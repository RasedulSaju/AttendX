<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Attendance</h4>
    <div class="d-flex gap-2">
      <a href="/attendance/process" class="btn btn-warning btn-sm"><i class="bi bi-cpu me-1"></i>Process</a>
      <a href="/attendance/export?<?= http_build_query($filters) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export</a>
    </div>
  </div>

  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-2"><label class="form-label text-secondary small">From</label>
          <input type="date" name="date_from" class="form-control form-control-dark form-control-sm" value="<?= htmlspecialchars($filters['date_from']??'') ?>"></div>
        <div class="col-md-2"><label class="form-label text-secondary small">To</label>
          <input type="date" name="date_to" class="form-control form-control-dark form-control-sm" value="<?= htmlspecialchars($filters['date_to']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label text-secondary small">Department</label>
          <select name="department_id" class="form-select form-select-dark form-select-sm">
            <option value="">All</option>
            <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= ($filters['department_id']??0)==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-md-2"><label class="form-label text-secondary small">Status</label>
          <select name="status" class="form-select form-select-dark form-select-sm">
            <option value="">All</option>
            <?php foreach (['present','absent','late','half_day','on_leave'] as $s): ?>
            <option value="<?= $s ?>" <?= ($filters['status']??'')===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i></button>
          <a href="/attendance" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0"><h6 class="text-white mb-0"><?= number_format($total) ?> records</h6></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Employee</th><th>Department</th><th>Date</th><th>Status</th><th>Time In</th><th>Time Out</th><th>Late</th><th class="pe-4">OT</th></tr></thead>
          <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="8" class="text-center text-secondary py-5">No attendance records found.</td></tr>
            <?php else: ?>
            <?php foreach ($records as $r): ?>
            <?php $sc = match($r['status']) { 'present'=>'success','late'=>'warning','absent'=>'danger','half_day'=>'info','on_leave'=>'secondary',default=>'secondary' }; ?>
            <tr>
              <td class="ps-4">
                <div class="fw-semibold text-white"><?= htmlspecialchars($r['full_name']??'') ?></div>
                <div class="text-secondary" style="font-size:.7rem"><?= $r['employee_code']??'' ?></div>
              </td>
              <td class="text-secondary"><?= htmlspecialchars($r['department_name']??'—') ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
              <td class="text-secondary"><?= $r['time_in'] ? substr($r['time_in'],0,5) : '—' ?></td>
              <td class="text-secondary"><?= $r['time_out'] ? substr($r['time_out'],0,5) : '—' ?></td>
              <td class="<?= $r['late_minutes']>0?'text-warning':'text-secondary' ?>"><?= $r['late_minutes'] ?>m</td>
              <td class="pe-4 <?= $r['overtime_minutes']>0?'text-info':'text-secondary' ?>"><?= $r['overtime_minutes'] ?>m</td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($total > $perPage): ?>
      <div class="d-flex justify-content-between align-items-center px-4 py-3">
        <small class="text-secondary">Showing <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage,$total) ?> of <?= number_format($total) ?></small>
        <?php $q=http_build_query(array_merge($filters,['page'=>'__P__'])); $pages=ceil($total/$perPage); ?>
        <nav><ul class="pagination pagination-sm mb-0">
          <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
          <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?<?= str_replace('__P__',$i,$q) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
        </ul></nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $this->stop() ?>
