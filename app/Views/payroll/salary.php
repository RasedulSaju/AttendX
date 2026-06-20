<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Salary Assignment</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/payroll" class="text-secondary">Payroll</a></li>
          <li class="breadcrumb-item active text-secondary">Salary Assignment</li>
        </ol>
      </nav>
    </div>
  </div>

  <div class="row g-4">

    <!-- Assignment Form -->
    <div class="col-lg-5">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0">Assign / Update Salary</h6>
        </div>
        <div class="card-body">
          <form action="/payroll/salary" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="mb-3">
              <label class="form-label text-secondary">Employee <span class="text-danger">*</span></label>
              <select name="employee_id" id="empSelect" class="form-select form-select-dark" required onchange="loadCurrentSalary(this.value)">
                <option value="">Select employee…</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_code'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Current salary info -->
            <div id="currentSalaryBox" class="alert alert-dark border-secondary d-none mb-3 p-2 small">
              <span class="text-secondary">Current: </span>
              <span id="currentSalaryInfo" class="text-white"></span>
            </div>

            <div class="mb-3">
              <label class="form-label text-secondary">Salary Structure <span class="text-danger">*</span></label>
              <select name="structure_id" class="form-select form-select-dark" required>
                <option value="">Select structure…</option>
                <?php foreach ($structures as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label text-secondary">Basic Salary <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-dark text-secondary border-secondary">৳</span>
                <input type="number" name="basic_salary" class="form-control form-control-dark" step="0.01" min="0" required placeholder="0.00">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-secondary">Effective From <span class="text-danger">*</span></label>
              <input type="date" name="effective_from" class="form-control form-control-dark" value="<?= date('Y-m-01') ?>" required>
            </div>
            <hr class="border-secondary">
            <h6 class="text-secondary small mb-3">Bank Details (optional)</h6>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label text-secondary small">Payment Method</label>
                <select name="payment_method" class="form-select form-select-dark form-select-sm">
                  <option value="bank">Bank Transfer</option>
                  <option value="cash">Cash</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label text-secondary small">Bank Name</label>
                <input type="text" name="bank_name" class="form-control form-control-dark form-control-sm">
              </div>
              <div class="col-6">
                <label class="form-label text-secondary small">Account No.</label>
                <input type="text" name="bank_account" class="form-control form-control-dark form-control-sm">
              </div>
              <div class="col-6">
                <label class="form-label text-secondary small">Branch</label>
                <input type="text" name="bank_branch" class="form-control form-control-dark form-control-sm">
              </div>
            </div>
            <div class="mt-4">
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-save me-1"></i>Assign Salary
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Salary List -->
    <div class="col-lg-7">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0">Current Salary Assignments</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 small">
              <thead>
                <tr class="text-secondary">
                  <th class="ps-4">Employee</th>
                  <th>Structure</th>
                  <th>Basic</th>
                  <th>Method</th>
                  <th>Effective</th>
                </tr>
              </thead>
              <tbody id="salaryList">
                <tr><td colspan="5" class="text-center text-secondary py-4">
                  Select an employee to see history, or view all below.
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
async function loadCurrentSalary(empId) {
  const box  = document.getElementById('currentSalaryBox');
  const info = document.getElementById('currentSalaryInfo');
  const list = document.getElementById('salaryList');

  if (!empId) { box.classList.add('d-none'); return; }

  const r    = await fetch('/api/v1/employees/' + empId);
  const data = await r.json();

  if (data.data) {
    const emp = data.data;
    info.textContent = emp.full_name;
  }

  // Fetch salary history via a dedicated endpoint if available
  list.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
  try {
    const sr   = await fetch('/payroll/salary/history/' + empId);
    const sd   = await sr.json();
    if (sd.success && sd.history.length) {
      box.classList.remove('d-none');
      list.innerHTML = sd.history.map(h => `
        <tr>
          <td class="ps-4 text-white">${h.full_name}</td>
          <td class="text-secondary">${h.structure_name}</td>
          <td class="fw-semibold">৳${parseFloat(h.basic_salary).toLocaleString()}</td>
          <td class="text-secondary">${h.payment_method}</td>
          <td class="text-secondary">${h.effective_from}${h.effective_to ? ' – ' + h.effective_to : ' (current)'}</td>
        </tr>`).join('');
    } else {
      list.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-3">No salary history found.</td></tr>';
      box.classList.add('d-none');
    }
  } catch (e) {
    list.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-3">Could not load history.</td></tr>';
  }
}
</script>
<?php $this->stop() ?>
