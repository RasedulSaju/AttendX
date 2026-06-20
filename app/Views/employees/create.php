<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Add Employee</h4>
      <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="/employees" class="text-secondary">Employees</a></li>
        <li class="breadcrumb-item active text-secondary">Add New</li>
      </ol></nav>
    </div>
  </div>
  <form action="/employees/create" method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <div class="row g-4">
      <!-- Personal Info -->
      <div class="col-lg-8">
        <div class="card card-dark border-0 mb-4">
          <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Personal Information</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label text-secondary">Full Name *</label><input type="text" name="full_name" class="form-control form-control-dark" required value="<?= htmlspecialchars($_POST['full_name']??'') ?>"></div>
              <div class="col-md-6"><label class="form-label text-secondary">Employee Code</label><input type="text" name="employee_code" class="form-control form-control-dark" placeholder="Auto-generated if blank" value="<?= htmlspecialchars($_POST['employee_code']??'') ?>"></div>
              <div class="col-md-6"><label class="form-label text-secondary">Email *</label><input type="email" name="email" class="form-control form-control-dark" required value="<?= htmlspecialchars($_POST['email']??'') ?>"></div>
              <div class="col-md-6"><label class="form-label text-secondary">Phone</label><input type="text" name="phone" class="form-control form-control-dark" value="<?= htmlspecialchars($_POST['phone']??'') ?>"></div>
              <div class="col-md-4"><label class="form-label text-secondary">Gender *</label>
                <select name="gender" class="form-select form-select-dark" required>
                  <option value="male" <?= ($_POST['gender']??'')==='male'?'selected':'' ?>>Male</option>
                  <option value="female" <?= ($_POST['gender']??'')==='female'?'selected':'' ?>>Female</option>
                  <option value="other" <?= ($_POST['gender']??'')==='other'?'selected':'' ?>>Other</option>
                </select>
              </div>
              <div class="col-md-4"><label class="form-label text-secondary">Date of Birth</label><input type="date" name="date_of_birth" class="form-control form-control-dark" value="<?= htmlspecialchars($_POST['date_of_birth']??'') ?>"></div>
              <div class="col-md-4"><label class="form-label text-secondary">Blood Group</label>
                <select name="blood_group" class="form-select form-select-dark">
                  <option value="">— Select —</option>
                  <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                  <option value="<?= $bg ?>" <?= ($_POST['blood_group']??'')===$bg?'selected':'' ?>><?= $bg ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12"><label class="form-label text-secondary">Address</label><textarea name="address" class="form-control form-control-dark" rows="2"><?= htmlspecialchars($_POST['address']??'') ?></textarea></div>
              <div class="col-12"><label class="form-label text-secondary">Emergency Contact</label><input type="text" name="emergency_contact" class="form-control form-control-dark" placeholder="Name & Phone" value="<?= htmlspecialchars($_POST['emergency_contact']??'') ?>"></div>
            </div>
          </div>
        </div>
        <div class="card card-dark border-0">
          <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Employment Details</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label text-secondary">Department</label>
                <select name="department_id" class="form-select form-select-dark">
                  <option value="">— Select —</option>
                  <?php foreach ($depts as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($_POST['department_id']??'')==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6"><label class="form-label text-secondary">Designation</label>
                <select name="designation_id" class="form-select form-select-dark">
                  <option value="">— Select —</option>
                  <?php foreach ($designations as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($_POST['designation_id']??'')==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4"><label class="form-label text-secondary">Join Date *</label><input type="date" name="join_date" class="form-control form-control-dark" required value="<?= htmlspecialchars($_POST['join_date']??date('Y-m-d')) ?>"></div>
              <div class="col-md-4"><label class="form-label text-secondary">Badge Number</label><input type="text" name="badge_number" class="form-control form-control-dark" value="<?= htmlspecialchars($_POST['badge_number']??'') ?>"></div>
              <div class="col-md-4"><label class="form-label text-secondary">Default Shift</label>
                <select name="shift_id" class="form-select form-select-dark">
                  <option value="">— None —</option>
                  <?php foreach ($shifts as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Photo -->
      <div class="col-lg-4">
        <div class="card card-dark border-0">
          <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Profile Photo</h6></div>
          <div class="card-body text-center">
            <div class="rounded-circle bg-primary bg-opacity-15 d-flex align-items-center justify-content-center mx-auto mb-3" style="width:90px;height:90px" id="photoPreview">
              <i class="bi bi-person text-primary fs-1"></i>
            </div>
            <input type="file" name="profile_photo" class="form-control form-control-dark form-control-sm" accept="image/jpeg,image/png,image/webp"
                   onchange="previewPhoto(this)">
            <small class="text-secondary d-block mt-2">JPG/PNG/WebP, max 2MB</small>
          </div>
        </div>
      </div>
      <div class="col-12 d-flex justify-content-end gap-2">
        <a href="/employees" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Create Employee</button>
      </div>
    </div>
  </form>
</div>
<script>
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const preview = document.getElementById('photoPreview');
      preview.innerHTML = `<img src="${e.target.result}" class="rounded-circle" style="width:90px;height:90px;object-fit:cover">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php $this->stop() ?>
