<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Late Arrivals Report</h4>
    <div class="d-flex gap-2">
      <a href="/reports" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
      <a href="/reports/late-arrivals/export?<?= http_build_query($filters) ?>" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Export</a>
    </div>
  </div>
  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label text-secondary small">From</label><input type="date" name="date_from" class="form-control form-control-dark form-control-sm" value="<?= $filters['date_from']??'' ?>"></div>
        <div class="col-md-3"><label class="form-label text-secondary small">To</label><input type="date" name="date_to" class="form-control form-control-dark form-control-sm" value="<?= $filters['date_to']??'' ?>"></div>
        <div class="col-md-3"><button class="btn btn-primary btn-sm">Apply</button></div>
      </form>
    </div>
  </div>
  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">#</th><th>Employee</th><th>Dept</th><th>Late Count</th><th>Avg Late (min)</th><th class="pe-4">Total Late (min)</th></tr></thead>
          <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="6" class="text-center text-secondary py-5">No late arrivals in selected range.</td></tr>
            <?php else: ?>
            <?php foreach ($records as $i => $r): ?>
            <tr>
              <td class="ps-4 text-secondary"><?= $i+1 ?></td>
              <td class="text-white"><?= htmlspecialchars($r['full_name']) ?> <small class="text-secondary">(<?= $r['employee_code'] ?>)</small></td>
              <td class="text-secondary"><?= htmlspecialchars($r['department']??'') ?></td>
              <td><span class="badge bg-warning"><?= $r['late_count'] ?></span></td>
              <td class="text-secondary"><?= round($r['avg_late_minutes'],1) ?>m</td>
              <td class="pe-4 text-warning fw-semibold"><?= $r['total_late_minutes'] ?>m</td>
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
