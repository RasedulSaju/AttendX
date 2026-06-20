<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Change Password</h4>
    <a href="/profile" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
  </div>
  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="card card-dark border-0">
        <div class="card-body">
          <form action="/change-password" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="mb-3">
              <label class="form-label text-secondary">Current Password *</label>
              <input type="password" name="current_password" class="form-control form-control-dark" required autocomplete="current-password">
            </div>
            <div class="mb-3">
              <label class="form-label text-secondary">New Password *</label>
              <input type="password" name="new_password" id="newPwd" class="form-control form-control-dark" required minlength="8" autocomplete="new-password">
              <small class="text-secondary">Minimum 8 characters</small>
            </div>
            <div class="mb-4">
              <label class="form-label text-secondary">Confirm New Password *</label>
              <input type="password" name="confirm_password" id="confirmPwd" class="form-control form-control-dark" required autocomplete="new-password">
              <div id="matchMsg" class="small mt-1"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-key me-1"></i>Update Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('confirmPwd').addEventListener('input', function() {
  const match = this.value === document.getElementById('newPwd').value;
  document.getElementById('matchMsg').innerHTML = this.value
    ? (match ? '<span class="text-success">Passwords match</span>' : '<span class="text-danger">Passwords do not match</span>')
    : '';
});
</script>
<?php $this->stop() ?>
