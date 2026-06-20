<?php
declare(strict_types=1);

use App\Core\Router;

/**
 * AttendX API Routes
 * Prefix: /api/v1
 * Prefix: /mobile (mobile PWA)
 */

// ─── REST API v1 ─────────────────────────────────────────────────────────────

// Auth (public)
Router::post('/api/v1/token', 'ApiController@token');

// Employees
Router::get('/api/v1/employees',         'ApiController@employees');
Router::get('/api/v1/employees/{id}',    'ApiController@employee');

// Attendance
Router::get('/api/v1/attendance',         'ApiController@attendance');
Router::post('/api/v1/attendance/punch',  'ApiController@attendancePunch');

// Leave
Router::get('/api/v1/leave/balance/{employee_id}', 'ApiController@leaveBalance');
Router::get('/api/v1/leave/applications',           'ApiController@leaveApplications');

// Reference data
Router::get('/api/v1/departments',  'ApiController@departments');
Router::get('/api/v1/designations', 'ApiController@designations');
Router::get('/api/v1/devices',      'ApiController@devices');

// Health
Router::get('/api/v1/health', 'ApiController@health');

// ─── Analytics API ────────────────────────────────────────────────────────────

Router::get('/api/v1/analytics/kpi',              'AnalyticsController@kpiData');
Router::get('/api/v1/analytics/attendance-trend',  'AnalyticsController@attendanceTrend');
Router::get('/api/v1/analytics/department',        'AnalyticsController@departmentData');
Router::get('/api/v1/analytics/payroll-trend',     'AnalyticsController@payrollTrend');

// ─── Access Control Live ──────────────────────────────────────────────────────

Router::get('/api/v1/access/live-stats',           'AccessControlController@liveStats');
Router::get('/api/v1/access/employee/{id}',        'AccessControlController@employeeAccess');

// ─── Mobile API ───────────────────────────────────────────────────────────────

// Auth (public)
Router::post('/mobile/login',   'MobileApiController@login');
Router::post('/mobile/refresh', 'MobileApiController@refresh');

// Protected mobile routes
Router::post('/mobile/logout',  'MobileApiController@logout');

Router::get('/mobile/profile',  'MobileApiController@profile');
Router::get('/mobile/dashboard','MobileApiController@dashboardSummary');

// Attendance
Router::get('/mobile/attendance',        'MobileApiController@myAttendance');
Router::get('/mobile/attendance/today',  'MobileApiController@todayStatus');

// Leave
Router::get('/mobile/leave',                 'MobileApiController@myLeave');
Router::post('/mobile/leave/apply',          'MobileApiController@applyLeave');
Router::post('/mobile/leave/cancel/{id}',    'MobileApiController@cancelLeave');

// Payslips
Router::get('/mobile/payslips', 'MobileApiController@myPayslips');

// Notifications
Router::get('/mobile/notifications',                     'MobileApiController@notifications');
Router::post('/mobile/notifications/{id}/read',          'MobileApiController@markNotificationRead');
Router::post('/mobile/notifications/read-all',           'MobileApiController@markAllNotificationsRead');

// Push tokens
Router::post('/mobile/push-token', 'MobileApiController@registerPushToken');

// ─── System API ───────────────────────────────────────────────────────────────

Router::post('/api/v1/system/settings',     'SystemController@saveSetting');
Router::post('/api/v1/system/test-email',   'SystemController@testEmail');
Router::post('/api/v1/system/users/{id}/toggle', 'SystemController@toggleUser');
Router::post('/api/v1/system/users/{id}/delete', 'SystemController@deleteUser');

// ─── Audit API ────────────────────────────────────────────────────────────────

Router::get('/api/v1/audit/stats',       'AuditController@stats');
Router::get('/api/v1/audit/{id}',        'AuditController@detail');
Router::post('/api/v1/audit/purge',      'AuditController@purge');

// ─── Backup API ───────────────────────────────────────────────────────────────

Router::post('/api/v1/backup/create',    'BackupController@create');
Router::post('/api/v1/backup/delete',    'BackupController@delete');
Router::post('/api/v1/backup/restore',   'BackupController@restore');
Router::post('/api/v1/backup/upload',    'BackupController@upload');

// ─── Payroll API ──────────────────────────────────────────────────────────────

Router::post('/api/v1/payroll/adjustment',          'PayrollController@addAdjustment');
Router::post('/api/v1/payroll/adjustment/{id}/approve', 'PayrollController@approveAdjustment');

// ─── Access Control API ───────────────────────────────────────────────────────

Router::post('/api/v1/access/door',             'AccessControlController@saveDoor');
Router::delete('/api/v1/access/rule/{id}',      'AccessControlController@deleteRule');
Router::post('/api/v1/access/schedule',         'AccessControlController@saveSchedule');
Router::post('/api/v1/access/visitor/{id}/checkout', 'AccessControlController@checkoutVisitor');
Router::post('/api/v1/access/visitor/{id}/revoke',   'AccessControlController@revokeVisitor');
