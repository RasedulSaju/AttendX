<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= \App\Core\CSRF::generate() ?>">
  <title><?= htmlspecialchars($title ?? 'AttendX') ?> – AttendX</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#6366f1">

  <style>
    :root {
      --primary:       #6366f1;
      --primary-dark:  #4f46e5;
      --sidebar-bg:    #0d0f1a;
      --sidebar-width: 240px;
      --card-bg:       #111827;
      --card-border:   rgba(255,255,255,0.06);
      --body-bg:       #0a0c14;
      --text-muted:    #6b7280;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--body-bg);
      color: #e2e8f0;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── Sidebar ── */
    #sidebar {
      position: fixed; top: 0; left: 0; bottom: 0;
      width: var(--sidebar-width);
      background: var(--sidebar-bg);
      border-right: 1px solid var(--card-border);
      overflow-y: auto; overflow-x: hidden;
      z-index: 1040;
      transition: transform .25s ease;
    }
    #sidebar .brand {
      padding: 20px 20px 16px;
      border-bottom: 1px solid var(--card-border);
    }
    #sidebar .brand-logo {
      font-size: 1.35rem; font-weight: 700;
      color: var(--primary); letter-spacing: -0.5px;
    }
    #sidebar .brand-logo span { color: #e2e8f0; }
    #sidebar .nav-section {
      padding: 18px 12px 4px;
      font-size: .68rem; font-weight: 600;
      text-transform: uppercase; letter-spacing: .08em;
      color: var(--text-muted);
    }
    #sidebar .nav-link {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 16px; margin: 1px 8px;
      border-radius: 8px; font-size: .875rem;
      color: #9ca3af; transition: all .15s;
      text-decoration: none;
    }
    #sidebar .nav-link:hover { background: rgba(99,102,241,.12); color: #e2e8f0; }
    #sidebar .nav-link.active { background: rgba(99,102,241,.2); color: var(--primary); font-weight: 500; }
    #sidebar .nav-link i { font-size: 1rem; width: 18px; text-align: center; }

    /* ── Main content ── */
    #main-content {
      margin-left: var(--sidebar-width);
      min-height: 100vh;
      display: flex; flex-direction: column;
    }

    /* ── Top navbar ── */
    #topbar {
      position: sticky; top: 0; z-index: 1030;
      background: rgba(10,12,20,.9);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--card-border);
      padding: 0 24px;
      height: 56px;
      display: flex; align-items: center; justify-content: space-between;
    }

    /* ── Cards ── */
    .card-dark {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 12px;
    }

    /* ── Forms ── */
    .form-control-dark, .form-select-dark {
      background: rgba(255,255,255,.05);
      border: 1px solid var(--card-border);
      color: #e2e8f0;
      border-radius: 8px;
    }
    .form-control-dark:focus, .form-select-dark:focus {
      background: rgba(255,255,255,.08);
      border-color: var(--primary);
      color: #e2e8f0;
      box-shadow: 0 0 0 3px rgba(99,102,241,.2);
    }
    .form-control-dark::placeholder { color: #4b5563; }

    /* ── Buttons ── */
    .btn-primary { background: var(--primary); border-color: var(--primary); }
    .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
    .btn-xs { padding: 3px 8px; font-size: .75rem; border-radius: 6px; }

    /* ── Tables ── */
    .table-dark { --bs-table-bg: transparent; }
    .table-dark th { font-size: .78rem; font-weight: 500; border-bottom-color: var(--card-border) !important; }
    .table-dark td { border-bottom-color: var(--card-border) !important; vertical-align: middle; }
    .table-hover tbody tr:hover { --bs-table-bg-state: rgba(255,255,255,.03); }

    /* ── Progress ── */
    .progress { background: rgba(255,255,255,.08); }

    /* ── Badges ── */
    .badge { font-weight: 500; }

    /* ── Pagination ── */
    .page-link { background: rgba(255,255,255,.05); border-color: var(--card-border); color: #9ca3af; }
    .page-link:hover { background: rgba(99,102,241,.15); color: var(--primary); border-color: var(--primary); }
    .page-item.active .page-link { background: var(--primary); border-color: var(--primary); }

    /* ── Modals ── */
    .modal-content.bg-dark { background: #111827 !important; border: 1px solid var(--card-border); }
    .modal-backdrop { backdrop-filter: blur(4px); }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.2); }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      #sidebar { transform: translateX(-100%); }
      #sidebar.show { transform: translateX(0); }
      #main-content { margin-left: 0; }
    }

    .x-small { font-size: .72rem; }
    code { color: #a78bfa; background: rgba(167,139,250,.1); padding: 2px 6px; border-radius: 4px; }
  </style>
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────────────────────────── -->
<nav id="sidebar">
  <div class="brand">
    <div class="brand-logo">Attend<span>X</span></div>
    <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px">Enterprise Attendance System</div>
  </div>

  <?php
    $currentPath = \App\Core\Request::path();
    $user        = \App\Core\Auth::user();
    $active      = fn(string $path) => str_starts_with($currentPath, $path) ? 'active' : '';
    $can         = fn(string $perm) => \App\Core\Auth::can($perm);
  ?>

  <div class="pt-2 pb-3">

    <div class="nav-section">Main</div>
    <a href="/dashboard" class="nav-link <?= $currentPath === '/dashboard' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <?php if ($can('employees')): ?>
    <div class="nav-section">Workforce</div>
    <a href="/employees" class="nav-link <?= $active('/employees') ?>">
      <i class="bi bi-people"></i> Employees
    </a>
    <a href="/departments" class="nav-link <?= $active('/departments') ?>">
      <i class="bi bi-building"></i> Departments
    </a>
    <?php endif; ?>

    <?php if ($can('attendance')): ?>
    <div class="nav-section">Attendance</div>
    <a href="/attendance" class="nav-link <?= $active('/attendance') ?>">
      <i class="bi bi-clock-history"></i> Attendance
    </a>
    <a href="/shifts" class="nav-link <?= $active('/shifts') ?>">
      <i class="bi bi-calendar3"></i> Shifts & Roster
    </a>
    <a href="/devices" class="nav-link <?= $active('/devices') ?>">
      <i class="bi bi-hdd-network"></i> Devices
    </a>
    <?php endif; ?>

    <?php if ($can('leave')): ?>
    <a href="/leave" class="nav-link <?= $active('/leave') ?>">
      <i class="bi bi-calendar-x"></i> Leave
    </a>
    <?php endif; ?>

    <?php if ($can('payroll')): ?>
    <div class="nav-section">Finance</div>
    <a href="/payroll" class="nav-link <?= $active('/payroll') ?>">
      <i class="bi bi-cash-stack"></i> Payroll
    </a>
    <?php endif; ?>

    <?php if ($can('access_control')): ?>
    <div class="nav-section">Security</div>
    <a href="/access-control" class="nav-link <?= $active('/access-control') ?>">
      <i class="bi bi-shield-lock"></i> Access Control
    </a>
    <?php endif; ?>

    <?php if ($can('reports')): ?>
    <div class="nav-section">Insights</div>
    <a href="/reports" class="nav-link <?= $active('/reports') ?>">
      <i class="bi bi-file-bar-graph"></i> Reports
    </a>
    <a href="/analytics" class="nav-link <?= $active('/analytics') ?>">
      <i class="bi bi-graph-up-arrow"></i> Analytics
    </a>
    <?php endif; ?>

    <?php if ($can('system')): ?>
    <div class="nav-section">System</div>
    <a href="/system/settings" class="nav-link <?= $active('/system/settings') ?>">
      <i class="bi bi-gear"></i> Settings
    </a>
    <a href="/system/users" class="nav-link <?= $active('/system/users') ?>">
      <i class="bi bi-person-gear"></i> Users
    </a>
    <a href="/system/audit" class="nav-link <?= $active('/system/audit') ?>">
      <i class="bi bi-journal-check"></i> Audit Logs
    </a>
    <a href="/system/backup" class="nav-link <?= $active('/system/backup') ?>">
      <i class="bi bi-cloud-arrow-up"></i> Backup
    </a>
    <?php endif; ?>

  </div>
</nav>

<!-- ── Main Content ─────────────────────────────────────────────────────────── -->
<div id="main-content">

  <!-- Top Bar -->
  <div id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-link text-secondary d-md-none p-0" onclick="toggleSidebar()">
        <i class="bi bi-list fs-5"></i>
      </button>
      <h6 class="mb-0 text-white fw-semibold d-none d-md-block" id="pageTitle">
        <?= htmlspecialchars($title ?? '') ?>
      </h6>
    </div>
    <div class="d-flex align-items-center gap-3">
      <!-- Notifications -->
      <a href="#" class="text-secondary position-relative" id="notifBtn" title="Notifications">
        <i class="bi bi-bell fs-5"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifCount" style="display:none;font-size:.6rem"></span>
      </a>
      <!-- User Menu -->
      <div class="dropdown">
        <button class="btn btn-sm btn-link text-secondary d-flex align-items-center gap-2 p-0 text-decoration-none" data-bs-toggle="dropdown">
          <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center fw-semibold text-white"
               style="width:32px;height:32px;font-size:.8rem">
            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
          </div>
          <span class="text-white small d-none d-md-inline"><?= htmlspecialchars($user['name'] ?? '') ?></span>
          <i class="bi bi-chevron-down small text-secondary d-none d-md-inline"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark border-secondary mt-2">
          <li><h6 class="dropdown-header"><?= htmlspecialchars($user['email'] ?? '') ?></h6></li>
          <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
          <li><a class="dropdown-item" href="/change-password"><i class="bi bi-key me-2"></i>Change Password</a></li>
          <li><hr class="dropdown-divider border-secondary"></li>
          <li>
            <form action="/logout" method="POST" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= \App\Core\CSRF::generate() ?>">
              <button type="submit" class="dropdown-item text-danger">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Flash Messages -->
  <?php if (!empty($_SESSION['_flash'])): ?>
  <div class="px-4 pt-3" id="flashMessages">
    <?php foreach (['success'=>'success','error'=>'danger','info'=>'info','warning'=>'warning'] as $type => $cls): ?>
      <?php if (!empty($_SESSION['_flash'][$type])): ?>
      <div class="alert alert-<?= $cls ?> alert-dismissible fade show d-flex align-items-center gap-2 py-2" role="alert">
        <i class="bi bi-<?= match($cls) { 'success'=>'check-circle', 'danger'=>'x-circle', 'info'=>'info-circle', default=>'exclamation-triangle' } ?>"></i>
        <?= htmlspecialchars($_SESSION['_flash'][$type]) ?>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['_flash'][$type]); ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Page Content -->
  <div class="flex-grow-1 py-4">
    <?= $this->section('content') ?>
  </div>

  <!-- Footer -->
  <footer class="text-center text-secondary py-3 border-top border-secondary" style="font-size:.75rem">
    AttendX &copy; <?= date('Y') ?> &nbsp;|&nbsp;
    v1.0.0 &nbsp;|&nbsp;
    <?php if (defined('APP_START')): ?>
    <?= round((microtime(true) - APP_START) * 1000) ?>ms
    <?php endif; ?>
  </footer>

</div><!-- /main-content -->

<!-- Mobile sidebar overlay -->
<div id="sidebarOverlay" class="d-md-none" style="display:none!important;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1039" onclick="toggleSidebar()"></div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  sb.classList.toggle('show');
  ov.style.display = sb.classList.contains('show') ? 'block' : 'none';
}

// Auto-dismiss flash messages
document.querySelectorAll('#flashMessages .alert').forEach(el => {
  setTimeout(() => el.classList.remove('show'), 5000);
});

// Set CSRF token for all AJAX requests
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
const _fetch = window.fetch;
window.fetch = function(url, opts = {}) {
  if (opts.method && opts.method.toUpperCase() !== 'GET') {
    opts.headers = opts.headers || {};
    if (typeof opts.headers === 'object' && !opts.headers['X-CSRF-TOKEN']) {
      opts.headers['X-CSRF-TOKEN'] = CSRF_TOKEN;
    }
  }
  return _fetch(url, opts);
};

// PWA service worker
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}

// Notification badge
async function loadNotifCount() {
  try {
    const r = await _fetch('/mobile/notifications');
    if (!r.ok) return;
    const d = await r.json();
    const badge = document.getElementById('notifCount');
    if (d.unread > 0) {
      badge.textContent = d.unread > 99 ? '99+' : d.unread;
      badge.style.display = '';
    }
  } catch(_) {}
}
loadNotifCount();
</script>

</body>
</html>
