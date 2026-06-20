<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">System Settings</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item active text-secondary">Settings</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <a href="/system/info"  class="btn btn-outline-secondary btn-sm"><i class="bi bi-info-circle me-1"></i>System Info</a>
      <a href="/system/users" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people me-1"></i>Users</a>
      <a href="/system/roles" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield me-1"></i>Roles</a>
    </div>
  </div>

  <div class="row g-4">
    <!-- Left Nav -->
    <div class="col-lg-3">
      <div class="card card-dark border-0">
        <div class="card-body p-0">
          <nav class="nav flex-column nav-pills" id="settingsNav">
            <a class="nav-link active rounded-0 py-3 px-4" href="#general"   data-tab>General</a>
            <a class="nav-link rounded-0 py-3 px-4"        href="#company"   data-tab>Company</a>
            <a class="nav-link rounded-0 py-3 px-4"        href="#attendance" data-tab>Attendance</a>
            <a class="nav-link rounded-0 py-3 px-4"        href="#email"     data-tab>Email / SMTP</a>
            <a class="nav-link rounded-0 py-3 px-4"        href="#security"  data-tab>Security</a>
            <a class="nav-link rounded-0 py-3 px-4"        href="#integrations" data-tab>Integrations</a>
          </nav>
        </div>
      </div>
    </div>

    <!-- Right Content -->
    <div class="col-lg-9">
      <form method="POST" action="/system/settings" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

        <?php
          $get = fn(string $group, string $key, string $default = '') =>
            htmlspecialchars($settings[$group][$key]['value'] ?? $default);
        ?>

        <!-- General -->
        <div class="settings-tab" id="general">
          <div class="card card-dark border-0 mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary">
              <h6 class="text-white mb-0">General Settings</h6>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label text-secondary">Application Name</label>
                  <input type="text" name="app.name" class="form-control form-control-dark" value="<?= $get('app','app.name','AttendX') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">Timezone</label>
                  <select name="app.timezone" class="form-select form-select-dark">
                    <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                    <option value="<?= $tz ?>" <?= $get('app','app.timezone','Asia/Dhaka') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Date Format</label>
                  <select name="app.date_format" class="form-select form-select-dark">
                    <?php
                      $fmts = ['d/m/Y','m/d/Y','Y-m-d','d-M-Y','d.m.Y'];
                      $cur  = $get('app','app.date_format','d/m/Y');
                    ?>
                    <?php foreach ($fmts as $f): ?>
                    <option value="<?= $f ?>" <?= $cur === $f ? 'selected' : '' ?>><?= date($f) ?> (<?= $f ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Time Format</label>
                  <select name="app.time_format" class="form-select form-select-dark">
                    <option value="H:i" <?= $get('app','app.time_format','H:i') === 'H:i' ? 'selected' : '' ?>>24-hour (<?= date('H:i') ?>)</option>
                    <option value="h:i A" <?= $get('app','app.time_format','H:i') === 'h:i A' ? 'selected' : '' ?>>12-hour (<?= date('h:i A') ?>)</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Currency Symbol</label>
                  <input type="text" name="app.currency" class="form-control form-control-dark" value="<?= $get('app','app.currency','৳') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">Week Start Day</label>
                  <select name="app.week_start" class="form-select form-select-dark">
                    <?php foreach (['0'=>'Sunday','1'=>'Monday','6'=>'Saturday'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $get('app','app.week_start','0') === (string)$val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">Language</label>
                  <select name="app.locale" class="form-select form-select-dark">
                    <option value="en" <?= $get('app','app.locale','en') === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="bn" <?= $get('app','app.locale','en') === 'bn' ? 'selected' : '' ?>>Bengali</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Company -->
        <div class="settings-tab d-none" id="company">
          <div class="card card-dark border-0 mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary">
              <h6 class="text-white mb-0">Company Information</h6>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label text-secondary">Company Name</label>
                  <input type="text" name="company.name" class="form-control form-control-dark" value="<?= $get('company','company.name') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Registration No.</label>
                  <input type="text" name="company.reg_no" class="form-control form-control-dark" value="<?= $get('company','company.reg_no') ?>">
                </div>
                <div class="col-12">
                  <label class="form-label text-secondary">Address</label>
                  <textarea name="company.address" class="form-control form-control-dark" rows="2"><?= $get('company','company.address') ?></textarea>
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Phone</label>
                  <input type="text" name="company.phone" class="form-control form-control-dark" value="<?= $get('company','company.phone') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Email</label>
                  <input type="email" name="company.email" class="form-control form-control-dark" value="<?= $get('company','company.email') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Website</label>
                  <input type="url" name="company.website" class="form-control form-control-dark" value="<?= $get('company','company.website') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">Logo</label>
                  <input type="file" name="company_logo" class="form-control form-control-dark" accept="image/png,image/jpeg,image/svg+xml">
                  <small class="text-secondary">PNG/JPG/SVG, max 2MB</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Attendance -->
        <div class="settings-tab d-none" id="attendance">
          <div class="card card-dark border-0 mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary">
              <h6 class="text-white mb-0">Attendance Rules</h6>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label text-secondary">Late Grace (minutes)</label>
                  <input type="number" name="attendance.late_grace_minutes" class="form-control form-control-dark" min="0" max="120"
                         value="<?= $get('attendance','attendance.late_grace_minutes','15') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Early Leave Grace (min)</label>
                  <input type="number" name="attendance.early_leave_grace_minutes" class="form-control form-control-dark" min="0" max="60"
                         value="<?= $get('attendance','attendance.early_leave_grace_minutes','10') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">OT Threshold (minutes)</label>
                  <input type="number" name="attendance.ot_threshold_minutes" class="form-control form-control-dark" min="0"
                         value="<?= $get('attendance','attendance.ot_threshold_minutes','30') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Min Work Hours</label>
                  <input type="number" name="attendance.min_work_hours" class="form-control form-control-dark" step="0.5" min="1" max="12"
                         value="<?= $get('attendance','attendance.min_work_hours','8') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Half Day Hours</label>
                  <input type="number" name="attendance.half_day_hours" class="form-control form-control-dark" step="0.5" min="1" max="6"
                         value="<?= $get('attendance','attendance.half_day_hours','4') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Auto-process Time</label>
                  <input type="time" name="attendance.auto_process_time" class="form-control form-control-dark"
                         value="<?= $get('attendance','attendance.auto_process_time','23:59') ?>">
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="attendance.auto_process" id="autoProcess" value="1"
                           <?= $get('attendance','attendance.auto_process','1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label text-secondary" for="autoProcess">Auto-process attendance daily</label>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="attendance.mark_absent_if_no_punch" id="markAbsent" value="1"
                           <?= $get('attendance','attendance.mark_absent_if_no_punch','1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label text-secondary" for="markAbsent">Mark absent if no punch recorded</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Email -->
        <div class="settings-tab d-none" id="email">
          <div class="card card-dark border-0 mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary">
              <h6 class="text-white mb-0">Email / SMTP Configuration</h6>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label text-secondary">SMTP Host</label>
                  <input type="text" name="mail.host" class="form-control form-control-dark" value="<?= $get('mail','mail.host','smtp.gmail.com') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label text-secondary">Port</label>
                  <input type="number" name="mail.port" class="form-control form-control-dark" value="<?= $get('mail','mail.port','587') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label text-secondary">Encryption</label>
                  <select name="mail.encryption" class="form-select form-select-dark">
                    <option value="tls" <?= $get('mail','mail.encryption','tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= $get('mail','mail.encryption','tls') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value=""    <?= $get('mail','mail.encryption','tls') === '' ? 'selected' : '' ?>>None</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">Username</label>
                  <input type="text" name="mail.username" class="form-control form-control-dark" value="<?= $get('mail','mail.username') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">Password</label>
                  <input type="password" name="mail.password" class="form-control form-control-dark" placeholder="(unchanged)">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">From Name</label>
                  <input type="text" name="mail.from_name" class="form-control form-control-dark" value="<?= $get('mail','mail.from_name','AttendX') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">From Email</label>
                  <input type="email" name="mail.from_email" class="form-control form-control-dark" value="<?= $get('mail','mail.from_email') ?>">
                </div>
                <div class="col-12">
                  <div class="d-flex gap-2 align-items-center">
                    <input type="email" id="testEmailAddr" class="form-control form-control-dark" style="max-width:260px" placeholder="test@example.com">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="sendTestEmail()">
                      <i class="bi bi-send me-1"></i>Send Test Email
                    </button>
                    <span id="testResult" class="small"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Security -->
        <div class="settings-tab d-none" id="security">
          <div class="card card-dark border-0 mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary">
              <h6 class="text-white mb-0">Security Settings</h6>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label text-secondary">Session Timeout (minutes)</label>
                  <input type="number" name="security.session_timeout" class="form-control form-control-dark" min="5" max="1440"
                         value="<?= $get('security','security.session_timeout','120') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Max Login Attempts</label>
                  <input type="number" name="security.max_login_attempts" class="form-control form-control-dark" min="3" max="20"
                         value="<?= $get('security','security.max_login_attempts','5') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Lockout Duration (minutes)</label>
                  <input type="number" name="security.lockout_duration" class="form-control form-control-dark" min="1"
                         value="<?= $get('security','security.lockout_duration','30') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">Min Password Length</label>
                  <input type="number" name="security.min_password_length" class="form-control form-control-dark" min="6" max="32"
                         value="<?= $get('security','security.min_password_length','8') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">JWT Token Expiry (seconds)</label>
                  <input type="number" name="security.jwt_ttl" class="form-control form-control-dark"
                         value="<?= $get('security','security.jwt_ttl','86400') ?>">
                </div>
                <div class="col-12 mt-2">
                  <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="security.require_2fa" value="1"
                           <?= $get('security','security.require_2fa','0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label text-secondary">Require 2FA for admin users</label>
                  </div>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="security.log_all_access" value="1"
                           <?= $get('security','security.log_all_access','1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label text-secondary">Log all page access in audit trail</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Integrations -->
        <div class="settings-tab d-none" id="integrations">
          <div class="card card-dark border-0 mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary">
              <h6 class="text-white mb-0">Device Integration</h6>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label text-secondary">ADMS Push Port (Primary)</label>
                  <input type="number" name="device.adms_port" class="form-control form-control-dark"
                         value="<?= $get('device','device.adms_port','8080') ?>">
                  <small class="text-secondary">Port 8080 (Granding F09 default)</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">ADMS Push Port (Secondary)</label>
                  <input type="number" name="device.adms_port_secondary" class="form-control form-control-dark"
                         value="<?= $get('device','device.adms_port_secondary','8081') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-secondary">SDK Pull Port</label>
                  <input type="number" name="device.sdk_port" class="form-control form-control-dark"
                         value="<?= $get('device','device.sdk_port','4370') ?>">
                  <small class="text-secondary">ZKTeco/Granding SDK port</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label text-secondary">Sync Interval (seconds)</label>
                  <input type="number" name="device.sync_interval" class="form-control form-control-dark" min="10"
                         value="<?= $get('device','device.sync_interval','300') ?>">
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="device.auto_sync" value="1"
                           <?= $get('device','device.auto_sync','1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label text-secondary">Enable automatic device sync</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Save Button -->
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary px-5">
            <i class="bi bi-save me-1"></i>Save Settings
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';

// Tab navigation
document.querySelectorAll('[data-tab]').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    const target = link.getAttribute('href').substring(1);

    document.querySelectorAll('[data-tab]').forEach(l => l.classList.remove('active'));
    link.classList.add('active');

    document.querySelectorAll('.settings-tab').forEach(t => t.classList.add('d-none'));
    document.getElementById(target)?.classList.remove('d-none');
  });
});

async function sendTestEmail() {
  const email = document.getElementById('testEmailAddr').value.trim();
  const result = document.getElementById('testResult');
  if (!email) { result.textContent = 'Enter email first.'; result.className = 'small text-warning'; return; }

  result.textContent = 'Sending…'; result.className = 'small text-secondary';

  const r = await fetch('/system/test-email', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ csrf_token: CSRF, email }),
  });
  const json = await r.json();
  result.textContent = json.message || (json.success ? 'Sent!' : 'Error');
  result.className = 'small text-' + (json.success ? 'success' : 'danger');
}
</script>
<?php $this->stop() ?>
