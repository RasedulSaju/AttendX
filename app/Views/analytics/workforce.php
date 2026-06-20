<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Workforce Analytics</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/analytics" class="text-secondary">Analytics</a></li>
          <li class="breadcrumb-item active text-secondary">Workforce</li>
        </ol>
      </nav>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
      <select name="year" class="form-select form-select-dark form-select-sm" style="width:100px" onchange="this.form.submit()">
        <?php for ($y = date('Y'); $y >= date('Y')-4; $y--): ?>
        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <?php
      $summaryCards = [
        ['label'=>'Total Employees', 'value'=>$summary['total_employees']??0,                                  'color'=>'primary'],
        ['label'=>'Active',          'value'=>$summary['active']??0,                                           'color'=>'success'],
        ['label'=>'Inactive',        'value'=>$summary['inactive']??0,                                         'color'=>'warning'],
        ['label'=>'Terminated',      'value'=>$summary['terminated']??0,                                       'color'=>'danger'],
        ['label'=>'Male',            'value'=>$summary['male']??0,                                             'color'=>'info'],
        ['label'=>'Female',          'value'=>$summary['female']??0,                                           'color'=>'pink'],
        ['label'=>'Avg Age',         'value'=>round((float)($summary['avg_age']??0),1).' yrs',                 'color'=>'secondary'],
        ['label'=>'Joined This Year','value'=>$summary['joined_this_year']??0,                                 'color'=>'teal'],
      ];
    ?>
    <?php foreach ($summaryCards as $c): ?>
    <div class="col-6 col-md-3">
      <div class="card card-dark border-0 text-center">
        <div class="card-body py-3">
          <p class="text-secondary small mb-1"><?= $c['label'] ?></p>
          <h3 class="fw-bold text-white mb-0"><?= $c['value'] ?></h3>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts Row 1 -->
  <div class="row g-4 mb-4">
    <!-- Headcount by Department -->
    <div class="col-lg-5">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Headcount by Department</h6>
        </div>
        <div class="card-body">
          <canvas id="deptHeadcountChart" height="220"></canvas>
        </div>
      </div>
    </div>

    <!-- Monthly Joining Trend -->
    <div class="col-lg-7">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Monthly Joining Trend (Last 12 Months)</h6>
        </div>
        <div class="card-body">
          <canvas id="joiningTrendChart" height="130"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div class="row g-4">
    <!-- Turnover -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Terminations in <?= $year ?></h6>
        </div>
        <div class="card-body">
          <canvas id="turnoverChart" height="130"></canvas>
        </div>
      </div>
    </div>

    <!-- Salary Distribution -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Salary Distribution</h6>
        </div>
        <div class="card-body">
          <canvas id="salaryDistChart" height="130"></canvas>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const byDept   = <?= $byDept ?>;
const trend    = <?= $trend ?>;
const turnover = <?= $turnover ?>;
const salDist  = <?= $salaryDist ?>;

Chart.defaults.color        = '#9ca3af';
Chart.defaults.borderColor  = 'rgba(255,255,255,0.05)';

const PALETTE = ['#6366f1','#22c55e','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#06b6d4'];

// Headcount Doughnut
new Chart(document.getElementById('deptHeadcountChart'), {
  type: 'doughnut',
  data: {
    labels: byDept.map(d => d.department || 'Unknown'),
    datasets: [{
      data: byDept.map(d => d.headcount),
      backgroundColor: PALETTE,
      borderWidth: 0,
      hoverOffset: 8,
    }],
  },
  options: {
    responsive: true,
    cutout: '65%',
    plugins: {
      legend: { position: 'right', labels: { boxWidth: 12, padding: 14 } },
    },
  },
});

// Joining Trend
const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
new Chart(document.getElementById('joiningTrendChart'), {
  type: 'bar',
  data: {
    labels: trend.map(t => t.month),
    datasets: [{
      label: 'New Hires',
      data: trend.map(t => t.joined),
      backgroundColor: 'rgba(99,102,241,0.75)',
      borderRadius: 4,
    }],
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { stepSize: 1 } },
    },
  },
});

// Turnover
const monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const turnoverData = Array(12).fill(0);
turnover.forEach(t => { turnoverData[t.month - 1] = t.terminated; });

new Chart(document.getElementById('turnoverChart'), {
  type: 'bar',
  data: {
    labels: monthLabels,
    datasets: [{
      label: 'Terminations',
      data: turnoverData,
      backgroundColor: 'rgba(239,68,68,0.7)',
      borderRadius: 4,
    }],
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { stepSize: 1 } },
    },
  },
});

// Salary Distribution
new Chart(document.getElementById('salaryDistChart'), {
  type: 'bar',
  data: {
    labels: salDist.map(s => s.bracket),
    datasets: [{
      label: 'Employees',
      data: salDist.map(s => s.employees),
      backgroundColor: PALETTE,
      borderRadius: 4,
    }],
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { stepSize: 1 } },
    },
  },
});
</script>
<?php $this->stop() ?>
