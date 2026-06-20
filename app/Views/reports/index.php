<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <h4 class="mb-4 fw-semibold text-white">Reports</h4>

  <div class="row g-3 mb-4">
    <?php $cards=[
      ['Total Employees',$kpi['total_employees']??0,'people','primary'],
      ['Present Today',$kpi['present_today']??0,'person-check','success'],
      ['Absent Today',$kpi['absent_today']??0,'person-x','danger'],
      ['Attendance Rate',($kpi['attendance_rate']??0).'%','graph-up','info'],
    ]; ?>
    <?php foreach ($cards as [$label,$val,$icon,$color]): ?>
    <div class="col-md-3"><div class="card card-dark border-0"><div class="card-body d-flex align-items-center gap-3">
      <div class="rounded-circle bg-<?= $color ?> bg-opacity-15 d-flex align-items-center justify-content-center" style="width:44px;height:44px"><i class="bi bi-<?= $icon ?> text-<?= $color ?>"></i></div>
      <div><p class="text-secondary small mb-0"><?= $label ?></p><h4 class="fw-bold text-white mb-0"><?= $val ?></h4></div>
    </div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3">
    <?php $reports = [
      ['Attendance Report','View detailed attendance records by employee and date range.','file-bar-graph','/reports/attendance'],
      ['Leave Report','Leave applications, approvals, and balances.','calendar-x','/reports/leave'],
      ['Payroll Report','Processed payroll periods and salary breakdowns.','cash-stack','/reports/payroll'],
      ['Overtime Report','Overtime hours by employee and period.','clock-history','/reports/overtime'],
      ['Employee Report','Full employee directory with filters.','people','/reports/employees'],
      ['Late Arrivals Report','Top employees by late arrival frequency.','alarm','/reports/late-arrivals'],
    ]; ?>
    <?php foreach ($reports as [$title2,$desc,$icon,$link]): ?>
    <div class="col-md-4">
      <a href="<?= $link ?>" class="text-decoration-none">
        <div class="card card-dark border-0 h-100 hover-card">
          <div class="card-body">
            <i class="bi bi-<?= $icon ?> text-primary fs-3 mb-2 d-block"></i>
            <h6 class="text-white"><?= $title2 ?></h6>
            <p class="text-secondary small mb-0"><?= $desc ?></p>
          </div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<style>.hover-card:hover{transform:translateY(-2px);transition:.2s;border-color:#6366f1!important}</style>
<?php $this->stop() ?>
