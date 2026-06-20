#!/usr/bin/env python3
"""
AttendX Windows Service Wrapper
Runs both adms_listener.py and sdk_pull.py as a single Windows service.

Installation (run as Administrator):
    python windows_service.py install
    python windows_service.py start
    python windows_service.py stop
    python windows_service.py remove

Alternatively use NSSM (https://nssm.cc):
    nssm install AttendX-ADMS python.exe C:\attendx\python\adms_listener.py
    nssm install AttendX-SDK  python.exe C:\attendx\python\sdk_pull.py
"""

import sys
import os
import time
import logging
import threading
import subprocess

LOG_FILE = os.path.join(os.path.dirname(__file__), '..', 'storage', 'logs', 'windows_service.log')
os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger('windows_service')

PYTHON_EXE   = sys.executable
SCRIPT_DIR   = os.path.dirname(os.path.abspath(__file__))
ADMS_SCRIPT  = os.path.join(SCRIPT_DIR, 'adms_listener.py')
SDK_SCRIPT   = os.path.join(SCRIPT_DIR, 'sdk_pull.py')

_procs = {}
_running = True

# ─── Process Management ───────────────────────────────────────────────────────

def start_process(name: str, script: str) -> subprocess.Popen:
    env = os.environ.copy()
    env['PYTHONUNBUFFERED'] = '1'

    proc = subprocess.Popen(
        [PYTHON_EXE, script],
        env=env,
        cwd=SCRIPT_DIR,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    logger.info('Started %s (PID %d)', name, proc.pid)
    return proc

def monitor_process(name: str, script: str) -> None:
    """Restart a process if it dies unexpectedly."""
    global _running
    while _running:
        proc = start_process(name, script)
        _procs[name] = proc

        # Stream stdout to log
        def stream_log(p, n):
            for line in iter(p.stdout.readline, b''):
                logger.info('[%s] %s', n, line.decode('utf-8', errors='replace').rstrip())
        t = threading.Thread(target=stream_log, args=(proc, name), daemon=True)
        t.start()

        proc.wait()

        if _running:
            logger.warning('%s exited (code %d). Restarting in 10s…', name, proc.returncode)
            time.sleep(10)

def stop_all() -> None:
    global _running
    _running = False
    for name, proc in _procs.items():
        logger.info('Stopping %s (PID %d)…', name, proc.pid)
        proc.terminate()
        try:
            proc.wait(timeout=10)
        except subprocess.TimeoutExpired:
            proc.kill()
    logger.info('All processes stopped.')

# ─── Windows Service Class ───────────────────────────────────────────────────

try:
    import win32serviceutil
    import win32service
    import win32event
    import servicemanager

    class AttendXService(win32serviceutil.ServiceFramework):
        _svc_name_         = 'AttendX'
        _svc_display_name_ = 'AttendX Biometric Service'
        _svc_description_  = 'Handles ADMS push (ports 8080/8081) and SDK pull (port 4370) for Granding F09/ID devices.'

        def __init__(self, args):
            win32serviceutil.ServiceFramework.__init__(self, args)
            self._stop_event = win32event.CreateEvent(None, 0, 0, None)

        def SvcStop(self):
            self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
            win32event.SetEvent(self._stop_event)
            stop_all()
            self.ReportServiceStatus(win32service.SERVICE_STOPPED)

        def SvcDoRun(self):
            servicemanager.LogMsg(
                servicemanager.EVENTLOG_INFORMATION_TYPE,
                servicemanager.PYS_SERVICE_STARTED,
                (self._svc_name_, '')
            )
            self._run()

        def _run(self):
            logger.info('AttendX Service starting…')

            adms_thread = threading.Thread(
                target=monitor_process,
                args=('ADMS Listener', ADMS_SCRIPT),
                daemon=True,
                name='adms-monitor',
            )
            sdk_thread = threading.Thread(
                target=monitor_process,
                args=('SDK Pull', SDK_SCRIPT),
                daemon=True,
                name='sdk-monitor',
            )

            adms_thread.start()
            sdk_thread.start()

            # Wait for stop signal
            win32event.WaitForSingleObject(self._stop_event, win32event.INFINITE)

    HAS_WIN32 = True

except ImportError:
    HAS_WIN32 = False

# ─── Entry Point ─────────────────────────────────────────────────────────────

def run_foreground():
    """Run both services in foreground (for testing / Linux)."""
    import signal

    logger.info('Running in foreground mode (Ctrl+C to stop)…')

    adms_thread = threading.Thread(
        target=monitor_process,
        args=('ADMS Listener', ADMS_SCRIPT),
        daemon=True,
        name='adms-monitor',
    )
    sdk_thread = threading.Thread(
        target=monitor_process,
        args=('SDK Pull', SDK_SCRIPT),
        daemon=True,
        name='sdk-monitor',
    )

    adms_thread.start()
    sdk_thread.start()

    def shutdown(sig, frame):
        logger.info('Shutdown signal received.')
        stop_all()
        sys.exit(0)

    signal.signal(signal.SIGINT,  shutdown)
    signal.signal(signal.SIGTERM, shutdown)

    adms_thread.join()
    sdk_thread.join()

def main():
    if len(sys.argv) < 2:
        print("Usage: windows_service.py [install|start|stop|remove|run]")
        sys.exit(1)

    cmd = sys.argv[1].lower()

    if cmd == 'run':
        run_foreground()
        return

    if not HAS_WIN32:
        logger.error('pywin32 not installed. Use "run" for foreground mode, or install pywin32 for Windows service.')
        sys.exit(1)

    if cmd in ('install', 'start', 'stop', 'remove', 'restart'):
        win32serviceutil.HandleCommandLine(AttendXService)
    else:
        print(f'Unknown command: {cmd}')
        sys.exit(1)

if __name__ == '__main__':
    main()
