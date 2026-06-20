<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Payroll Report</h4>
    <a href="/reports" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  </div>
  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead><tr class="text-secondary small"><th class="ps-4">Period</th><th>From</th><th>To</th><th>Employees</th><th>Total Net</th><th>Status</th><th class="pe-4 text-end">Export</th></tr></thead>
          <tbody>
            <?php if (empty($periods)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-5">No processed payroll periods yet.</td></tr>
            <?php else: ?>
            <?php foreach ($periods as $p): ?>
            <?php $sc = match($p['status']) { 'paid'=>'success','approved'=>'info','closed'=>'secondary',default=>'warning' }; ?>
            <tr>
              <td class="ps-4 text-white"><?= htmlspecialchars($p['name']) ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($p['period_from'])) ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($p['period_to'])) ?></td>
              <td><?= $p['emp_count'] ?></td>
              <td class="text-success fw-semibold"><?= number_format((float)$p['total_net'],2) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($p['status']) ?></span></td>
              <td class="pe-4 text-end"><a href="/reports/payroll/export?period_id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-download"></i></a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php $this->stop() ?>
