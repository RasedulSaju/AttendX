<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Models\AccessControlModel;
use App\Models\EmployeeModel;
use App\Models\DeviceModel;

class AccessControlController extends Controller
{
    private AccessControlModel $acl;
    private EmployeeModel      $employee;
    private DeviceModel        $device;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('access_control');
        $this->acl      = new AccessControlModel();
        $this->employee = new EmployeeModel();
        $this->device   = new DeviceModel();
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $zones     = $this->acl->getAllZones(true);
        $recentLogs = $this->acl->getLogs([], 20, 0);
        $stats      = $this->acl->getAccessStats(date('Y-m-d'));

        $this->view('access_control/index', [
            'title'      => 'Access Control',
            'zones'      => $zones,
            'recentLogs' => $recentLogs,
            'stats'      => $stats,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Zones ───────────────────────────────────────────────────────────────────

    public function zones(): void
    {
        if (Request::isPost()) {
            CSRF::verify();
            $this->requirePermission('access_control.manage');
            $data  = Request::post();
            $rules = [
                'name'           => 'required|min:2|max:100',
                'zone_code'      => 'required|min:2|max:30',
                'security_level' => 'required|integer|min:1|max:4',
            ];
            if (!$this->validate($data, $rules)) {
                Session::flash('error', $this->getValidationErrors());
                Response::redirect('/access-control/zones');
                return;
            }

            $id = !empty($data['id']) ? (int)$data['id'] : null;
            if ($id) {
                $this->acl->updateZone($id, $data);
                Session::flash('success', 'Zone updated.');
            } else {
                $this->acl->createZone($data);
                Session::flash('success', 'Zone created.');
            }
            Response::redirect('/access-control/zones');
            return;
        }

        $this->view('access_control/zones', [
            'title'      => 'Access Zones',
            'zones'      => $this->acl->getAllZones(),
            'devices'    => $this->device->getAll(),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function deleteZone(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('access_control.manage');
        if (!$this->acl->deleteZone($id)) {
            Session::flash('error', 'Cannot delete zone: rules or logs depend on it.');
        } else {
            Session::flash('success', 'Zone deleted.');
        }
        Response::redirect('/access-control/zones');
    }

    // ─── Doors ───────────────────────────────────────────────────────────────────

    public function saveDoor(): void
    {
        if (!Request::isPost()) {
            Response::redirect('/access-control/zones');
            return;
        }
        CSRF::verify();
        $this->requirePermission('access_control.manage');

        $data  = Request::post();
        $rules = [
            'zone_id'    => 'required|integer',
            'device_id'  => 'required|integer',
            'name'       => 'required|min:2|max:100',
            'door_index' => 'required|integer|min:1|max:4',
            'direction'  => 'required|in:in,out,both',
        ];
        if (!$this->validate($data, $rules)) {
            Response::json(['success' => false, 'message' => $this->getValidationErrors()], 422);
            return;
        }

        $this->acl->createDoor($data);
        Response::json(['success' => true, 'message' => 'Door added.']);
    }

    // ─── Access Rules ─────────────────────────────────────────────────────────────

    public function rules(int $zoneId): void
    {
        $zone = $this->acl->getZoneById($zoneId);
        if (!$zone) {
            Session::flash('error', 'Zone not found.');
            Response::redirect('/access-control/zones');
            return;
        }

        if (Request::isPost()) {
            CSRF::verify();
            $this->requirePermission('access_control.manage');
            $data  = Request::post();
            $rules = [
                'rule_type'  => 'required|in:employee,department,designation,all',
                'permission' => 'required|in:allow,deny',
            ];
            if (!$this->validate($data, $rules)) {
                Session::flash('error', $this->getValidationErrors());
                Response::redirect('/access-control/rules/' . $zoneId);
                return;
            }
            $data['zone_id']    = $zoneId;
            $data['created_by'] = $this->auth->id();
            $this->acl->createRule($data);
            Session::flash('success', 'Rule added.');
            Response::redirect('/access-control/rules/' . $zoneId);
            return;
        }

        $employees    = $this->employee->getAll(['status' => 'active']);
        $departments  = (new \App\Models\DepartmentModel())->getAll();
        $designations = (new \App\Models\DesignationModel())->getAll();
        $schedules    = $this->acl->getAllSchedules();

        $this->view('access_control/rules', [
            'title'        => 'Access Rules: ' . $zone['name'],
            'zone'         => $zone,
            'rules'        => $this->acl->getRulesByZone($zoneId),
            'employees'    => $employees,
            'departments'  => $departments,
            'designations' => $designations,
            'schedules'    => $schedules,
            'csrf_token'   => CSRF::generate(),
        ]);
    }

    public function deleteRule(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('access_control.manage');
        $this->acl->deleteRule($id);
        Response::json(['success' => true]);
    }

    // ─── Access Logs ──────────────────────────────────────────────────────────────

    public function logs(): void
    {
        $filters = [
            'employee_id' => (int)Request::get('employee_id'),
            'zone_id'     => (int)Request::get('zone_id'),
            'event_type'  => Request::get('event_type', ''),
            'date_from'   => Request::get('date_from', date('Y-m-d')),
            'date_to'     => Request::get('date_to', date('Y-m-d')),
        ];
        $filters = array_filter($filters, fn($v) => $v !== '' && $v !== 0);

        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 50;
        $logs    = $this->acl->getLogs($filters, $perPage, ($page - 1) * $perPage);
        $total   = $this->acl->countLogs($filters);

        $this->view('access_control/logs', [
            'title'     => 'Access Logs',
            'logs'      => $logs,
            'filters'   => $filters,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'zones'     => $this->acl->getAllZones(),
            'employees' => $this->employee->getAll(['status' => 'active']),
        ]);
    }

    // ─── Visitor Passes ──────────────────────────────────────────────────────────

    public function visitors(): void
    {
        if (Request::isPost()) {
            CSRF::verify();
            $data  = Request::post();
            $rules = [
                'visitor_name'     => 'required|min:2|max:150',
                'visitor_id_type'  => 'required|in:nid,passport,driving_license,other',
                'visitor_id_no'    => 'required|min:4|max:50',
                'host_employee_id' => 'required|integer',
                'purpose'          => 'required|min:10|max:500',
                'valid_from'       => 'required|datetime',
                'valid_to'         => 'required|datetime',
            ];
            if (!$this->validate($data, $rules)) {
                Session::flash('error', $this->getValidationErrors());
                Response::redirect('/access-control/visitors');
                return;
            }

            $data['badge_code']  = strtoupper('VIS-' . uniqid());
            $data['created_by']  = $this->auth->id();
            $data['zones_allowed'] = !empty($data['zones']) ? $data['zones'] : null;
            $this->acl->createVisitorPass($data);
            Session::flash('success', 'Visitor pass created: ' . $data['badge_code']);
            Response::redirect('/access-control/visitors');
            return;
        }

        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 20;
        $passes  = $this->acl->getAllVisitorPasses($perPage, ($page - 1) * $perPage);

        $this->view('access_control/visitors', [
            'title'     => 'Visitor Management',
            'passes'    => $passes,
            'zones'     => $this->acl->getAllZones(true),
            'employees' => $this->employee->getAll(['status' => 'active']),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function checkoutVisitor(int $id): void
    {
        CSRF::verify();
        $this->acl->updateVisitorStatus($id, 'completed', null, date('Y-m-d H:i:s'));
        Response::json(['success' => true]);
    }

    public function revokeVisitor(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('access_control.manage');
        $this->acl->updateVisitorStatus($id, 'revoked');
        Response::json(['success' => true]);
    }

    // ─── Schedules ───────────────────────────────────────────────────────────────

    public function saveSchedule(): void
    {
        if (!Request::isPost()) {
            Response::redirect('/access-control/zones');
            return;
        }
        CSRF::verify();
        $this->requirePermission('access_control.manage');

        $data  = Request::post();
        $rules = [
            'name'       => 'required|min:2|max:100',
            'time_from'  => 'required|time',
            'time_to'    => 'required|time',
            'days_of_week' => 'required|array',
        ];
        if (!$this->validate($data, $rules)) {
            Response::json(['success' => false, 'message' => $this->getValidationErrors()], 422);
            return;
        }

        $id = $this->acl->createSchedule($data);
        Response::json(['success' => true, 'id' => $id]);
    }

    // ─── Live Monitor (JSON endpoint) ─────────────────────────────────────────────

    public function liveStats(): void
    {
        $stats   = $this->acl->getAccessStats(date('Y-m-d'));
        $recent  = $this->acl->getLogs([], 10, 0);
        Response::json([
            'stats'  => $stats,
            'recent' => $recent,
        ]);
    }

    // ─── Employee Zone Access ─────────────────────────────────────────────────────

    public function employeeAccess(int $employeeId): void
    {
        $emp   = $this->employee->getById($employeeId);
        if (!$emp) {
            Response::json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }
        $rules = $this->acl->getRulesForEmployee($employeeId);
        Response::json(['success' => true, 'employee' => $emp, 'rules' => $rules]);
    }
}
