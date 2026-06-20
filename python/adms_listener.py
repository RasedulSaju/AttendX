#!/usr/bin/env python3
"""
AttendX – ADMS Push Listener
Receives HTTP push data from Granding F09/ID biometric devices.
Devices push to: http://<server>:8080/iclock/cdata  (primary)
                 http://<server>:8081/iclock/cdata  (secondary)

Protocol: ZKTeco/Granding ADMS (Attendance Data Management System)
Ports   : 8080 (primary), 8081 (secondary) — NOT 4370 (SDK pull only)
"""

import os
import sys
import json
import logging
import hashlib
import hmac
import threading
import signal
from datetime import datetime
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
from typing import Optional

import mysql.connector
from mysql.connector import Error as MySQLError

# ─── Configuration ──────────────────────────────────────────────────────────────

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

ADMS_PRIMARY_PORT   = int(os.environ.get('ADMS_PORT',           '8080'))
ADMS_SECONDARY_PORT = int(os.environ.get('ADMS_PORT_SECONDARY', '8081'))

LOG_LEVEL = os.environ.get('LOG_LEVEL', 'INFO').upper()
LOG_FILE  = os.environ.get('LOG_FILE',  os.path.join(os.path.dirname(__file__), '..', 'storage', 'logs', 'adms_listener.log'))

# Ensure log directory exists
os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)

# ─── Logging ─────────────────────────────────────────────────────────────────────

logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger('adms_listener')

# ─── Database Helper ─────────────────────────────────────────────────────────────

class Database:
    _local = threading.local()

    @classmethod
    def get_connection(cls):
        conn = getattr(cls._local, 'connection', None)
        if conn is None or not conn.is_connected():
            try:
                conn = mysql.connector.connect(**DB_CONFIG)
                cls._local.connection = conn
                logger.debug('New DB connection created for thread %s', threading.current_thread().name)
            except MySQLError as e:
                logger.error('DB connection failed: %s', e)
                raise
        return conn

    @classmethod
    def execute(cls, sql: str, params: tuple = (), fetch: bool = False):
        conn   = cls.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(sql, params)
            if fetch:
                return cursor.fetchall()
            return cursor.rowcount
        except MySQLError as e:
            logger.error('DB error [%s] – %s', sql[:80], e)
            raise
        finally:
            cursor.close()

    @classmethod
    def fetchone(cls, sql: str, params: tuple = ()):
        conn   = cls.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(sql, params)
            return cursor.fetchone()
        except MySQLError as e:
            logger.error('DB fetchone error: %s', e)
            raise
        finally:
            cursor.close()

# ─── Device Registry ─────────────────────────────────────────────────────────────

def get_device_by_serial(serial_number: str) -> Optional[dict]:
    """Look up device by serial number; auto-register if unknown."""
    row = Database.fetchone(
        "SELECT id, name, status, is_active FROM devices WHERE serial_number = %s",
        (serial_number,)
    )
    if row:
        return row

    # Auto-register unknown device
    logger.info('Auto-registering new device: %s', serial_number)
    Database.execute(
        """INSERT INTO devices (name, serial_number, model, status, is_active, created_at)
           VALUES (%s, %s, 'Unknown', 'online', 1, NOW())
           ON DUPLICATE KEY UPDATE status='online', updated_at=NOW()""",
        (f'Device-{serial_number}', serial_number)
    )
    return Database.fetchone(
        "SELECT id, name, status, is_active FROM devices WHERE serial_number = %s",
        (serial_number,)
    )

def update_device_heartbeat(device_id: int, ip_address: str) -> None:
    Database.execute(
        "UPDATE devices SET status='online', last_sync_at=NOW(), ip_address=%s WHERE id=%s",
        (ip_address, device_id)
    )

# ─── Attendance Log Insertion ─────────────────────────────────────────────────────

def get_employee_by_badge(badge_number: str) -> Optional[dict]:
    return Database.fetchone(
        "SELECT id, employee_code, full_name FROM employees WHERE badge_number = %s AND status = 'active'",
        (badge_number,)
    )

def insert_attendance_log(device_id: int, employee_id: Optional[int],
                          badge_number: str, punch_time: str,
                          punch_type: int, verify_type: int) -> bool:
    """
    Insert raw punch into attendance_logs.
    punch_type: 0=check-in, 1=check-out, 2=break-out, 3=break-in, 4=overtime-in, 5=overtime-out
    """
    # Deduplicate: skip if same device+badge+time already exists (±30s window)
    existing = Database.fetchone(
        """SELECT id FROM attendance_logs
           WHERE device_id=%s AND badge_number=%s
             AND ABS(TIMESTAMPDIFF(SECOND, punch_time, %s)) <= 30
           LIMIT 1""",
        (device_id, badge_number, punch_time)
    )
    if existing:
        logger.debug('Duplicate punch skipped: badge=%s time=%s', badge_number, punch_time)
        return False

    Database.execute(
        """INSERT INTO attendance_logs
               (device_id, employee_id, badge_number, punch_time, punch_type, verify_type, source, synced_at)
           VALUES (%s, %s, %s, %s, %s, %s, 'adms', NOW())""",
        (device_id, employee_id, badge_number, punch_time, punch_type, verify_type)
    )
    return True

# ─── ADMS Protocol Parser ────────────────────────────────────────────────────────

def parse_adms_record(line: str) -> Optional[dict]:
    """
    Parse a single ADMS attendance record line.
    Format: PIN\tDateTime\tStatus\tVerify\tWorkCode\tReserved
    Example: 10001\t2025-05-01 08:32:44\t0\t1\t0\t0
    """
    parts = line.strip().split('\t')
    if len(parts) < 4:
        # Try space-delimited fallback
        parts = line.strip().split()
    if len(parts) < 4:
        logger.warning('Unparseable ADMS line: %r', line)
        return None

    try:
        dt = datetime.strptime(parts[1].strip(), '%Y-%m-%d %H:%M:%S')
    except ValueError:
        try:
            dt = datetime.strptime(parts[1].strip(), '%Y/%m/%d %H:%M:%S')
        except ValueError:
            logger.warning('Unrecognised datetime: %s', parts[1])
            return None

    return {
        'pin':         parts[0].strip(),
        'punch_time':  dt.strftime('%Y-%m-%d %H:%M:%S'),
        'punch_type':  int(parts[2].strip()) if parts[2].strip().isdigit() else 0,
        'verify_type': int(parts[3].strip()) if len(parts) > 3 and parts[3].strip().isdigit() else 0,
    }

# ─── HTTP Request Handler ────────────────────────────────────────────────────────

class ADMSHandler(BaseHTTPRequestHandler):
    server_version = 'AttendX/1.0'

    def log_message(self, fmt, *args):
        """Suppress default HTTP log; use our logger instead."""
        logger.debug('%s – %s', self.address_string(), fmt % args)

    def send_adms_response(self, code: int, body: str = '') -> None:
        self.send_response(code)
        self.send_header('Content-Type', 'text/plain; charset=utf-8')
        self.send_header('Content-Length', str(len(body.encode('utf-8'))))
        self.end_headers()
        if body:
            self.wfile.write(body.encode('utf-8'))

    def get_serial(self) -> Optional[str]:
        """Extract device serial from query string or custom header."""
        parsed = urlparse(self.path)
        qs     = parse_qs(parsed.query)
        # Devices send SN= in query or X-Device-SN header
        sn = qs.get('SN', qs.get('sn', [None]))[0]
        if not sn:
            sn = self.headers.get('X-Device-SN') or self.headers.get('SN')
        return sn.strip() if sn else None

    def do_GET(self):
        """
        Respond to device heartbeat / time sync requests.
        GET /iclock/cdata?SN=<serial>&options=all,errorDelay=60,...
        GET /iclock/getrequest?SN=<serial>
        """
        parsed = urlparse(self.path)
        path   = parsed.path.rstrip('/')

        if path == '/iclock/cdata':
            self._handle_info_request()
        elif path == '/iclock/getrequest':
            self._handle_getrequest()
        else:
            self.send_adms_response(200, 'OK')

    def do_POST(self):
        """
        Receive attendance data pushed by device.
        POST /iclock/cdata?SN=<serial>
        Body: ADMS formatted attendance records (tab-delimited lines)
        """
        parsed = urlparse(self.path)
        path   = parsed.path.rstrip('/')

        if path == '/iclock/cdata':
            self._handle_attendance_push()
        else:
            self.send_adms_response(200, 'OK')

    def _handle_info_request(self):
        """Respond to device info / options request with server time."""
        serial = self.get_serial()
        if not serial:
            self.send_adms_response(400, 'Missing SN')
            return

        ip = self.client_address[0]
        logger.info('Heartbeat from device SN=%s IP=%s', serial, ip)

        try:
            device = get_device_by_serial(serial)
            if device:
                update_device_heartbeat(device['id'], ip)
        except Exception as e:
            logger.error('Heartbeat DB error: %s', e)

        # Server time response — device syncs its clock to this
        server_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        body = (
            f'GET OPTION FROM: {serial}\n'
            f'Stamp=9999\n'
            f'OpStamp=9999\n'
            f'ErrorDelay=60\n'
            f'Delay=30\n'
            f'TransTimes=00:00;14:05\n'
            f'TransInterval=1\n'
            f'TransFlag=TransData AttLog\tOpLog\tEnrollUser\tChkUser\tEnrollFP\tChkFP\n'
            f'TimeZone=6\n'
            f'Realtime=1\n'
            f'Encrypt=0\n'
        )
        self.send_adms_response(200, body)

    def _handle_getrequest(self):
        """Device polls for pending commands (e.g. enroll, delete user)."""
        # No pending commands by default; return empty OK
        self.send_adms_response(200, 'OK')

    def _handle_attendance_push(self):
        """Process batch attendance records pushed from device."""
        serial = self.get_serial()
        if not serial:
            self.send_adms_response(400, 'Missing SN')
            return

        content_length = int(self.headers.get('Content-Length', 0))
        if content_length == 0:
            self.send_adms_response(200, 'OK')
            return

        raw_body = self.rfile.read(content_length)
        body     = raw_body.decode('utf-8', errors='replace')

        logger.info('Attendance push from SN=%s len=%d', serial, content_length)
        logger.debug('Body: %r', body[:500])

        try:
            device = get_device_by_serial(serial)
            if not device:
                self.send_adms_response(500, 'Device lookup failed')
                return

            if not device.get('is_active'):
                logger.warning('Ignoring push from disabled device SN=%s', serial)
                self.send_adms_response(200, 'OK')
                return

            ip = self.client_address[0]
            update_device_heartbeat(device['id'], ip)

            inserted = 0
            skipped  = 0

            for line in body.splitlines():
                line = line.strip()
                # ADMS body starts with "ATTLOG START" and ends with "ATTLOG END"
                if not line or line.startswith('ATTLOG') or line.startswith('OPERLOG'):
                    continue

                record = parse_adms_record(line)
                if not record:
                    skipped += 1
                    continue

                # Resolve badge → employee
                emp = get_employee_by_badge(record['pin'])
                emp_id = emp['id'] if emp else None

                if not emp_id:
                    logger.debug('No active employee for badge/PIN: %s', record['pin'])

                ok = insert_attendance_log(
                    device_id    = device['id'],
                    employee_id  = emp_id,
                    badge_number = record['pin'],
                    punch_time   = record['punch_time'],
                    punch_type   = record['punch_type'],
                    verify_type  = record['verify_type'],
                )
                if ok:
                    inserted += 1
                else:
                    skipped += 1

            logger.info('SN=%s: inserted=%d skipped=%d', serial, inserted, skipped)

            # Notify PHP processor via flag table
            if inserted > 0:
                self._notify_processor(device['id'])

            # ADMS expects "OK" (case-sensitive)
            self.send_adms_response(200, 'OK')

        except Exception as e:
            logger.exception('Error processing attendance push: %s', e)
            self.send_adms_response(500, 'Internal Error')

    def _notify_processor(self, device_id: int) -> None:
        """Signal PHP attendance processor that new logs are ready."""
        try:
            Database.execute(
                """INSERT INTO processing_queue (device_id, queued_at)
                   VALUES (%s, NOW())
                   ON DUPLICATE KEY UPDATE queued_at=NOW(), processed=0""",
                (device_id,)
            )
        except Exception as e:
            logger.warning('Could not notify processor: %s', e)

# ─── Multi-port Server ───────────────────────────────────────────────────────────

class ThreadedHTTPServer(HTTPServer):
    """HTTP server with per-request thread spawning."""
    def process_request(self, request, client_address):
        t = threading.Thread(
            target=self._process_request_thread,
            args=(request, client_address),
            daemon=True,
        )
        t.start()

    def _process_request_thread(self, request, client_address):
        try:
            self.finish_request(request, client_address)
        except Exception:
            self.handle_error(request, client_address)
        finally:
            self.shutdown_request(request)

def start_server(port: int) -> ThreadedHTTPServer:
    server = ThreadedHTTPServer(('0.0.0.0', port), ADMSHandler)
    t = threading.Thread(target=server.serve_forever, name=f'adms-{port}', daemon=True)
    t.start()
    logger.info('ADMS listener started on port %d', port)
    return server

# ─── Entry Point ─────────────────────────────────────────────────────────────────

def main():
    logger.info('=' * 60)
    logger.info('AttendX ADMS Listener starting')
    logger.info('Primary port  : %d', ADMS_PRIMARY_PORT)
    logger.info('Secondary port: %d', ADMS_SECONDARY_PORT)
    logger.info('=' * 60)

    # Verify DB connectivity on startup
    try:
        Database.execute('SELECT 1')
        logger.info('Database connection OK')
    except Exception as e:
        logger.critical('Cannot connect to database: %s', e)
        sys.exit(1)

    # Ensure processing_queue table exists
    try:
        Database.execute("""
            CREATE TABLE IF NOT EXISTS `processing_queue` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `device_id`  INT UNSIGNED NOT NULL,
                `queued_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `processed`  TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY `uq_device` (`device_id`)
            ) ENGINE=InnoDB
        """)
    except Exception:
        pass  # Table likely already exists

    servers = [
        start_server(ADMS_PRIMARY_PORT),
        start_server(ADMS_SECONDARY_PORT),
    ]

    # Graceful shutdown on SIGINT / SIGTERM
    def shutdown(signum, frame):
        logger.info('Shutting down ADMS listener…')
        for s in servers:
            s.shutdown()
        sys.exit(0)

    signal.signal(signal.SIGINT,  shutdown)
    signal.signal(signal.SIGTERM, shutdown)

    logger.info('ADMS listener ready. Waiting for device pushes…')
    signal.pause()

if __name__ == '__main__':
    main()
