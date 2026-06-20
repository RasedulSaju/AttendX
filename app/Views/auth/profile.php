<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">My Profile</h4>
    <a href="/change-password" class="btn btn-outline-secondary btn-sm"><i class="bi bi-key me-1"></i>Change Password</a>
  </div>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card card-dark border-0 text-center p-4">
        <?php $photo = $employee['profile_photo'] ?? null; ?>
        <div class="mb-3">
          <?php if ($photo && file_exists(base_path($photo))): ?>
          <img src="/<?= htmlspecialchars($photo) ?>" class="rounded-circle" style="width:90px;height:90px;object-fit:cover">
          <?php else: ?>
          <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto fw-bold text-white" style="width:90px;height:90px;font-size:2rem">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
          </div>
          <?php endif; ?>
        </div>
        <h5 class="text-white mb-1"><?= htmlspecialchars($user['name']) ?></h5>
        <p class="text-secondary small mb-0"><?= htmlspecialchars($user['email']) ?></p>
        <?php if ($employee): ?>
        <hr class="border-secondary">
        <p class="text-secondary small mb-1"><?= htmlspecialchars($employee['designation_name'] ?? '') ?></p>
        <p class="text-secondary small mb-0"><?= htmlspecialchars($employee['department_name'] ?? '') ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Update Profile</h6></div>
        <div class="card-body">
          <form action="/profile" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-secondary">Full Name *</label>
                <input type="text" name="name" class="form-control form-control-dark" value="<?= htmlspecialchars($user['name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label text-secondary">Email</label>
                <input type="email" class="form-control form-control-dark" value="<?= htmlspecialchars($user['email']) ?>" disabled>
              </div>
              <?php if ($employee): ?>
              <div class="col-md-6">
                <label class="form-label text-secondary">Phone</label>
                <input type="text" name="phone" class="form-control form-control-dark" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>">
              </div>
              <?php endif; ?>
              <div class="col-md-6">
                <label class="form-label text-secondary">Profile Photo</label>
                <input type="file" name="photo" class="form-control form-control-dark" accept="image/jpeg,image/png,image/webp">
                <small class="text-secondary">JPG/PNG/WebP, max 2MB</small>
              </div>
            </div>
            <div class="mt-4">
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $this->stop() ?>
