<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Apply for Leave</h4>
    <a href="/leave" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card card-dark border-0">
        <div class="card-body">
          <form action="/leave/apply" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <?php if (!empty($employees)): ?>
            <div class="mb-3">
              <label class="form-label text-secondary">Employee (leave blank to apply for yourself)</label>
              <select name="employee_id" class="form-select form-select-dark">
                <option value="">— Myself —</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_code'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <div class="mb-3">
              <label class="form-label text-secondary">Leave Type *</label>
              <select name="leave_type_id" class="form-select form-select-dark" required>
                <option value="">— Select —</option>
                <?php foreach ($types as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['days_allowed'] ?> days/yr)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6"><label class="form-label text-secondary">From Date *</label>
                <input type="date" name="from_date" class="form-control form-control-dark" required></div>
              <div class="col-md-6"><label class="form-label text-secondary">To Date *</label>
                <input type="date" name="to_date" class="form-control form-control-dark" required></div>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="half_day" value="1" id="halfDay">
              <label class="form-check-label text-secondary" for="halfDay">Half Day</label>
            </div>

            <div class="mb-3">
              <label class="form-label text-secondary">Reason * (min 10 characters)</label>
              <textarea name="reason" class="form-control form-control-dark" rows="3" required minlength="10"></textarea>
            </div>

            <div class="mb-4">
              <label class="form-label text-secondary">Supporting Document (optional)</label>
              <input type="file" name="document" class="form-control form-control-dark" accept=".pdf,.jpg,.jpeg,.png">
              <small class="text-secondary">PDF/Image, max 5MB. Required for some leave types (e.g. Sick Leave, Maternity).</small>
            </div>

            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Submit Application</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $this->stop() ?>
