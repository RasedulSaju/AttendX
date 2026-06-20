<?php
declare(strict_types=1);

use App\Core\Router;

/**
 * AttendX Core Routes — Phases 1–10
 * Authentication, Dashboard, Employees, Attendance, Devices,
 * Leave, Shifts, Departments, Reports, Notifications.
 */

// ─── Auth ─────────────────────────────────────────────────────────────────────
Router::get('/login',           'AuthController@showLogin');
Router::post('/login',          'AuthController@login');
Router::post('/logout',         'AuthController@logout');
Router::get('/change-password', 'AuthController@showChangePassword');
Router::post('/change-password','AuthController@changePassword');
Router::get('/profile',         'AuthController@profile');
Router::post('/profile',        'AuthController@updateProfile');

// ─── Dashboard ────────────────────────────────────────────────────────────────
Router::get('/',          'DashboardController@index');
Router::get('/dashboard', 'DashboardController@index');

// ─── Employees ────────────────────────────────────────────────────────────────
Router::get('/employees',                       'EmployeeController@index');
Router::get('/employees/create',                'EmployeeController@create');
Router::post('/employees/create',               'EmployeeController@create');
Router::get('/employees/{id}',                  'EmployeeController@show');
Router::get('/employees/{id}/edit',             'EmployeeController@edit');
Router::post('/employees/{id}/edit',            'EmployeeController@edit');
Router::post('/employees/{id}/status',          'EmployeeController@updateStatus');
Router::post('/employees/{id}/transfer',        'EmployeeController@transfer');
Router::post('/employees/{id}/document',        'EmployeeController@uploadDocument');
Router::post('/employees/{id}/document/delete', 'EmployeeController@deleteDocument');
Router::get('/employees/search',                'EmployeeController@search');

// ─── Departments ──────────────────────────────────────────────────────────────
Router::get('/departments',          'DepartmentController@index');
Router::post('/departments/save',    'DepartmentController@save');
Router::post('/departments/{id}/delete','DepartmentController@delete');

// ─── Designations ─────────────────────────────────────────────────────────────
Router::get('/designations',              'DepartmentController@designations');
Router::post('/designations/save',        'DepartmentController@saveDesignation');
Router::post('/designations/{id}/delete', 'DepartmentController@deleteDesignation');

// ─── Attendance ───────────────────────────────────────────────────────────────
Router::get('/attendance',                    'AttendanceController@index');
Router::get('/attendance/process',            'AttendanceController@process');
Router::post('/attendance/process',           'AttendanceController@process');
Router::get('/attendance/employee/{id}',      'AttendanceController@employeeAttendance');
Router::post('/attendance/manual',            'AttendanceController@manualPunch');
Router::post('/attendance/record/{id}/edit',  'AttendanceController@editRecord');
Router::post('/attendance/record/{id}/delete','AttendanceController@deleteRecord');
Router::get('/attendance/export',             'AttendanceController@export');
Router::get('/attendance/overtime',           'AttendanceController@overtime');
Router::post('/attendance/overtime/approve/{id}','AttendanceController@approveOvertime');

// ─── Shifts & Roster ──────────────────────────────────────────────────────────
Router::get('/shifts',                     'ShiftController@index');
Router::post('/shifts/save',               'ShiftController@save');
Router::post('/shifts/{id}/delete',        'ShiftController@delete');
Router::get('/shifts/roster',              'ShiftController@roster');
Router::post('/shifts/roster/assign',      'ShiftController@assign');
Router::post('/shifts/roster/unassign',    'ShiftController@unassign');
Router::get('/shifts/change-requests',     'ShiftController@changeRequests');
Router::post('/shifts/change-requests/{id}/approve','ShiftController@approveChangeRequest');

// ─── Devices ──────────────────────────────────────────────────────────────────
Router::get('/devices',                  'DeviceController@index');
Router::post('/devices/save',            'DeviceController@save');
Router::post('/devices/{id}/delete',     'DeviceController@delete');
Router::post('/devices/{id}/sync',       'DeviceController@sync');
Router::post('/devices/{id}/toggle',     'DeviceController@toggle');
Router::get('/devices/{id}/logs',        'DeviceController@logs');
Router::get('/devices/{id}/info',        'DeviceController@info');

// ─── Leave ────────────────────────────────────────────────────────────────────
Router::get('/leave',                        'LeaveController@index');
Router::get('/leave/apply',                  'LeaveController@apply');
Router::post('/leave/apply',                 'LeaveController@apply');
Router::get('/leave/{id}',                   'LeaveController@show');
Router::post('/leave/{id}/approve',          'LeaveController@approve');
Router::post('/leave/{id}/reject',           'LeaveController@reject');
Router::post('/leave/{id}/cancel',           'LeaveController@cancel');
Router::get('/leave/manage',                 'LeaveController@manage');
Router::post('/leave/types/save',            'LeaveController@saveType');
Router::post('/leave/types/{id}/delete',     'LeaveController@deleteType');
Router::get('/leave/holidays',               'LeaveController@holidays');
Router::post('/leave/holidays/save',         'LeaveController@saveHoliday');
Router::post('/leave/holidays/{id}/delete',  'LeaveController@deleteHoliday');
Router::post('/leave/balance/allocate',      'LeaveController@allocateBalance');
Router::get('/leave/export',                 'LeaveController@export');

// ─── Reports ──────────────────────────────────────────────────────────────────
Router::get('/reports',                      'ReportController@index');
Router::get('/reports/attendance',           'ReportController@attendance');
Router::get('/reports/attendance/export',    'ReportController@exportAttendance');
Router::get('/reports/leave',                'ReportController@leave');
Router::get('/reports/leave/export',         'ReportController@exportLeave');
Router::get('/reports/payroll',              'ReportController@payroll');
Router::get('/reports/payroll/export',       'ReportController@exportPayroll');
Router::get('/reports/overtime',             'ReportController@overtime');
Router::get('/reports/overtime/export',      'ReportController@exportOvertime');
Router::get('/reports/employees',            'ReportController@employees');
Router::get('/reports/employees/export',     'ReportController@exportEmployees');
Router::get('/reports/late-arrivals',        'ReportController@lateArrivals');
Router::get('/reports/late-arrivals/export', 'ReportController@exportLateArrivals');

// ─── Notifications ────────────────────────────────────────────────────────────
Router::get('/notifications',                    'NotificationController@index');
Router::post('/notifications/{id}/read',         'NotificationController@markRead');
Router::post('/notifications/read-all',          'NotificationController@markAllRead');
Router::post('/notifications/{id}/delete',       'NotificationController@delete');
Router::post('/notifications/delete-all-read',   'NotificationController@deleteAllRead');
