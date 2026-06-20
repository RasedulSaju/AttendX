<?php
declare(strict_types=1);

use App\Core\Router;

/**
 * AttendX Web Routes — Phases 11–14
 * All routes require authentication (enforced in controllers).
 * Include this file from the main routes/routes.php or index.php router bootstrap.
 */

// ─── Payroll ──────────────────────────────────────────────────────────────────

Router::get('/payroll',                         'PayrollController@index');
Router::get('/payroll/create',                  'PayrollController@create');
Router::post('/payroll/create',                 'PayrollController@create');
Router::get('/payroll/process/{id}',            'PayrollController@process');
Router::post('/payroll/process/{id}',           'PayrollController@process');
Router::get('/payroll/view/{id}',               'PayrollController@view');
Router::get('/payroll/slip/{id}',               'PayrollController@slipDetail');
Router::post('/payroll/approve/{id}',           'PayrollController@approve');
Router::post('/payroll/mark-paid/{id}',         'PayrollController@markPaid');
Router::post('/payroll/adjustment',             'PayrollController@addAdjustment');
Router::post('/payroll/adjustment/{id}/approve','PayrollController@approveAdjustment');

// Salary
Router::get('/payroll/salary',                  'PayrollController@assignSalary');
Router::post('/payroll/salary',                 'PayrollController@assignSalary');

// Structures
Router::get('/payroll/structures',              'PayrollController@structures');
Router::post('/payroll/structures/save',        'PayrollController@saveStructure');

// Components
Router::get('/payroll/components',              'PayrollController@components');
Router::post('/payroll/components/save',        'PayrollController@saveComponent');

// ─── Access Control ───────────────────────────────────────────────────────────

Router::get('/access-control',                  'AccessControlController@index');

// Zones
Router::get('/access-control/zones',            'AccessControlController@zones');
Router::post('/access-control/zones',           'AccessControlController@zones');
Router::post('/access-control/zones/delete/{id}','AccessControlController@deleteZone');

// Rules
Router::get('/access-control/rules/{zone_id}',  'AccessControlController@rules');
Router::post('/access-control/rules/{zone_id}', 'AccessControlController@rules');

// Logs
Router::get('/access-control/logs',             'AccessControlController@logs');

// Visitors
Router::get('/access-control/visitors',         'AccessControlController@visitors');
Router::post('/access-control/visitors',        'AccessControlController@visitors');
Router::post('/access-control/visitors/{id}/checkout', 'AccessControlController@checkoutVisitor');
Router::post('/access-control/visitors/{id}/revoke',   'AccessControlController@revokeVisitor');

// ─── Analytics ────────────────────────────────────────────────────────────────

Router::get('/analytics',                       'AnalyticsController@dashboard');
Router::get('/analytics/workforce',             'AnalyticsController@workforce');
Router::get('/analytics/attendance',            'AnalyticsController@attendance');
Router::get('/analytics/leave',                 'AnalyticsController@leave');
Router::get('/analytics/payroll',               'AnalyticsController@payroll');
Router::get('/analytics/devices',               'AnalyticsController@devices');

// ─── System ───────────────────────────────────────────────────────────────────

Router::get('/system/settings',                 'SystemController@settings');
Router::post('/system/settings',                'SystemController@settings');
Router::post('/system/test-email',              'SystemController@testEmail');
Router::get('/system/info',                     'SystemController@info');

// Users
Router::get('/system/users',                    'SystemController@users');
Router::post('/system/users/save',              'SystemController@saveUser');
Router::post('/system/users/{id}/toggle',       'SystemController@toggleUser');
Router::post('/system/users/{id}/delete',       'SystemController@deleteUser');

// Roles
Router::get('/system/roles',                    'SystemController@roles');
Router::post('/system/roles/save',              'SystemController@saveRole');

// Audit
Router::get('/system/audit',                    'AuditController@index');
Router::get('/system/audit/export',             'AuditController@export');

// Backup
Router::get('/system/backup',                   'BackupController@index');
Router::get('/system/backup/download/{filename}','BackupController@download');
