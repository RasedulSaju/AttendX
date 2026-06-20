<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Attendance Report</h4>
    <div class="d-flex gap-2">
      <a href="/reports" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
      <a href="/reports/attendance/export?<?= http_build_query($filters) ?>" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
    </div>
  </div>

  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-2"><label class="form-label text-secondary small">From</label><input type="date" name="date_from" class="form-control form-control-dark form-control-sm" value="<?= $filters['date_from']??'' ?>"></div>
        <div class="col-md-2"><label class="form-label text-secondary small">To</label><input type="date" name="date_to" class="form-control form-control-dark form-control-sm" value="<?= $filters['date_to']??'' ?>"></div>
        <div class="col-md-3"><label class="form-label text-secondary small">Department</label>
          <select name="department_id" class="form-select form-select-dark form-select-sm">
            <option value="">All</option>
            <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= ($filters['department_id']??0)==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><button class="btn btn-primary btn-sm">Apply Filters</button></div>
      </form>
    </div>
  </div>

  <?php if (!empty($summary)): ?>
  <div class="row g-3 mb-4">
    <?php foreach (['present'=>'success','absent'=>'danger','late'=>'warning','half_day'=>'info'] as $k=>$c): ?>
    <div class="col-md-3"><div class="card card-dark border-0 text-center"><div class="card-body py-3">
      <h4 class="fw-bold text-<?= $c ?> mb-0"><?= $summary[$k]??0 ?></h4><p class="text-secondary small mb-0"><?= ucfirst(str_replace('_',' ',$k)) ?></p>
    </div></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Employee</th><th>Dept</th><th>Date</th><th>Status</th><th>In</th><th>Out</th><th class="pe-4">OT</th></tr></thead>
          <tbody>
            <?php foreach ($records as $r): ?>
            <?php $sc = match($r['status']) { 'present'=>'success','late'=>'warning','absent'=>'danger',default=>'secondary' }; ?>
            <tr>
              <td class="ps-4 text-white"><?= htmlspecialchars($r['full_name']??'') ?></td>
              <td class="text-secondary"><?= htmlspecialchars($r['department_name']??'') ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($r['status']) ?></span></td>
              <td class="text-secondary"><?= $r['time_in']?substr($r['time_in'],0,5):'—' ?></td>
              <td class="text-secondary"><?= $r['time_out']?substr($r['time_out'],0,5):'—' ?></td>
              <td class="pe-4"><?= $r['overtime_minutes'] ?>m</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php $this->stop() ?>
