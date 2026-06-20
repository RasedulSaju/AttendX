<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white"><?= htmlspecialchars($emp['full_name']) ?> — Attendance</h4>
      <p class="text-secondary small mb-0"><?= $emp['employee_code'] ?></p>
    </div>
    <form method="GET" class="d-flex gap-2">
      <input type="date" name="date_from" class="form-control form-control-dark form-control-sm" value="<?= $dateFrom ?>">
      <input type="date" name="date_to" class="form-control form-control-dark form-control-sm" value="<?= $dateTo ?>">
      <button class="btn btn-primary btn-sm">Filter</button>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <?php $cards=[['Present','present','success'],['Absent','absent','danger'],['Late','late','warning'],['Half Day','half_day','info'],['On Leave','on_leave','secondary']]; ?>
    <?php foreach ($cards as [$label,$key,$color]): ?>
    <div class="col"><div class="card card-dark border-0 text-center"><div class="card-body py-3">
      <p class="text-secondary small mb-1"><?= $label ?></p>
      <h4 class="fw-bold text-<?= $color ?> mb-0"><?= $stats[$key]??0 ?></h4>
    </div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Date</th><th>Status</th><th>Time In</th><th>Time Out</th><th>Work</th><th>Late</th><th class="pe-4">OT</th></tr></thead>
          <tbody>
            <?php foreach ($records as $r): ?>
            <?php $sc = match($r['status']) { 'present'=>'success','late'=>'warning','absent'=>'danger','half_day'=>'info',default=>'secondary' }; ?>
            <tr>
              <td class="ps-4 text-secondary"><?= date('d M Y (D)', strtotime($r['attendance_date'])) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
              <td class="text-secondary"><?= $r['time_in']?substr($r['time_in'],0,5):'—' ?></td>
              <td class="text-secondary"><?= $r['time_out']?substr($r['time_out'],0,5):'—' ?></td>
              <td><?= floor($r['work_minutes']/60) ?>h <?= $r['work_minutes']%60 ?>m</td>
              <td class="<?= $r['late_minutes']>0?'text-warning':'' ?>"><?= $r['late_minutes'] ?>m</td>
              <td class="pe-4 <?= $r['overtime_minutes']>0?'text-info':'' ?>"><?= $r['overtime_minutes'] ?>m</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php $this->stop() ?>
