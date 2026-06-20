<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Dashboard</h4>
      <p class="text-secondary small mb-0"><?= date('l, d F Y') ?></p>
    </div>
    <div class="d-flex gap-2">
      <a href="/attendance/process" class="btn btn-outline-secondary btn-sm"><i class="bi bi-cpu me-1"></i>Process Attendance</a>
      <a href="/analytics" class="btn btn-primary btn-sm"><i class="bi bi-graph-up me-1"></i>Analytics</a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <?php
      $cards = [
        ['label'=>'Total Employees',  'value'=>$kpi['total_employees']??0,  'icon'=>'people',         'color'=>'primary',  'link'=>'/employees'],
        ['label'=>'Present Today',    'value'=>$kpi['present_today']??0,    'icon'=>'person-check',   'color'=>'success',  'link'=>'/attendance'],
        ['label'=>'Absent Today',     'value'=>$kpi['absent_today']??0,     'icon'=>'person-x',       'color'=>'danger',   'link'=>'/attendance?status=absent'],
        ['label'=>'Late Today',       'value'=>$kpi['late_today']??0,       'icon'=>'clock-history',  'color'=>'warning',  'link'=>'/attendance?status=late'],
        ['label'=>'Pending Leaves',   'value'=>$pendingLeaves,              'icon'=>'calendar-x',     'color'=>'info',     'link'=>'/leave?status=pending'],
        ['label'=>'Attendance Rate',  'value'=>($kpi['attendance_rate']??0).'%', 'icon'=>'graph-up', 'color'=>'success',  'link'=>'/analytics/attendance'],
        ['label'=>'Offline Devices',  'value'=>$kpi['offline_devices']??0,  'icon'=>'hdd-network',   'color'=>($kpi['offline_devices']??0)>0?'danger':'success','link'=>'/devices'],
        ['label'=>'Notifications',    'value'=>$unreadNotif,               'icon'=>'bell',            'color'=>'secondary','link'=>'/notifications'],
      ];
    ?>
    <?php foreach ($cards as $c): ?>
    <div class="col-6 col-md-3">
      <a href="<?= $c['link'] ?>" class="text-decoration-none">
        <div class="card card-dark border-0 h-100 hover-card">
          <div class="card-body d-flex align-items-center gap-3 py-3">
            <div class="rounded-circle bg-<?= $c['color'] ?> bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px">
              <i class="bi bi-<?= $c['icon'] ?> text-<?= $c['color'] ?>"></i>
            </div>
            <div>
              <p class="text-secondary small mb-0"><?= $c['label'] ?></p>
              <h4 class="fw-bold text-white mb-0"><?= $c['value'] ?></h4>
            </div>
          </div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts + Recent -->
  <div class="row g-4">

    <!-- Attendance Trend -->
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Attendance Trend (Last 14 Days)</h6>
          <a href="/reports/attendance" class="btn btn-link btn-sm text-secondary p-0">Full Report</a>
        </div>
        <div class="card-body"><canvas id="trendChart" height="100"></canvas></div>
      </div>
    </div>

    <!-- Department Summary -->
    <div class="col-lg-4">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Today by Department</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0 small">
              <thead><tr class="text-secondary"><th class="ps-4">Dept</th><th>Present</th><th class="pe-4">Rate</th></tr></thead>
              <tbody>
                <?php $depts = json_decode($deptData, true) ?: []; ?>
                <?php foreach ($depts as $d): ?>
                <?php $rate = (float)($d['rate']??0); $c = $rate>=90?'success':($rate>=75?'warning':'danger'); ?>
                <tr>
                  <td class="ps-4 text-white"><?= htmlspecialchars($d['department']??'') ?></td>
                  <td><?= (int)($d['present']??0) + (int)($d['late']??0) ?></td>
                  <td class="pe-4 text-<?= $c ?>"><?= number_format($rate,1) ?>%</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Attendance -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Recent Punches Today</h6>
          <a href="/attendance" class="btn btn-link btn-sm text-secondary p-0">View All</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-dark table-hover table-sm align-middle mb-0 small">
              <thead><tr class="text-secondary"><th class="ps-4">Employee</th><th>Status</th><th>Time In</th><th class="pe-4">Time Out</th></tr></thead>
              <tbody>
                <?php if (empty($recentAttendance)): ?>
                <tr><td colspan="4" class="text-center text-secondary py-3">No records for today yet.</td></tr>
                <?php else: ?>
                <?php foreach ($recentAttendance as $r): ?>
                <?php $sc = match($r['status']) { 'present'=>'success','late'=>'warning','absent'=>'danger','half_day'=>'info',default=>'secondary' }; ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-semibold text-white" style="font-size:.8rem"><?= htmlspecialchars($r['full_name']??'') ?></div>
                    <div class="text-secondary" style="font-size:.7rem"><?= $r['employee_code']??'' ?></div>
                  </td>
                  <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($r['status']) ?></span></td>
                  <td class="text-secondary"><?= $r['time_in'] ? substr($r['time_in'],0,5) : '—' ?></td>
                  <td class="pe-4 text-secondary"><?= $r['time_out'] ? substr($r['time_out'],0,5) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Device Status -->
    <div class="col-lg-3">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Devices</h6>
          <a href="/devices" class="btn btn-link btn-sm text-secondary p-0">Manage</a>
        </div>
        <div class="card-body">
          <?php $ds = $deviceStats; ?>
          <div class="d-flex justify-content-between mb-3">
            <div class="text-center">
              <h3 class="fw-bold text-success mb-0"><?= $ds['online']??0 ?></h3>
              <small class="text-secondary">Online</small>
            </div>
            <div class="text-center">
              <h3 class="fw-bold text-danger mb-0"><?= $ds['offline']??0 ?></h3>
              <small class="text-secondary">Offline</small>
            </div>
            <div class="text-center">
              <h3 class="fw-bold text-white mb-0"><?= $ds['total']??0 ?></h3>
              <small class="text-secondary">Total</small>
            </div>
          </div>
          <?php $tot = max(1,(int)($ds['total']??1)); $pct = round(($ds['online']??0)/$tot*100); ?>
          <div class="progress" style="height:6px;background:rgba(255,255,255,.1)">
            <div class="progress-bar bg-<?= $pct>=80?'success':($pct>=50?'warning':'danger') ?>" style="width:<?= $pct ?>%"></div>
          </div>
          <p class="text-secondary small text-center mt-2 mb-0"><?= $pct ?>% uptime</p>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-3">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0"><h6 class="text-white mb-0">Quick Actions</h6></div>
        <div class="card-body d-flex flex-column gap-2">
          <a href="/employees/create" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-person-plus me-2"></i>Add Employee</a>
          <a href="/leave/apply"      class="btn btn-outline-secondary btn-sm text-start"><i class="bi bi-calendar-plus me-2"></i>Apply Leave</a>
          <a href="/attendance/process" class="btn btn-outline-warning btn-sm text-start"><i class="bi bi-cpu me-2"></i>Process Attendance</a>
          <a href="/payroll/create"   class="btn btn-outline-success btn-sm text-start"><i class="bi bi-cash-stack me-2"></i>New Payroll Period</a>
          <a href="/reports"          class="btn btn-outline-info btn-sm text-start"><i class="bi bi-file-bar-graph me-2"></i>View Reports</a>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trend = <?= $dailyTrend ?>;
Chart.defaults.color = '#9ca3af';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: trend.map(d => { const dt=new Date(d.date); return dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short'}); }),
    datasets: [
      { label:'Present', data:trend.map(d=>d.present), borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,.1)', tension:.4, fill:true, pointRadius:2 },
      { label:'Absent',  data:trend.map(d=>d.absent),  borderColor:'#ef4444', tension:.4, fill:false, pointRadius:2 },
      { label:'Late',    data:trend.map(d=>d.late),    borderColor:'#f59e0b', tension:.4, fill:false, pointRadius:2 },
    ],
  },
  options: {
    responsive:true, interaction:{mode:'index',intersect:false},
    plugins:{legend:{labels:{boxWidth:12,padding:16}}},
    scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:'rgba(255,255,255,.05)'}}},
  },
});
</script>
<style>.hover-card:hover{transform:translateY(-2px);transition:transform .2s;}</style>
<?php $this->stop() ?>
