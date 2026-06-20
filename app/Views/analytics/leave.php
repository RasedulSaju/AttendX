<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Leave Analytics</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/analytics" class="text-secondary">Analytics</a></li>
          <li class="breadcrumb-item active text-secondary">Leave</li>
        </ol>
      </nav>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
      <label class="text-secondary small mb-0">Year:</label>
      <select name="year" class="form-select form-select-dark form-select-sm" style="width:100px" onchange="this.form.submit()">
        <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>

  <!-- Charts Row 1 -->
  <div class="row g-4 mb-4">

    <!-- By Type -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Leave Days by Type (<?= $year ?>)</h6>
        </div>
        <div class="card-body"><canvas id="typeChart" height="140"></canvas></div>
      </div>
    </div>

    <!-- By Department -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Leave Days by Department</h6>
        </div>
        <div class="card-body"><canvas id="deptChart" height="140"></canvas></div>
      </div>
    </div>

  </div>

  <!-- Leave Type Summary Table -->
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0">
      <h6 class="text-white mb-0">Leave Type Breakdown – <?= $year ?></h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4">Leave Type</th>
              <th>Applications</th>
              <th>Total Days</th>
              <th class="text-success">Approved</th>
              <th class="text-danger">Rejected</th>
              <th class="text-warning">Pending</th>
              <th class="pe-4">Approval Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $typeData = json_decode($byType, true) ?: [];
              if (empty($typeData)):
            ?>
            <tr><td colspan="7" class="text-center text-secondary py-4">No leave data for <?= $year ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($typeData as $t): ?>
            <?php
              $total    = (int)$t['applications'];
              $approved = (int)$t['approved'];
              $approvalRate = $total > 0 ? round($approved / $total * 100) : 0;
            ?>
            <tr>
              <td class="ps-4 fw-semibold text-white"><?= htmlspecialchars($t['leave_type']) ?></td>
              <td><?= $total ?></td>
              <td class="fw-semibold"><?= number_format((float)$t['total_days'], 1) ?></td>
              <td class="text-success"><?= $t['approved'] ?></td>
              <td class="text-danger"><?= $t['rejected'] ?></td>
              <td class="text-warning"><?= $t['pending'] ?></td>
              <td class="pe-4">
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="height:6px;background:rgba(255,255,255,0.1)">
                    <div class="progress-bar bg-<?= $approvalRate >= 75 ? 'success' : 'warning' ?>"
                         style="width:<?= $approvalRate ?>%"></div>
                  </div>
                  <small class="text-secondary"><?= $approvalRate ?>%</small>
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
const byType = <?= $byType ?>;
const byDept = <?= $byDept ?>;

Chart.defaults.color       = '#9ca3af';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

const PALETTE = ['#6366f1','#22c55e','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6'];

// Leave by Type – Doughnut
new Chart(document.getElementById('typeChart'), {
  type: 'doughnut',
  data: {
    labels: byType.map(t => t.leave_type),
    datasets: [{
      data: byType.map(t => parseFloat(t.total_days) || 0),
      backgroundColor: PALETTE,
      borderWidth: 0,
      hoverOffset: 6,
    }],
  },
  options: {
    responsive: true,
    cutout: '65%',
    plugins: {
      legend: { position: 'right', labels: { boxWidth: 12, padding: 14 } },
      tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} days` } },
    },
  },
});

// Leave by Department – Horizontal Bar
new Chart(document.getElementById('deptChart'), {
  type: 'bar',
  data: {
    labels: byDept.map(d => d.department || 'Unknown'),
    datasets: [
      {
        label: 'Days Taken',
        data: byDept.map(d => parseFloat(d.days_taken) || 0),
        backgroundColor: 'rgba(99,102,241,0.7)',
        borderRadius: 4,
      },
      {
        label: 'Employees',
        data: byDept.map(d => parseInt(d.employees_on_leave) || 0),
        backgroundColor: 'rgba(34,197,94,0.5)',
        borderRadius: 4,
      },
    ],
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { boxWidth: 12 } } },
    scales: {
      x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
      y: { grid: { display: false } },
    },
  },
});
</script>
<?php $this->stop() ?>
