<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Leave Report — <?= $year ?></h4>
    <div class="d-flex gap-2">
      <form method="GET" class="d-flex gap-2"><input type="number" name="year" class="form-control form-control-dark form-control-sm" value="<?= $year ?>" style="width:100px"><button class="btn btn-outline-secondary btn-sm">Go</button></form>
      <a href="/reports" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
      <a href="/reports/leave/export?year=<?= $year ?>" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Export</a>
    </div>
  </div>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Employee</th><th>Dept</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th class="pe-4">Status</th></tr></thead>
          <tbody>
            <?php if (empty($apps)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-5">No leave records for <?= $year ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($apps as $a): ?>
            <?php $sc = match($a['status']) { 'approved'=>'success','rejected'=>'danger','pending'=>'warning',default=>'secondary' }; ?>
            <tr>
              <td class="ps-4 text-white"><?= htmlspecialchars($a['full_name']) ?></td>
              <td class="text-secondary"><?= htmlspecialchars($a['department_name']??'') ?></td>
              <td class="text-secondary"><?= htmlspecialchars($a['leave_type_name']) ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($a['from_date'])) ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($a['to_date'])) ?></td>
              <td><?= $a['total_days'] ?></td>
              <td class="pe-4"><span class="badge bg-<?= $sc ?>"><?= ucfirst($a['status']) ?></span></td>
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
