<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Create Payroll Period</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/payroll" class="text-secondary">Payroll</a></li>
          <li class="breadcrumb-item active text-secondary">Create</li>
        </ol>
      </nav>
    </div>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0">New Payroll Period</h6>
        </div>
        <div class="card-body">
          <form action="/payroll/create" method="POST" novalidate id="createForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="mb-4">
              <label class="form-label text-secondary">Period Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="periodName" class="form-control form-control-dark"
                     placeholder="e.g. May 2025 Payroll" required minlength="3" maxlength="100"
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
              <small class="text-secondary">A descriptive name for this payroll run.</small>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-6">
                <label class="form-label text-secondary">Period From <span class="text-danger">*</span></label>
                <input type="date" name="period_from" id="periodFrom" class="form-control form-control-dark"
                       required value="<?= htmlspecialchars($_POST['period_from'] ?? date('Y-m-01')) ?>">
              </div>
              <div class="col-6">
                <label class="form-label text-secondary">Period To <span class="text-danger">*</span></label>
                <input type="date" name="period_to" id="periodTo" class="form-control form-control-dark"
                       required value="<?= htmlspecialchars($_POST['period_to'] ?? date('Y-m-t')) ?>">
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label text-secondary">Notes</label>
              <textarea name="notes" class="form-control form-control-dark" rows="3"
                        placeholder="Optional notes about this payroll period…"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>

            <!-- Period preview -->
            <div class="card mb-4" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2)">
              <div class="card-body py-3">
                <div class="row text-center g-0">
                  <div class="col-4">
                    <p class="text-secondary small mb-0">Working Days</p>
                    <h5 class="fw-bold text-white mb-0" id="previewDays">—</h5>
                  </div>
                  <div class="col-4 border-start border-end" style="border-color:rgba(99,102,241,0.2)!important">
                    <p class="text-secondary small mb-0">Period</p>
                    <h6 class="fw-semibold text-primary mb-0" id="previewPeriod">—</h6>
                  </div>
                  <div class="col-4">
                    <p class="text-secondary small mb-0">Duration</p>
                    <h5 class="fw-bold text-white mb-0" id="previewDuration">— days</h5>
                  </div>
                </div>
              </div>
            </div>

            <div class="d-flex gap-3">
              <a href="/payroll" class="btn btn-outline-secondary flex-grow-1">Cancel</a>
              <button type="submit" class="btn btn-primary flex-grow-1" id="submitBtn">
                <i class="bi bi-plus-lg me-1"></i>Create & Process
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const nameEl     = document.getElementById('periodName');
const fromEl     = document.getElementById('periodFrom');
const toEl       = document.getElementById('periodTo');
const previewDay = document.getElementById('previewDays');
const previewPer = document.getElementById('previewPeriod');
const previewDur = document.getElementById('previewDuration');

function updatePreview() {
  const from = new Date(fromEl.value);
  const to   = new Date(toEl.value);

  if (isNaN(from) || isNaN(to) || from > to) {
    previewDay.textContent = '—';
    previewPer.textContent = '—';
    previewDur.textContent = '—';
    return;
  }

  // Auto-fill name if empty
  if (!nameEl.value) {
    nameEl.value = from.toLocaleString('default', { month: 'long' }) + ' ' + from.getFullYear() + ' Payroll';
  }

  // Count Mon–Fri working days
  let workDays = 0;
  const cur = new Date(from);
  while (cur <= to) {
    const dow = cur.getDay();
    if (dow !== 0 && dow !== 6) workDays++;
    cur.setDate(cur.getDate() + 1);
  }

  const duration = Math.round((to - from) / 86400000) + 1;

  const fmtOpts = { day: '2-digit', month: 'short', year: 'numeric' };
  previewDay.textContent = workDays;
  previewPer.textContent = from.toLocaleDateString('en-GB', fmtOpts) + ' – ' + to.toLocaleDateString('en-GB', fmtOpts);
  previewDur.textContent = duration + ' days';
}

fromEl.addEventListener('change', updatePreview);
toEl.addEventListener('change', updatePreview);
updatePreview();

document.getElementById('createForm').addEventListener('submit', function (e) {
  const from = new Date(fromEl.value);
  const to   = new Date(toEl.value);

  if (!fromEl.value || !toEl.value) {
    e.preventDefault();
    alert('Please select both period dates.');
    return;
  }
  if (from > to) {
    e.preventDefault();
    alert('"Period From" must be before "Period To".');
    return;
  }
  document.getElementById('submitBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating…';
  document.getElementById('submitBtn').disabled = true;
});
</script>
<?php $this->stop() ?>
