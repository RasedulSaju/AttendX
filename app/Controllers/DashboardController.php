<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\AnalyticsModel;
use App\Models\AttendanceModel;
use App\Models\LeaveModel;
use App\Models\DeviceModel;
use App\Models\NotificationModel;

class DashboardController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
    }

    public function index(): void
    {
        $today      = date('Y-m-d');
        $monthFrom  = date('Y-m-01');

        $analytics   = new AnalyticsModel();
        $attModel    = new AttendanceModel();
        $leaveModel  = new LeaveModel();
        $deviceModel = new DeviceModel();
        $notifModel  = new NotificationModel();

        $kpi         = $analytics->getKpiSnapshot($today);
        $dailyTrend  = $analytics->getDailyAttendanceTrend(
            date('Y-m-d', strtotime('-13 days')), $today
        );
        $deptData    = $analytics->getAttendanceByDepartment($monthFrom, $today);
        $deviceStats = $deviceModel->getStats();
        $todayStats  = $attModel->getDailyStats($today);
        $pendingLeaves = $leaveModel->countPending();
        $unreadNotif = $notifModel->countUnread(Auth::id());

        $recentAttendance = $attModel->getRecords([
            'date_from' => $today,
            'date_to'   => $today,
        ], 10, 0);

        $this->view('dashboard/index', [
            'title'            => 'Dashboard',
            'kpi'              => $kpi,
            'dailyTrend'       => json_encode($dailyTrend),
            'deptData'         => json_encode($deptData),
            'deviceStats'      => $deviceStats,
            'todayStats'       => $todayStats,
            'pendingLeaves'    => $pendingLeaves,
            'unreadNotif'      => $unreadNotif,
            'recentAttendance' => $recentAttendance,
        ]);
    }
}
