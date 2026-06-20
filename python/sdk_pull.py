#!/usr/bin/env python3
"""
AttendX – SDK Pull Service
Actively pulls attendance logs from Granding F09/ID devices via ZKTeco SDK protocol.
Port: 4370 (UDP/TCP) — SDK pull-only, NOT the ADMS push port (8080/8081).

Dependencies:
    pip install pyzk mysql-connector-python

Run: python sdk_pull.py
     or as Windows service via NSSM
"""

import os
import sys
import time
import signal
import logging
import threading
from datetime import datetime, timedelta
from typing import Optional, List, Dict

import mysql.connector
from mysql.connector import Error as MySQLError

try:
    from zk import ZK, const as ZKConst
except ImportError:
    print("ERROR: pyzk not installed. Run: pip install pyzk")
    sys.exit(1)

# ─── Configuration ────────────────────────────────────────────────────────────

DB_CONFIG = {
    'host':     os.environ.get('DB_HOST',     '127.0.0.1'),
    'port':     int(os.environ.get('DB_PORT', '3306')),
    'user':     os.environ.get('DB_USER',     'attendx'),
    'password': os.environ.get('DB_PASS',     ''),
    'database': os.environ.get('DB_NAME',     'attendx'),
    'charset':  'utf8mb4',
    'autocommit': True,
    'connection_timeout': 10,
}

SDK_PORT          = int(os.environ.get('SDK_PORT',          '4370'))
PULL_INTERVAL_SEC = int(os.environ.get('PULL_INTERVAL_SEC', '300'))   # 5 minutes default
CONN_TIMEOUT_SEC  = int(os.environ.get('CONN_TIMEOUT_SEC',  '15'))
PASSWORD          = int(os.environ.get('DEVICE_PASSWORD',   '0'))     # Device comm password
LOG_LEVEL         = os.environ.get('LOG_LEVEL', 'INFO').upper()
LOG_FILE          = os.environ.get(
    'LOG_FILE',
    os.path.join(os.path.dirname(__file__), '..', 'storage', 'logs', 'sdk_pull.log')
)

os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)

# ─── Logging ─────────────────────────────────────────────────────────────────

logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger('sdk_pull')

# ─── Database ─────────────────────────────────────────────────────────────────

class Database:
    _local = threading.local()

    @classmethod
    def get_connection(cls):
        conn = getattr(cls._local, 'connection', None)
        if conn is None or not conn.is_connected():
            conn = mysql.connector.connect(**DB_CONFIG)
            cls._local.connection = conn
        return conn

    @classmethod
    def execute(cls, sql: str, params: tuple = ()):
        conn   = cls.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(sql, params)
            return cursor.rowcount
        except MySQLError as e:
            logger.error('DB execute error: %s | SQL: %s', e, sql[:100])
            raise
        finally:
            cursor.close()

    @classmethod
    def fetchall(cls, sql: str, params: tuple = ()) -> List[dict]:
        conn   = cls.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(sql, params)
            return cursor.fetchall()
        finally:
            cursor.close()

    @classmethod
    def fetchone(cls, sql: str, params: tuple = ()) -> Optional[dict]:
        conn   = cls.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(sql, params)
            return cursor.fetchone()
        finally:
            cursor.close()

# ─── Device Helpers ───────────────────────────────────────────────────────────

def get_active_devices() -> List[dict]:
    """Fetch all active devices configured for SDK pull."""
    return Database.fetchall(
        """SELECT id, name, ip_address, serial_number, last_sync_at
           FROM devices
           WHERE is_active = 1 AND status != 'disabled'
           ORDER BY id"""
    )

def mark_device_online(device_id: int, ip: str) -> None:
    Database.execute(
        "UPDATE devices SET status='online', last_sync_at=NOW(), ip_address=%s WHERE id=%s",
        (ip, device_id)
    )

def mark_device_offline(device_id: int) -> None:
    Database.execute(
        "UPDATE devices SET status='offline', updated_at=NOW() WHERE id=%s",
        (device_id,)
    )

def get_last_sync_time(device_id: int) -> Optional[datetime]:
    row = Database.fetchone(
        "SELECT last_sync_at FROM devices WHERE id = %s", (device_id,)
    )
    if row and row['last_sync_at']:
        return row['last_sync_at']
    return datetime.now() - timedelta(days=1)  # Default: pull last 24h on first sync

def get_employee_by_badge(badge: str) -> Optional[dict]:
    return Database.fetchone(
        "SELECT id FROM employees WHERE badge_number = %s AND status = 'active'",
        (badge,)
    )

# ─── Punch Insertion ──────────────────────────────────────────────────────────

def insert_punch(device_id: int, employee_id: Optional[int],
                 badge: str, punch_time: datetime,
                 punch_type: int, verify_type: int) -> bool:
    """Insert punch with deduplication (±30 second window)."""
    pt_str = punch_time.strftime('%Y-%m-%d %H:%M:%S')

    existing = Database.fetchone(
        """SELECT id FROM attendance_logs
           WHERE device_id=%s AND badge_number=%s
             AND ABS(TIMESTAMPDIFF(SECOND, punch_time, %s)) <= 30
           LIMIT 1""",
        (device_id, badge, pt_str)
    )
    if existing:
        return False

    Database.execute(
        """INSERT INTO attendance_logs
               (device_id, employee_id, badge_number, punch_time, punch_type, verify_type, source, synced_at)
           VALUES (%s, %s, %s, %s, %s, %s, 'sdk', NOW())""",
        (device_id, employee_id, badge, pt_str, punch_type, verify_type)
    )
    return True

def notify_processor(device_id: int) -> None:
    try:
        Database.execute(
            """INSERT INTO processing_queue (device_id, queued_at)
               VALUES (%s, NOW())
               ON DUPLICATE KEY UPDATE queued_at=NOW(), processed=0""",
            (device_id,)
        )
    except Exception as e:
        logger.warning('notify_processor error: %s', e)

# ─── Device Sync ──────────────────────────────────────────────────────────────

def sync_device_users(zk_conn, device_id: int) -> Dict[str, int]:
    """
    Sync device user list to DB (badge_number→user_id mapping).
    Returns mapping: {user_id_on_device: employee_db_id}
    """
    mapping: Dict[str, int] = {}
    try:
        users = zk_conn.get_users()
        for u in users:
            emp = get_employee_by_badge(str(u.user_id))
            if emp:
                mapping[str(u.user_id)] = emp['id']
        logger.debug('Device %d: synced %d user mappings', device_id, len(mapping))
    except Exception as e:
        logger.warning('User sync error for device %d: %s', device_id, e)
    return mapping

def pull_device(device: dict) -> int:
    """
    Connect to one device, pull attendance logs since last sync.
    Returns number of new records inserted.
    """
    device_id = device['id']
    ip        = device['ip_address']
    name      = device['name']

    if not ip:
        logger.warning('Device %d (%s) has no IP address, skipping.', device_id, name)
        return 0

    logger.info('Connecting to device %d (%s) at %s:%d', device_id, name, ip, SDK_PORT)

    zk = ZK(
        ip,
        port=SDK_PORT,
        timeout=CONN_TIMEOUT_SEC,
        password=PASSWORD,
        force_udp=False,
        ommit_ping=False,
    )
    conn = None
    inserted = 0

    try:
        conn = zk.connect()
        conn.disable_device()

        mark_device_online(device_id, ip)
        last_sync = get_last_sync_time(device_id)
        logger.info('Device %s: pulling since %s', name, last_sync)

        user_mapping = sync_device_users(conn, device_id)

        attendances = conn.get_attendance()
        skipped = 0

        for att in attendances:
            # Skip records older than last sync (with 2-minute overlap buffer)
            if att.timestamp < last_sync - timedelta(minutes=2):
                skipped += 1
                continue

            badge      = str(att.user_id)
            emp_id     = user_mapping.get(badge)
            punch_type = getattr(att, 'punch', 0)      # 0=in,1=out,4=OT-in,5=OT-out
            verify     = getattr(att, 'status', 0)     # verify mode

            ok = insert_punch(
                device_id   = device_id,
                employee_id = emp_id,
                badge       = badge,
                punch_time  = att.timestamp,
                punch_type  = punch_type,
                verify_type = verify,
            )
            if ok:
                inserted += 1

        logger.info('Device %s: inserted=%d skipped=%d (already synced)',
                    name, inserted, skipped)

        if inserted > 0:
            notify_processor(device_id)

        conn.enable_device()
        return inserted

    except Exception as e:
        logger.error('Pull error for device %s (%s): %s', name, ip, e)
        mark_device_offline(device_id)
        return 0

    finally:
        if conn:
            try:
                conn.enable_device()
                conn.disconnect()
            except Exception:
                pass

# ─── Device Info & Enrollment ─────────────────────────────────────────────────

def get_device_info(device: dict) -> Optional[dict]:
    """Fetch hardware info from device (firmware, serial, etc.)."""
    ip = device['ip_address']
    if not ip:
        return None

    zk = ZK(ip, port=SDK_PORT, timeout=CONN_TIMEOUT_SEC, password=PASSWORD)
    conn = None
    try:
        conn = zk.connect()
        info = {
            'firmware':      conn.get_firmware_version(),
            'serial_number': conn.get_serialnumber(),
            'platform':      conn.get_platform(),
            'fp_version':    conn.get_fp_version(),
            'mac_address':   conn.get_mac(),
            'face_algorithm':conn.get_face_fun_on(),
            'user_count':    conn.get_user_count(),
            'fp_count':      conn.get_fp_count(),
            'record_count':  conn.get_att_log_count(),
        }
        # Update serial number in DB
        Database.execute(
            "UPDATE devices SET serial_number=%s, model=%s WHERE id=%s",
            (info['serial_number'], info['platform'], device['id'])
        )
        return info
    except Exception as e:
        logger.error('get_device_info error for %s: %s', ip, e)
        return None
    finally:
        if conn:
            try:
                conn.disconnect()
            except Exception:
                pass

def clear_device_logs(device: dict, confirm: bool = False) -> bool:
    """Clear all attendance logs from device (use with caution)."""
    if not confirm:
        logger.warning('clear_device_logs called without confirm=True, aborting.')
        return False

    ip = device['ip_address']
    zk = ZK(ip, port=SDK_PORT, timeout=CONN_TIMEOUT_SEC, password=PASSWORD)
    conn = None
    try:
        conn = zk.connect()
        conn.disable_device()
        conn.clear_attendance()
        conn.enable_device()
        logger.info('Cleared attendance logs on device %s', device['name'])
        return True
    except Exception as e:
        logger.error('clear_device_logs error: %s', e)
        return False
    finally:
        if conn:
            try:
                conn.enable_device()
                conn.disconnect()
            except Exception:
                pass

def set_device_time(device: dict) -> bool:
    """Sync device clock to server time."""
    ip = device['ip_address']
    zk = ZK(ip, port=SDK_PORT, timeout=CONN_TIMEOUT_SEC, password=PASSWORD)
    conn = None
    try:
        conn = zk.connect()
        conn.set_time(datetime.now())
        logger.info('Time synced on device %s', device['name'])
        return True
    except Exception as e:
        logger.error('set_device_time error for %s: %s', device['name'], e)
        return False
    finally:
        if conn:
            try:
                conn.disconnect()
            except Exception:
                pass

def push_user_to_device(device: dict, employee_id: int) -> bool:
    """Push a single employee record to device."""
    emp = Database.fetchone(
        "SELECT id, full_name, badge_number, privilege FROM employees WHERE id=%s",
        (employee_id,)
    )
    if not emp or not emp['badge_number']:
        logger.warning('push_user_to_device: employee %d not found or no badge', employee_id)
        return False

    ip = device['ip_address']
    zk = ZK(ip, port=SDK_PORT, timeout=CONN_TIMEOUT_SEC, password=PASSWORD)
    conn = None
    try:
        conn = zk.connect()
        conn.disable_device()
        conn.set_user(
            uid       = int(emp['badge_number']) if emp['badge_number'].isdigit() else None,
            name      = emp['full_name'][:24],
            privilege = ZKConst.USER_DEFAULT,
            password  = '',
            group_id  = '',
            user_id   = emp['badge_number'],
        )
        conn.enable_device()
        logger.info('Pushed employee %s to device %s', emp['full_name'], device['name'])
        return True
    except Exception as e:
        logger.error('push_user_to_device error: %s', e)
        return False
    finally:
        if conn:
            try:
                conn.enable_device()
                conn.disconnect()
            except Exception:
                pass

# ─── Pull Loop ────────────────────────────────────────────────────────────────

_running = True

def pull_all_devices() -> None:
    """Pull from all active devices sequentially."""
    devices = get_active_devices()
    if not devices:
        logger.info('No active devices found.')
        return

    logger.info('Starting pull cycle for %d device(s)', len(devices))
    total = 0
    for device in devices:
        if not _running:
            break
        count = pull_device(device)
        total += count

    logger.info('Pull cycle complete. Total new records: %d', total)

def run_scheduler() -> None:
    """Main scheduler loop: pull every PULL_INTERVAL_SEC seconds."""
    logger.info('SDK pull scheduler started. Interval: %ds', PULL_INTERVAL_SEC)

    while _running:
        start = time.time()
        try:
            pull_all_devices()
        except Exception as e:
            logger.exception('Unhandled error in pull cycle: %s', e)

        elapsed = time.time() - start
        sleep   = max(0, PULL_INTERVAL_SEC - elapsed)
        logger.debug('Next pull in %.0f seconds', sleep)

        # Sleep in chunks to allow clean shutdown
        slept = 0
        while _running and slept < sleep:
            time.sleep(min(5, sleep - slept))
            slept += 5

# ─── Entry Point ─────────────────────────────────────────────────────────────

def main():
    global _running

    logger.info('=' * 60)
    logger.info('AttendX SDK Pull Service')
    logger.info('SDK Port      : %d', SDK_PORT)
    logger.info('Pull Interval : %ds', PULL_INTERVAL_SEC)
    logger.info('=' * 60)

    # Handle command-line arguments for one-shot operations
    if len(sys.argv) > 1:
        cmd = sys.argv[1].lower()

        if cmd == 'pull':
            # One-shot pull for all devices
            try:
                Database.execute('SELECT 1')
            except Exception as e:
                logger.critical('DB connection failed: %s', e)
                sys.exit(1)
            pull_all_devices()
            return

        elif cmd == 'info' and len(sys.argv) > 2:
            # Print device info: python sdk_pull.py info <device_id>
            device_id = int(sys.argv[2])
            device = Database.fetchone("SELECT * FROM devices WHERE id=%s", (device_id,))
            if not device:
                print(f'Device {device_id} not found.')
                sys.exit(1)
            info = get_device_info(device)
            if info:
                for k, v in info.items():
                    print(f'{k:20}: {v}')
            return

        elif cmd == 'synctime' and len(sys.argv) > 2:
            device_id = int(sys.argv[2])
            device = Database.fetchone("SELECT * FROM devices WHERE id=%s", (device_id,))
            if device:
                ok = set_device_time(device)
                print('Time synced.' if ok else 'Sync failed.')
            return

        elif cmd == 'clearogs' and len(sys.argv) > 2:
            device_id = int(sys.argv[2])
            device = Database.fetchone("SELECT * FROM devices WHERE id=%s", (device_id,))
            if device:
                ok = clear_device_logs(device, confirm=True)
                print('Logs cleared.' if ok else 'Clear failed.')
            return

        else:
            print(f'Unknown command: {cmd}')
            print('Usage: sdk_pull.py [pull|info <id>|synctime <id>|clearlogs <id>]')
            sys.exit(1)

    # Verify DB on startup
    try:
        Database.execute('SELECT 1')
        logger.info('Database connection OK')
    except Exception as e:
        logger.critical('Cannot connect to database: %s', e)
        sys.exit(1)

    # Graceful shutdown
    def shutdown(signum, frame):
        global _running
        logger.info('Shutdown signal received.')
        _running = False

    signal.signal(signal.SIGINT,  shutdown)
    signal.signal(signal.SIGTERM, shutdown)

    # Run immediate pull on start, then schedule
    try:
        pull_all_devices()
    except Exception as e:
        logger.exception('Initial pull error: %s', e)

    run_scheduler()
    logger.info('SDK Pull Service stopped.')

if __name__ == '__main__':
    main()
