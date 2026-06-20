<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Attendance Analytics</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/analytics" class="text-secondary">Analytics</a></li>
          <li class="breadcrumb-item active text-secondary">Attendance</li>
        </ol>
      </nav>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-end">
      <div>
        <label class="form-label text-secondary small mb-1">From</label>
        <input type="date" name="date_from" class="form-control form-control-dark form-control-sm"
               value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div>
        <label class="form-label text-secondary small mb-1">To</label>
        <input type="date" name="date_to" class="form-control form-control-dark form-control-sm"
               value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    </form>
  </div>

  <!-- Summary KPIs -->
  <div class="row g-3 mb-4">
    <?php
      $kpis = [
        ['label' => 'Employees Tracked',  'value' => $summary['employees_tracked'] ?? 0,             'color' => 'primary',   'icon' => 'people'],
        ['label' => 'Attendance Rate',     'value' => ($summary['attendance_rate'] ?? 0).'%',         'color' => 'success',   'icon' => 'graph-up'],
        ['label' => 'Total Present',       'value' => $summary['present'] ?? 0,                       'color' => 'success',   'icon' => 'person-check'],
        ['label' => 'Total Absent',        'value' => $summary['absent'] ?? 0,                        'color' => 'danger',    'icon' => 'person-x'],
        ['label' => 'Late Arrivals',       'value' => $summary['late'] ?? 0,                          'color' => 'warning',   'icon' => 'clock-history'],
        ['label' => 'Half Days',           'value' => $summary['half_day'] ?? 0,                      'color' => 'info',      'icon' => 'circle-half'],
        ['label' => 'On Leave',            'value' => $summary['on_leave'] ?? 0,                      'color' => 'secondary', 'icon' => 'calendar-check'],
        ['label' => 'Avg OT (min/person)', 'value' => round((float)($summary['avg_overtime_minutes'] ?? 0), 1), 'color' => 'teal', 'icon' => 'stopwatch'],
      ];
    ?>
    <?php foreach ($kpis as $k): ?>
    <div class="col-6 col-md-3">
      <div class="card card-dark border-0">
        <div class="card-body d-flex align-items-center gap-3 py-3">
          <div class="rounded-circle bg-<?= $k['color'] ?> bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:40px;height:40px">
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
    <!-- Daily Trend -->
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Daily Attendance Trend</h6>
          <div class="d-flex gap-3 small">
            <span class="text-success"><span class="me-1" style="display:inline-block;width:10px;height:10px;background:#22c55e;border-radius:2px"></span>Present</span>
            <span class="text-danger"><span class="me-1" style="display:inline-block;width:10px;height:10px;background:#ef4444;border-radius:2px"></span>Absent</span>
            <span class="text-warning"><span class="me-1" style="display:inline-block;width:10px;height:10px;background:#f59e0b;border-radius:2px"></span>Late</span>
          </div>
        </div>
        <div class="card-body"><canvas id="dailyChart" height="100"></canvas></div>
      </div>
    </div>
    <!-- Rate Gauge -->
    <div class="col-lg-4">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Overall Rate</h6>
        </div>
        <div class="card-body d-flex flex-column align-items-center justify-content-center">
          <?php $rate = round((float)($summary['attendance_rate'] ?? 0), 1); ?>
          <div class="position-relative mb-3" style="width:140px;height:140px">
            <canvas id="gaugeChart" width="140" height="140"></canvas>
            <div class="position-absolute top-50 start-50 translate-middle text-center">
              <h3 class="fw-bold text-white mb-0"><?= $rate ?>%</h3>
              <small class="text-secondary">Rate</small>
            </div>
          </div>
          <div class="text-center">
            <span class="badge bg-<?= $rate >= 90 ? 'success' : ($rate >= 75 ? 'warning' : 'danger') ?> fs-6">
              <?= $rate >= 90 ? 'Excellent' : ($rate >= 75 ? 'Moderate' : 'Poor') ?>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div class="row g-4 mb-4">
    <!-- By Department -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Attendance Rate by Department</h6>
        </div>
        <div class="card-body"><canvas id="deptChart" height="140"></canvas></div>
      </div>
    </div>
    <!-- Overtime by Department -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Overtime by Department (hours)</h6>
        </div>
        <div class="card-body"><canvas id="otChart" height="140"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Late Arrivals Table -->
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0">
      <h6 class="text-white mb-0">Top Late Arrivals</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4">#</th>
              <th>Employee</th>
              <th>Department</th>
              <th>Late Count</th>
              <th>Avg Late (min)</th>
              <th>Total Late (min)</th>
              <th class="pe-4">Severity</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($lateTop)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-4">No late arrivals in this period.</td></tr>
            <?php else: ?>
            <?php foreach ($lateTop as $i => $emp): ?>
            <?php $color = $emp['late_count'] >= 10 ? 'danger' : ($emp['late_count'] >= 5 ? 'warning' : 'info'); ?>
            <tr>
              <td class="ps-4 text-secondary"><?= $i + 1 ?></td>
              <td>
                <div class="fw-semibold text-white"><?= htmlspecialchars($emp['full_name']) ?></div>
                <small class="text-secondary"><?= $emp['employee_code'] ?></small>
              </td>
              <td class="text-secondary"><?= htmlspecialchars($emp['department'] ?? '—') ?></td>
              <td class="fw-semibold text-<?= $color ?>"><?= $emp['late_count'] ?></td>
              <td><?= round((float)$emp['avg_late_minutes'], 1) ?></td>
              <td><?= number_format((float)$emp['total_late_minutes']) ?></td>
              <td class="pe-4">
                <div class="progress" style="height:6px;background:rgba(255,255,255,0.1);width:100px">
                  <div class="progress-bar bg-<?= $color ?>"
                       style="width:<?= min(100, $emp['late_count'] * 5) ?>%"></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const daily    = <?= $daily ?>;
const byDept   = <?= $byDept ?>;
const overtime = <?= $overtime ?>;

Chart.defaults.color       = '#9ca3af';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

// Daily Trend
new Chart(document.getElementById('dailyChart'), {
  type: 'line',
  data: {
    labels: daily.map(d => {
      const dt = new Date(d.date);
      return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
    }),
    datasets: [
      { label:'Present', data: daily.map(d=>d.present), borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,0.1)', tension:0.4, fill:true, pointRadius:2 },
      { label:'Absent',  data: daily.map(d=>d.absent),  borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.05)',  tension:0.4, fill:false, pointRadius:2 },
      { label:'Late',    data: daily.map(d=>d.late),    borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.05)', tension:0.4, fill:false, pointRadius:2 },
    ],
  },
  options: {
    responsive: true,
    interaction: { mode:'index', intersect:false },
    plugins: { legend:{ display:false } },
    scales: {
      x: { grid:{ display:false } },
      y: { beginAtZero:true, grid:{ color:'rgba(255,255,255,0.05)' } },
    },
  },
});

// Gauge Doughnut
const rate = <?= $rate ?>;
new Chart(document.getElementById('gaugeChart'), {
  type: 'doughnut',
  data: {
    datasets: [{
      data: [rate, 100 - rate],
      backgroundColor: [
        rate >= 90 ? '#22c55e' : rate >= 75 ? '#f59e0b' : '#ef4444',
        'rgba(255,255,255,0.08)',
      ],
      borderWidth: 0,
    }],
  },
  options: {
    responsive: false,
    cutout: '78%',
    plugins: { legend:{ display:false }, tooltip:{ enabled:false } },
  },
});

// Department Rate Bar
new Chart(document.getElementById('deptChart'), {
  type: 'bar',
  data: {
    labels: byDept.map(d => d.department || 'Unknown'),
    datasets: [{
      label: 'Rate %',
      data: byDept.map(d => parseFloat(d.rate) || 0),
      backgroundColor: byDept.map(d => {
        const r = parseFloat(d.rate) || 0;
        return r >= 90 ? 'rgba(34,197,94,0.7)' : r >= 75 ? 'rgba(245,158,11,0.7)' : 'rgba(239,68,68,0.7)';
      }),
      borderRadius: 4,
    }],
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: { legend:{ display:false } },
    scales: {
      x: { min:0, max:100, grid:{ color:'rgba(255,255,255,0.05)' },
           ticks:{ callback: v => v+'%' } },
      y: { grid:{ display:false } },
    },
  },
});

// Overtime Bar
new Chart(document.getElementById('otChart'), {
  type: 'bar',
  data: {
    labels: overtime.map(d => d.department || 'Unknown'),
    datasets: [{
      label: 'OT Hours',
      data: overtime.map(d => Math.round(d.total_overtime_minutes / 60 * 10) / 10),
      backgroundColor: 'rgba(99,102,241,0.7)',
      borderRadius: 4,
    }],
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: { legend:{ display:false } },
    scales: {
      x: { beginAtZero:true, grid:{ color:'rgba(255,255,255,0.05)' } },
      y: { grid:{ display:false } },
    },
  },
});
</script>
<?php $this->stop() ?>
