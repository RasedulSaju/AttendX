<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\AuditLogger;
use App\Models\DeviceModel;

class DeviceController extends Controller
{
    private DeviceModel $device;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('devices');
        $this->device = new DeviceModel();
    }

    public function index(): void
    {
        $this->view('devices/index', [
            'title'      => 'Device Management',
            'devices'    => $this->device->getAll(),
            'stats'      => $this->device->getStats(),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function save(): void
    {
        if (!Request::isPost()) { Response::redirect('/devices'); return; }
        CSRF::verify();
        $this->requirePermission('devices.manage');

        $data  = Request::post();
        $id    = !empty($data['id']) ? (int)$data['id'] : null;
        $rules = [
            'name'       => 'required|min:2|max:100',
            'ip_address' => 'required',
        ];
        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/devices');
            return;
        }

        if ($id) {
            $old = $this->device->getById($id);
            $this->device->update($id, $data);
            AuditLogger::logChange('devices', 'device_updated', $old ?: [], $data);
            Session::flash('success', 'Device updated.');
        } else {
            $data['created_by'] = $this->auth->id();
            $newId = $this->device->create($data);
            AuditLogger::log('devices', 'device_created', "Added device: {$data['name']} ({$data['ip_address']})");
            Session::flash('success', 'Device added.');
        }
        Response::redirect('/devices');
    }

    public function delete(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('devices.manage');
        $this->device->delete($id);
        AuditLogger::log('devices', 'device_deleted', "Deleted device #$id");
        Response::json(['success' => true]);
    }

    public function sync(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->requirePermission('devices.manage');

        $device = $this->device->getById($id);
        if (!$device) { Response::json(['success' => false, 'message' => 'Device not found.'], 404); return; }

        // Enqueue for SDK pull by clearing processed flag
        \App\Core\Database::getInstance()->getConnection()->prepare("
            INSERT INTO processing_queue (device_id, queued_at, processed)
            VALUES (?, NOW(), 0)
            ON DUPLICATE KEY UPDATE queued_at=NOW(), processed=0
        ")->execute([$id]);

        AuditLogger::log('devices', 'device_sync_requested', "Manual sync requested for {$device['name']}");
        Response::json(['success' => true, 'message' => 'Sync queued. The SDK pull service will process it shortly.']);
    }

    public function toggle(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('devices.manage');
        $db = \App\Core\Database::getInstance()->getConnection();
        $db->prepare("UPDATE devices SET is_active = IF(is_active=1,0,1), updated_at=NOW() WHERE id=?")->execute([$id]);
        Response::json(['success' => true]);
    }

    public function logs(int $id): void
    {
        $device = $this->device->getById($id);
        if (!$device) Response::abort(404);
        $logs = $this->device->getRecentLogs($id, 50);
        Response::json(['success' => true, 'device' => $device, 'logs' => $logs]);
    }

    public function info(int $id): void
    {
        $device = $this->device->getById($id);
        if (!$device) { Response::json(['success' => false], 404); return; }
        Response::json(['success' => true, 'device' => $device]);
    }
}
