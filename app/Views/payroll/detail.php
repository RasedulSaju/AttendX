<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Payslip</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/payroll" class="text-secondary">Payroll</a></li>
          <li class="breadcrumb-item"><a href="/payroll/view/<?= $slip['period_id'] ?>" class="text-secondary"><?= htmlspecialchars($slip['period_name']) ?></a></li>
          <li class="breadcrumb-item active text-secondary">Payslip</li>
        </ol>
      </nav>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card card-dark border-0" id="payslipCard">

        <!-- Slip Header -->
        <div class="card-body border-bottom border-secondary">
          <div class="row align-items-center">
            <div class="col">
              <h5 class="text-white fw-bold mb-1">PAYSLIP</h5>
              <p class="text-secondary mb-0 small">
                <?= htmlspecialchars($slip['period_name']) ?> &nbsp;|&nbsp;
                <?= date('d M Y', strtotime($slip['period_from'])) ?> – <?= date('d M Y', strtotime($slip['period_to'])) ?>
              </p>
            </div>
            <div class="col-auto text-end">
              <span class="badge bg-<?= $slip['status'] === 'paid' ? 'success' : ($slip['status'] === 'approved' ? 'primary' : 'secondary') ?> fs-6">
                <?= strtoupper($slip['status']) ?>
              </span>
              <?php if ($slip['payment_date']): ?>
              <div class="text-secondary small mt-1">Paid: <?= date('d M Y', strtotime($slip['payment_date'])) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Employee Info -->
        <div class="card-body border-bottom border-secondary">
          <div class="row">
            <div class="col-md-6">
              <table class="table table-sm table-dark table-borderless mb-0">
                <tr>
                  <td class="text-secondary ps-0" style="width:140px">Employee Name</td>
                  <td class="text-white fw-semibold"><?= htmlspecialchars($slip['full_name']) ?></td>
                </tr>
                <tr>
                  <td class="text-secondary ps-0">Employee ID</td>
                  <td><?= htmlspecialchars($slip['employee_code']) ?></td>
                </tr>
                <tr>
                  <td class="text-secondary ps-0">Department</td>
                  <td><?= htmlspecialchars($slip['department_name'] ?? '—') ?></td>
                </tr>
                <tr>
                  <td class="text-secondary ps-0">Designation</td>
                  <td><?= htmlspecialchars($slip['designation_name'] ?? '—') ?></td>
                </tr>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-sm table-dark table-borderless mb-0">
                <tr>
                  <td class="text-secondary ps-0" style="width:140px">Working Days</td>
                  <td><?= $slip['working_days'] ?></td>
                </tr>
                <tr>
                  <td class="text-secondary ps-0">Present Days</td>
                  <td class="text-success"><?= $slip['present_days'] ?></td>
                </tr>
                <tr>
                  <td class="text-secondary ps-0">Absent Days</td>
                  <td class="text-danger"><?= $slip['absent_days'] ?></td>
                </tr>
                <tr>
                  <td class="text-secondary ps-0">Leave Days</td>
                  <td><?= $slip['leave_days'] ?></td>
                </tr>
                <tr>
                  <td class="text-secondary ps-0">Overtime Hours</td>
                  <td><?= $slip['overtime_hours'] ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>

        <!-- Earnings & Deductions -->
        <div class="card-body border-bottom border-secondary">
          <div class="row">
            <!-- Earnings -->
            <div class="col-md-6">
              <h6 class="text-success text-uppercase small mb-3 fw-semibold">Earnings</h6>
              <table class="table table-sm table-dark table-borderless">
                <thead>
                  <tr class="text-secondary" style="font-size:0.75rem">
                    <th class="ps-0">Component</th>
                    <th class="text-end pe-0">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($earnings as $e): ?>
                  <tr>
                    <td class="ps-0 text-secondary"><?= htmlspecialchars($e['component_name']) ?></td>
                    <td class="text-end pe-0"><?= number_format((float)$e['amount'], 2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr class="border-top border-secondary">
                    <td class="ps-0 fw-semibold text-success">Total Earnings</td>
                    <td class="text-end pe-0 fw-semibold text-success"><?= number_format((float)$slip['gross_earnings'], 2) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <!-- Deductions -->
            <div class="col-md-6">
              <h6 class="text-danger text-uppercase small mb-3 fw-semibold">Deductions</h6>
              <table class="table table-sm table-dark table-borderless">
                <thead>
                  <tr class="text-secondary" style="font-size:0.75rem">
                    <th class="ps-0">Component</th>
                    <th class="text-end pe-0">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($deductions)): ?>
                  <tr><td colspan="2" class="ps-0 text-secondary small">No deductions</td></tr>
                  <?php else: ?>
                  <?php foreach ($deductions as $d): ?>
                  <tr>
                    <td class="ps-0 text-secondary"><?= htmlspecialchars($d['component_name']) ?></td>
                    <td class="text-end pe-0 text-danger"><?= number_format((float)$d['amount'], 2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
                <tfoot>
                  <tr class="border-top border-secondary">
                    <td class="ps-0 fw-semibold text-danger">Total Deductions</td>
                    <td class="text-end pe-0 fw-semibold text-danger"><?= number_format((float)$slip['total_deductions'], 2) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- Net Salary -->
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col">
              <p class="text-secondary mb-0 small">This is a computer-generated payslip and does not require a signature.</p>
            </div>
            <div class="col-auto text-end">
              <p class="text-secondary small mb-1">Net Salary</p>
              <h2 class="fw-bold text-success mb-0"><?= number_format((float)$slip['net_salary'], 2) ?></h2>
              <?php if ($slip['payment_ref']): ?>
              <small class="text-secondary">Ref: <?= htmlspecialchars($slip['payment_ref']) ?></small>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div><!-- /card -->
    </div>
  </div>
</div>

<style>
@media print {
  body { background: #fff !important; color: #000 !important; }
  .sidebar, .navbar, nav, .btn, nav[aria-label="breadcrumb"] { display: none !important; }
  .container-fluid { padding: 0 !important; }
  #payslipCard { border: 1px solid #ccc !important; background: #fff !important; color: #000 !important; }
  .text-white, .text-secondary, .text-success, .text-danger, .text-info { color: #000 !important; }
  .card-dark, .table-dark { background: #fff !important; color: #000 !important; }
  .badge { border: 1px solid #000 !important; color: #000 !important; background: #eee !important; }
  .border-secondary { border-color: #ccc !important; }
}
</style>
<?php $this->stop() ?>
