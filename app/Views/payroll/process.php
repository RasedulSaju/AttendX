<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white"><?= htmlspecialchars($period['name']) ?></h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/payroll" class="text-secondary">Payroll</a></li>
          <li class="breadcrumb-item active text-secondary">Process</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <?php if ($period['status'] === 'draft'): ?>
      <form action="/payroll/process/<?= $period['id'] ?>" method="POST" id="processForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <button type="submit" class="btn btn-warning" id="processBtn"
          onclick="return confirm('Run payroll calculation for this period? This will overwrite any existing draft slips.')">
          <i class="bi bi-cpu me-1"></i>Run Calculation
        </button>
      </form>
      <?php elseif ($period['status'] === 'processed'): ?>
      <form action="/payroll/approve/<?= $period['id'] ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <button type="submit" class="btn btn-primary"
          onclick="return confirm('Approve this payroll period?')">
          <i class="bi bi-check-circle me-1"></i>Approve Payroll
        </button>
      </form>
      <?php elseif ($period['status'] === 'approved'): ?>
      <form action="/payroll/mark-paid/<?= $period['id'] ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <button type="submit" class="btn btn-success"
          onclick="return confirm('Mark this payroll as paid?')">
          <i class="bi bi-bank me-1"></i>Mark as Paid
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Period Info + Summary -->
  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <div class="card card-dark border-0 text-center">
            <div class="card-body py-3">
              <p class="text-secondary x-small mb-1">Employees</p>
              <h4 class="fw-bold text-white mb-0"><?= (int)($summary['total_employees'] ?? 0) ?></h4>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card card-dark border-0 text-center">
            <div class="card-body py-3">
              <p class="text-secondary x-small mb-1">Gross Earnings</p>
              <h4 class="fw-bold text-info mb-0"><?= number_format((float)($summary['total_gross'] ?? 0), 0) ?></h4>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card card-dark border-0 text-center">
            <div class="card-body py-3">
              <p class="text-secondary x-small mb-1">Deductions</p>
              <h4 class="fw-bold text-danger mb-0"><?= number_format((float)($summary['total_deductions'] ?? 0), 0) ?></h4>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card card-dark border-0 text-center">
            <div class="card-body py-3">
              <p class="text-secondary x-small mb-1">Net Payroll</p>
              <h4 class="fw-bold text-success mb-0"><?= number_format((float)($summary['total_net'] ?? 0), 0) ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card card-dark border-0 h-100">
        <div class="card-body">
          <table class="table table-sm table-dark mb-0">
            <tr><td class="text-secondary">Period</td><td class="text-white"><?= date('d M', strtotime($period['period_from'])) ?> – <?= date('d M Y', strtotime($period['period_to'])) ?></td></tr>
            <tr><td class="text-secondary">Status</td><td><span class="badge bg-warning"><?= ucfirst($period['status']) ?></span></td></tr>
            <tr><td class="text-secondary">Created</td><td class="text-secondary small"><?= $period['created_by_name'] ?? '' ?></td></tr>
            <?php if ($period['processed_at']): ?>
            <tr><td class="text-secondary">Processed</td><td class="text-secondary small"><?= date('d M Y H:i', strtotime($period['processed_at'])) ?></td></tr>
            <?php endif; ?>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Adjustments -->
  <div class="card card-dark border-0 mb-3">
    <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
      <h6 class="text-white mb-0">Adjustments</h6>
      <?php if (in_array($period['status'], ['draft','processing','processed'])): ?>
      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adjustModal">
        <i class="bi bi-plus-lg me-1"></i>Add Adjustment
      </button>
      <?php endif; ?>
    </div>
    <div class="card-body p-0">
      <?php if (empty($adjustments)): ?>
        <p class="text-secondary text-center py-4 mb-0">No adjustments for this period.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4">Employee</th><th>Type</th><th>Amount</th><th>Reason</th><th>Status</th>
              <?php if (in_array($period['status'], ['draft','processed'])): ?><th>Action</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($adjustments as $adj): ?>
            <tr>
              <td class="ps-4"><?= htmlspecialchars($adj['employee_name']) ?> <small class="text-secondary">(<?= $adj['employee_code'] ?>)</small></td>
              <td><span class="badge bg-<?= $adj['type'] === 'bonus' ? 'success' : ($adj['type'] === 'deduction' ? 'danger' : 'info') ?>"><?= ucfirst($adj['type']) ?></span></td>
              <td class="fw-semibold"><?= number_format((float)$adj['amount'], 2) ?></td>
              <td class="text-secondary"><?= htmlspecialchars($adj['reason']) ?></td>
              <td><span class="badge bg-<?= $adj['status'] === 'approved' ? 'success' : ($adj['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= ucfirst($adj['status']) ?></span></td>
              <?php if (in_array($period['status'], ['draft','processed']) && $adj['status'] === 'pending'): ?>
              <td>
                <button class="btn btn-xs btn-outline-success me-1" onclick="adjAction(<?= $adj['id'] ?>,'approved')">Approve</button>
                <button class="btn btn-xs btn-outline-danger" onclick="adjAction(<?= $adj['id'] ?>,'rejected')">Reject</button>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Payslips Table -->
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
      <h6 class="text-white mb-0">Payslips (<?= count($slips) ?>)</h6>
      <input type="text" id="slipSearch" class="form-control form-control-dark form-control-sm w-auto" placeholder="Search employee…">
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="slipsTable">
          <thead>
            <tr class="text-secondary small">
              <th class="ps-4">Employee</th>
              <th>Department</th>
              <th>Present</th>
              <th>Absent</th>
              <th>OT Hrs</th>
              <th>Gross</th>
              <th>Deductions</th>
              <th>Net</th>
              <th class="text-end pe-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($slips)): ?>
              <tr><td colspan="9" class="text-center text-secondary py-5">
                <?= $period['status'] === 'draft' ? 'Click "Run Calculation" to generate payslips.' : 'No payslips found.' ?>
              </td></tr>
            <?php else: ?>
              <?php foreach ($slips as $s): ?>
              <tr class="slip-row" data-name="<?= strtolower($s['full_name'] . ' ' . $s['employee_code']) ?>">
                <td class="ps-4">
                  <span class="fw-semibold text-white"><?= htmlspecialchars($s['full_name']) ?></span><br>
                  <small class="text-secondary"><?= $s['employee_code'] ?></small>
                </td>
                <td class="text-secondary small"><?= htmlspecialchars($s['department_name'] ?? '') ?></td>
                <td><?= $s['present_days'] ?></td>
                <td class="text-danger"><?= $s['absent_days'] ?></td>
                <td><?= $s['overtime_hours'] ?></td>
                <td><?= number_format((float)$s['gross_earnings'], 2) ?></td>
                <td class="text-danger"><?= number_format((float)$s['total_deductions'], 2) ?></td>
                <td class="fw-semibold text-success"><?= number_format((float)$s['net_salary'], 2) ?></td>
                <td class="text-end pe-4">
                  <a href="/payroll/slip/<?= $s['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-file-text"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Adjustment Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-white">Add Adjustment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label text-secondary">Employee <span class="text-danger">*</span></label>
          <select name="employee_id" id="adjEmployee" class="form-select form-select-dark">
            <option value="">Select employee…</option>
            <?php foreach ($slips as $s): ?>
              <option value="<?= $s['employee_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['employee_code'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label text-secondary">Type <span class="text-danger">*</span></label>
            <select name="type" id="adjType" class="form-select form-select-dark">
              <option value="bonus">Bonus</option>
              <option value="deduction">Deduction</option>
              <option value="advance">Advance</option>
              <option value="reimbursement">Reimbursement</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label text-secondary">Amount <span class="text-danger">*</span></label>
            <input type="number" id="adjAmount" class="form-control form-control-dark" step="0.01" min="0.01" placeholder="0.00">
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label text-secondary">Reason <span class="text-danger">*</span></label>
          <textarea id="adjReason" class="form-control form-control-dark" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveAdjustment()">Add</button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';
const PERIOD_ID = <?= $period['id'] ?>;

// Slip search
document.getElementById('slipSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.slip-row').forEach(r => {
    r.style.display = r.dataset.name.includes(q) ? '' : 'none';
  });
});

async function saveAdjustment() {
  const data = {
    csrf_token:  CSRF,
    period_id:   PERIOD_ID,
    employee_id: document.getElementById('adjEmployee').value,
    type:        document.getElementById('adjType').value,
    amount:      document.getElementById('adjAmount').value,
    reason:      document.getElementById('adjReason').value,
  };

  if (!data.employee_id || !data.amount || !data.reason) {
    alert('All fields are required.'); return;
  }

  const r = await fetch('/payroll/adjustment', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data),
  });
  const json = await r.json();
  if (json.success) { location.reload(); }
  else { alert(json.message || 'Error saving adjustment.'); }
}

async function adjAction(id, status) {
  if (!confirm(`${status === 'approved' ? 'Approve' : 'Reject'} this adjustment?`)) return;
  const r = await fetch(`/payroll/adjustment/${id}/approve`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({csrf_token: CSRF, status}),
  });
  const json = await r.json();
  if (json.success) location.reload();
}
</script>
<?php $this->stop() ?>
