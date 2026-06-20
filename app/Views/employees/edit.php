<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Edit Employee</h4>
      <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="/employees" class="text-secondary">Employees</a></li>
        <li class="breadcrumb-item"><a href="/employees/<?= $emp['id'] ?>" class="text-secondary"><?= htmlspecialchars($emp['full_name']) ?></a></li>
        <li class="breadcrumb-item active text-secondary">Edit</li>
      </ol></nav>
    </div>
  </div>

  <form action="/employees/<?= $emp['id'] ?>/edit" method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <div class="row g-4">
      <div class="col-lg-8">
        <!-- Personal Info -->
        <div class="card card-dark border-0 mb-4">
          <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Personal Information</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label text-secondary">Full Name *</label>
                <input type="text" name="full_name" class="form-control form-control-dark" required value="<?= htmlspecialchars($emp['full_name']) ?>"></div>
              <div class="col-md-6"><label class="form-label text-secondary">Employee Code</label>
                <input type="text" class="form-control form-control-dark" value="<?= htmlspecialchars($emp['employee_code']) ?>" disabled></div>
              <div class="col-md-6"><label class="form-label text-secondary">Email *</label>
                <input type="email" name="email" class="form-control form-control-dark" required value="<?= htmlspecialchars($emp['email']??'') ?>"></div>
              <div class="col-md-6"><label class="form-label text-secondary">Phone</label>
                <input type="text" name="phone" class="form-control form-control-dark" value="<?= htmlspecialchars($emp['phone']??'') ?>"></div>
              <div class="col-md-4"><label class="form-label text-secondary">Gender *</label>
                <select name="gender" class="form-select form-select-dark" required>
                  <?php foreach (['male','female','other'] as $g): ?>
                  <option value="<?= $g ?>" <?= $emp['gender']===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="col-md-4"><label class="form-label text-secondary">Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control form-control-dark" value="<?= $emp['date_of_birth']??'' ?>"></div>
              <div class="col-md-4"><label class="form-label text-secondary">Blood Group</label>
                <select name="blood_group" class="form-select form-select-dark">
                  <option value="">— Select —</option>
                  <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                  <option value="<?= $bg ?>" <?= ($emp['blood_group']??'')===$bg?'selected':'' ?>><?= $bg ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="col-12"><label class="form-label text-secondary">Address</label>
                <textarea name="address" class="form-control form-control-dark" rows="2"><?= htmlspecialchars($emp['address']??'') ?></textarea></div>
              <div class="col-12"><label class="form-label text-secondary">Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control form-control-dark" value="<?= htmlspecialchars($emp['emergency_contact']??'') ?>"></div>
            </div>
          </div>
        </div>
        <!-- Employment Details -->
        <div class="card card-dark border-0">
          <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Employment Details</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label text-secondary">Department</label>
                <select name="department_id" class="form-select form-select-dark">
                  <option value="">— Select —</option>
                  <?php foreach ($depts as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($emp['department_id']==$d['id'])?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="col-md-6"><label class="form-label text-secondary">Designation</label>
                <select name="designation_id" class="form-select form-select-dark">
                  <option value="">— Select —</option>
                  <?php foreach ($designations as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($emp['designation_id']==$d['id'])?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="col-md-4"><label class="form-label text-secondary">Join Date *</label>
                <input type="date" name="join_date" class="form-control form-control-dark" required value="<?= $emp['join_date'] ?>"></div>
              <div class="col-md-4"><label class="form-label text-secondary">Badge Number</label>
                <input type="text" name="badge_number" class="form-control form-control-dark" value="<?= htmlspecialchars($emp['badge_number']??'') ?>"></div>
              <div class="col-md-4"><label class="form-label text-secondary">Status</label>
                <select name="status" class="form-select form-select-dark">
                  <?php foreach (['active','inactive','on_leave','terminated'] as $s): ?>
                  <option value="<?= $s ?>" <?= $emp['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                  <?php endforeach; ?>
                </select></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Photo -->
      <div class="col-lg-4">
        <div class="card card-dark border-0">
          <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Profile Photo</h6></div>
          <div class="card-body text-center">
            <?php if (!empty($emp['profile_photo']) && file_exists(base_path($emp['profile_photo']))): ?>
            <img src="/<?= htmlspecialchars($emp['profile_photo']) ?>" class="rounded-circle mb-3" style="width:90px;height:90px;object-fit:cover" id="photoPreview">
            <?php else: ?>
            <div class="rounded-circle bg-primary bg-opacity-15 d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold text-primary" style="width:90px;height:90px;font-size:2rem" id="photoPreview">
              <?= strtoupper(substr($emp['full_name'],0,1)) ?>
            </div>
            <?php endif; ?>
            <input type="file" name="profile_photo" class="form-control form-control-dark form-control-sm" accept="image/jpeg,image/png,image/webp" onchange="previewPhoto(this)">
            <small class="text-secondary d-block mt-2">Leave blank to keep current</small>
          </div>
        </div>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2">
        <a href="/employees/<?= $emp['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
      </div>
    </div>
  </form>
</div>
<script>
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const el = document.getElementById('photoPreview');
      el.outerHTML = `<img src="${e.target.result}" id="photoPreview" class="rounded-circle mb-3" style="width:90px;height:90px;object-fit:cover">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php $this->stop() ?>
