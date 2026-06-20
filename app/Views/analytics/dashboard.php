<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Analytics Dashboard</h4>
      <p class="text-secondary small mb-0">Month-to-date &bull; <?= date('F Y') ?></p>
    </div>
    <div class="d-flex gap-2">
      <a href="/analytics/attendance" class="btn btn-outline-secondary btn-sm">Attendance</a>
      <a href="/analytics/workforce"  class="btn btn-outline-secondary btn-sm">Workforce</a>
      <a href="/analytics/leave"      class="btn btn-outline-secondary btn-sm">Leave</a>
      <a href="/analytics/payroll"    class="btn btn-outline-secondary btn-sm">Payroll</a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <?php
      $kpis = [
        ['label'=>'Attendance Rate',    'value'=>($kpi['attendance_rate']??0).'%', 'icon'=>'graph-up',        'color'=>'success'],
        ['label'=>'Present Today',      'value'=>$kpi['present_today']??0,         'icon'=>'person-check',    'color'=>'info'],
        ['label'=>'Absent Today',       'value'=>$kpi['absent_today']??0,          'icon'=>'person-x',        'color'=>'danger'],
        ['label'=>'Late Today',         'value'=>$kpi['late_today']??0,            'icon'=>'clock-history',   'color'=>'warning'],
        ['label'=>'Total Employees',    'value'=>$kpi['total_employees']??0,       'icon'=>'people',          'color'=>'primary'],
        ['label'=>'Active Employees',   'value'=>$kpi['active_employees']??0,      'icon'=>'person-check-fill','color'=>'success'],
        ['label'=>'Pending Leaves',     'value'=>$kpi['pending_leaves']??0,        'icon'=>'calendar-x',      'color'=>'warning'],
        ['label'=>'Offline Devices',    'value'=>$kpi['offline_devices']??0,       'icon'=>'hdd-network',     'color'=>($kpi['offline_devices']??0)>0?'danger':'success'],
      ];
    ?>
    <?php foreach ($kpis as $k): ?>
    <div class="col-6 col-md-3">
      <div class="card card-dark border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle bg-<?= $k['color'] ?> bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px">
            <i class="bi bi-<?= $k['icon'] ?> text-<?= $k['color'] ?>"></i>
          </div>
          <div>
            <p class="text-secondary small mb-0"><?= $k['label'] ?></p>
            <h4 class="fw-bold text-white mb-0"><?= $k['value'] ?></h4>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts Row 1 -->
  <div class="row g-4 mb-4">
    <!-- Attendance Trend -->
    <div class="col-lg-8">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Attendance Trend (Last 30 Days)</h6>
          <div class="d-flex gap-3 align-items-center">
            <span class="d-flex align-items-center gap-1 small text-success"><span style="width:10px;height:10px;background:#22c55e;border-radius:2px;display:inline-block"></span>Present</span>
            <span class="d-flex align-items-center gap-1 small text-danger"><span style="width:10px;height:10px;background:#ef4444;border-radius:2px;display:inline-block"></span>Absent</span>
            <span class="d-flex align-items-center gap-1 small text-warning"><span style="width:10px;height:10px;background:#f59e0b;border-radius:2px;display:inline-block"></span>Late</span>
          </div>
        </div>
        <div class="card-body">
          <canvas id="attendanceTrendChart" height="90"></canvas>
        </div>
      </div>
    </div>

    <!-- Department Attendance -->
    <div class="col-lg-4">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Attendance by Department</h6>
        </div>
        <div class="card-body d-flex flex-column justify-content-center">
          <canvas id="deptChart" height="200"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div class="row g-4">
    <!-- Payroll Trend -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Payroll Trend (Last 6 Months)</h6>
        </div>
        <div class="card-body">
          <canvas id="payrollChart" height="120"></canvas>
        </div>
      </div>
    </div>

    <!-- Department Attendance Rate Table -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Department Rates (MTD)</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 small">
              <thead>
                <tr class="text-secondary">
                  <th class="ps-4">Department</th>
                  <th>Rate</th>
                  <th class="pe-4">Trend</th>
                </tr>
              </thead>
              <tbody id="deptTable">
                <?php
                  $deptArr = json_decode($deptData, true) ?: [];
                  foreach ($deptArr as $d):
                    $rate = (float)($d['rate'] ?? 0);
                    $barColor = $rate >= 90 ? 'success' : ($rate >= 75 ? 'warning' : 'danger');
                ?>
                <tr>
                  <td class="ps-4 text-white"><?= htmlspecialchars($d['department'] ?? '—') ?></td>
                  <td class="fw-semibold text-<?= $barColor ?>"><?= number_format($rate, 1) ?>%</td>
                  <td class="pe-4">
                    <div class="progress" style="height:6px;background:rgba(255,255,255,0.1)">
                      <div class="progress-bar bg-<?= $barColor ?>" style="width:<?= min(100,$rate) ?>%"></div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trend      = <?= $trend ?>;
const deptData   = <?= $deptData ?>;
const payData    = <?= $payrollTrend ?>;

Chart.defaults.color = '#9ca3af';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

// Attendance Trend
new Chart(document.getElementById('attendanceTrendChart'), {
  type: 'line',
  data: {
    labels: trend.map(d => {
      const dt = new Date(d.date);
      return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
    }),
    datasets: [
      {
        label: 'Present', data: trend.map(d => d.present),
        borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)',
        tension: 0.4, fill: true, pointRadius: 2,
      },
      {
        label: 'Absent', data: trend.map(d => d.absent),
        borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.05)',
        tension: 0.4, fill: false, pointRadius: 2,
      },
      {
        label: 'Late', data: trend.map(d => d.late),
        borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.05)',
        tension: 0.4, fill: false, pointRadius: 2,
      },
    ],
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
    },
  },
});

// Department Doughnut
new Chart(document.getElementById('deptChart'), {
  type: 'doughnut',
  data: {
    labels: deptData.map(d => d.department || 'Unknown'),
    datasets: [{
      data: deptData.map(d => d.present + d.late),
      backgroundColor: [
        '#6366f1','#22c55e','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6',
      ],
      borderWidth: 0,
      hoverOffset: 6,
    }],
  },
  options: {
    responsive: true,
    cutout: '70%',
    plugins: {
      legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } },
    },
  },
});

// Payroll Bar
new Chart(document.getElementById('payrollChart'), {
  type: 'bar',
  data: {
    labels: payData.map(p => p.period),
    datasets: [
      {
        label: 'Gross',
        data: payData.map(p => parseFloat(p.gross) || 0),
        backgroundColor: 'rgba(99,102,241,0.7)',
        borderRadius: 4,
      },
      {
        label: 'Net',
        data: payData.map(p => parseFloat(p.net) || 0),
        backgroundColor: 'rgba(34,197,94,0.7)',
        borderRadius: 4,
      },
    ],
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { boxWidth: 12, padding: 16 } } },
    scales: {
      x: { grid: { display: false } },
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(255,255,255,0.05)' },
        ticks: { callback: v => v.toLocaleString() },
      },
    },
  },
});
</script>
<?php $this->stop() ?>
