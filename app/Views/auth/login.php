<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – AttendX</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background:#0a0c14; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Inter',system-ui,sans-serif; }
    .login-card { background:#111827; border:1px solid rgba(255,255,255,.07); border-radius:16px; width:100%; max-width:420px; padding:40px; }
    .brand { font-size:1.8rem; font-weight:700; color:#6366f1; letter-spacing:-1px; }
    .brand span { color:#e2e8f0; }
    .form-control { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); color:#e2e8f0; border-radius:8px; padding:10px 14px; }
    .form-control:focus { background:rgba(255,255,255,.08); border-color:#6366f1; color:#e2e8f0; box-shadow:0 0 0 3px rgba(99,102,241,.2); }
    .form-control::placeholder { color:#4b5563; }
    .btn-primary { background:#6366f1; border-color:#6366f1; border-radius:8px; padding:10px; font-weight:500; }
    .btn-primary:hover { background:#4f46e5; border-color:#4f46e5; }
    label { color:#9ca3af; font-size:.875rem; margin-bottom:6px; }
    .input-icon { position:relative; }
    .input-icon i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#4b5563; }
    .input-icon .form-control { padding-left:36px; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="text-center mb-4">
    <div class="brand">Attend<span>X</span></div>
    <p class="text-secondary small mt-1">Enterprise Attendance & Access Control</p>
  </div>

  <?php if (!empty($error)): ?>
  <div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-circle"></i>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if (\App\Core\Session::hasFlash('success')): ?>
  <div class="alert alert-success py-2 small"><?= htmlspecialchars(\App\Core\Session::getFlash('success')) ?></div>
  <?php endif; ?>

  <form action="/login" method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

    <div class="mb-3">
      <label for="email">Email Address</label>
      <div class="input-icon">
        <i class="bi bi-envelope"></i>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="admin@attendx.com" autocomplete="email" required autofocus
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
    </div>

    <div class="mb-3">
      <label for="password">Password</label>
      <div class="input-icon">
        <i class="bi bi-lock"></i>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••••" autocomplete="current-password" required>
      </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-4">
      <div class="form-check mb-0">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label text-secondary small" for="remember">Remember me</label>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
    </button>
  </form>

  <p class="text-center text-secondary small mt-4 mb-0">
    AttendX &copy; <?= date('Y') ?> &nbsp;&bull;&nbsp; v1.0.0
  </p>
</div>
</body>
</html>
