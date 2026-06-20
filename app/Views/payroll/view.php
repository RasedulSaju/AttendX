<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white"><?= htmlspecialchars($period['name']) ?></h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/payroll" class="text-secondary">Payroll</a></li>
          <li class="breadcrumb-item active text-secondary">View</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <?php if ($period['status'] === 'processed'): ?>
      <form action="/payroll/approve/<?= $period['id'] ?>" method="POST" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Approve this payroll?')">
          <i class="bi bi-check-circle me-1"></i>Approve
        </button>
      </form>
      <?php elseif ($period['status'] === 'approved'): ?>
      <form action="/payroll/mark-paid/<?= $period['id'] ?>" method="POST" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Mark as paid?')">
          <i class="bi bi-bank me-1"></i>Mark Paid
        </button>
      </form>
      <?php endif; ?>
      <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-printer me-1"></i>Print
      </button>
    </div>
  </div>

  <!-- Period Summary -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card card-dark border-0 text-center">
        <div class="card-body py-3">
          <p class="text-secondary small mb-1">Period</p>
          <h6 class="text-white mb-0">
            <?= date('d M', strtotime($period['period_from'])) ?> – <?= date('d M Y', strtotime($period['period_to'])) ?>
          </h6>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-dark border-0 text-center">
        <div class="card-body py-3">
          <p class="text-secondary small mb-1">Status</p>
          <?php $badge = match ($period['status']) {
            'approved' => 'primary', 'paid' => 'success', 'closed' => 'dark', default => 'secondary'
          }; ?>
          <span class="badge bg-<?= $badge ?> fs-6"><?= ucfirst($period['status']) ?></span>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-dark border-0 text-center">
        <div class="card-body py-3">
          <p class="text-secondary small mb-1">Employees</p>
          <h4 class="fw-bold text-white mb-0"><?= (int)($summary['total_employees'] ?? 0) ?></h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-dark border-0 text-center">
        <div class="card-body py-3">
          <p class="text-secondary small mb-1">Total Net</p>
          <h4 class="fw-bold text-success mb-0"><?= number_format((float)($summary['total_net'] ?? 0), 2) ?></h4>
        </div>
      </div>
    </div>
  </div>

  <!-- Dept Summary -->
  <?php if (!empty($deptSummary)): ?>
  <div class="row g-3 mb-4">
    <?php foreach ($deptSummary as $d): ?>
    <div class="col-md-3">
      <div class="card card-dark border-0">
        <div class="card-body py-2 px-3">
          <p class="text-secondary small mb-0"><?= htmlspecialchars($d['department_name'] ?? 'Unknown') ?></p>
          <div class="d-flex align-items-center justify-content-between">
            <small class="text-secondary"><?= $d['employees'] ?> emp</small>
            <span class="fw-semibold text-success"><?= number_format((float)$d['total_net'], 0) ?></span>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Slips Table -->
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
      <h6 class="text-white mb-0">Payslips (<?= count($slips) ?>)</h6>
      <input type="text" id="slipSearch" class="form-control form-control-dark form-control-sm w-auto" placeholder="Search…">
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4">Employee</th>
              <th>Department</th>
              <th>Present</th>
              <th>Leave</th>
              <th>Gross</th>
              <th>Deductions</th>
              <th class="text-success">Net</th>
              <th>Status</th>
              <th class="pe-4 text-end">Slip</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($slips as $s): ?>
            <tr class="slip-row" data-name="<?= strtolower($s['full_name'].' '.$s['employee_code']) ?>">
              <td class="ps-4">
                <div class="fw-semibold text-white"><?= htmlspecialchars($s['full_name']) ?></div>
                <small class="text-secondary"><?= $s['employee_code'] ?></small>
              </td>
              <td class="text-secondary"><?= htmlspecialchars($s['department_name'] ?? '') ?></td>
              <td><?= $s['present_days'] ?></td>
              <td><?= $s['leave_days'] ?></td>
              <td><?= number_format((float)$s['gross_earnings'], 2) ?></td>
              <td class="text-danger"><?= number_format((float)$s['total_deductions'], 2) ?></td>
              <td class="fw-semibold text-success"><?= number_format((float)$s['net_salary'], 2) ?></td>
              <td>
                <span class="badge bg-<?= $s['status'] === 'paid' ? 'success' : ($s['status'] === 'approved' ? 'primary' : 'secondary') ?>">
                  <?= ucfirst($s['status']) ?>
                </span>
              </td>
              <td class="pe-4 text-end">
                <a href="/payroll/slip/<?= $s['id'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary">
                  <i class="bi bi-file-text"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="border-top border-secondary fw-semibold">
              <td colspan="4" class="ps-4 text-secondary">Totals</td>
              <td><?= number_format((float)($summary['total_gross'] ?? 0), 2) ?></td>
              <td class="text-danger"><?= number_format((float)($summary['total_deductions'] ?? 0), 2) ?></td>
              <td class="text-success"><?= number_format((float)($summary['total_net'] ?? 0), 2) ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('slipSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.slip-row').forEach(r => {
    r.style.display = r.dataset.name.includes(q) ? '' : 'none';
  });
});
</script>
<?php $this->stop() ?>
