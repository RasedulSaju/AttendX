<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Overtime</h4>
    <form method="GET" class="d-flex gap-2">
      <input type="date" name="date_from" class="form-control form-control-dark form-control-sm" value="<?= htmlspecialchars($filters['date_from']??'') ?>">
      <input type="date" name="date_to" class="form-control form-control-dark form-control-sm" value="<?= htmlspecialchars($filters['date_to']??'') ?>">
      <button class="btn btn-primary btn-sm">Filter</button>
    </form>
  </div>
  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Employee</th><th>Department</th><th>Date</th><th class="pe-4">OT (min)</th></tr></thead>
          <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="4" class="text-center text-secondary py-5">No overtime records.</td></tr>
            <?php else: ?>
            <?php foreach ($records as $r): ?>
            <tr>
              <td class="ps-4 text-white"><?= htmlspecialchars($r['full_name']) ?> <small class="text-secondary">(<?= $r['employee_code'] ?>)</small></td>
              <td class="text-secondary"><?= htmlspecialchars($r['department_name']??'') ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
              <td class="pe-4 text-info fw-semibold"><?= $r['overtime_minutes'] ?>m</td>
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
