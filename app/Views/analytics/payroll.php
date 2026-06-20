<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Payroll Analytics</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/analytics" class="text-secondary">Analytics</a></li>
          <li class="breadcrumb-item active text-secondary">Payroll</li>
        </ol>
      </nav>
    </div>
  </div>

  <!-- Charts Row 1 -->
  <div class="row g-4 mb-4">

    <!-- Payroll Trend -->
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Payroll Trend (Last 12 Months)</h6>
          <div class="d-flex gap-3 small">
            <span><span class="me-1" style="display:inline-block;width:10px;height:10px;background:#6366f1;border-radius:2px"></span>Gross</span>
            <span><span class="me-1" style="display:inline-block;width:10px;height:10px;background:#22c55e;border-radius:2px"></span>Net</span>
            <span><span class="me-1" style="display:inline-block;width:10px;height:10px;background:#ef4444;border-radius:2px"></span>Deductions</span>
          </div>
        </div>
        <div class="card-body"><canvas id="trendChart" height="110"></canvas></div>
      </div>
    </div>

    <!-- Salary Distribution -->
    <div class="col-lg-4">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Salary Distribution</h6>
        </div>
        <div class="card-body d-flex flex-column justify-content-center">
          <canvas id="salDistChart" height="200"></canvas>
        </div>
      </div>
    </div>

  </div>

  <!-- Payroll Summary Table -->
  <?php $trendData = json_decode($trend, true) ?: []; ?>
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0">
      <h6 class="text-white mb-0">Period-wise Summary</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4">Period</th>
              <th>Employees</th>
              <th>Gross Earnings</th>
              <th class="text-danger">Deductions</th>
              <th class="text-success">Net Payroll</th>
              <th class="pe-4">Avg Net/Employee</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($trendData)): ?>
            <tr><td colspan="6" class="text-center text-secondary py-4">No processed payroll periods found.</td></tr>
            <?php else: ?>
            <?php foreach (array_reverse($trendData) as $p): ?>
            <tr>
              <td class="ps-4 text-white fw-semibold"><?= htmlspecialchars($p['period']) ?></td>
              <td><?= (int)($p['employees'] ?? 0) ?></td>
              <td><?= number_format((float)($p['gross'] ?? 0), 2) ?></td>
              <td class="text-danger"><?= number_format((float)($p['deductions'] ?? 0), 2) ?></td>
              <td class="text-success fw-semibold"><?= number_format((float)($p['net'] ?? 0), 2) ?></td>
              <td class="pe-4 text-secondary">
                <?= $p['employees'] > 0
                    ? number_format((float)($p['net'] ?? 0) / (int)$p['employees'], 2)
                    : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($trendData)): ?>
          <tfoot>
            <?php
              $totals = array_reduce($trendData, function ($carry, $p) {
                $carry['gross']  += (float)($p['gross'] ?? 0);
                $carry['ded']    += (float)($p['deductions'] ?? 0);
                $carry['net']    += (float)($p['net'] ?? 0);
                return $carry;
              }, ['gross' => 0, 'ded' => 0, 'net' => 0]);
            ?>
            <tr class="border-top border-secondary fw-semibold">
              <td class="ps-4 text-secondary">Totals</td>
              <td></td>
              <td><?= number_format($totals['gross'], 2) ?></td>
              <td class="text-danger"><?= number_format($totals['ded'], 2) ?></td>
              <td class="text-success"><?= number_format($totals['net'], 2) ?></td>
              <td></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trend   = <?= $trend ?>;
const salDist = <?= $salDist ?>;

Chart.defaults.color       = '#9ca3af';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

// Payroll Trend
new Chart(document.getElementById('trendChart'), {
  type: 'bar',
  data: {
    labels: trend.map(p => p.period),
    datasets: [
      { label:'Gross',      data: trend.map(p => parseFloat(p.gross)||0),      backgroundColor:'rgba(99,102,241,0.7)',  borderRadius:4, stack:'s' },
      { label:'Deductions', data: trend.map(p => parseFloat(p.deductions)||0), backgroundColor:'rgba(239,68,68,0.6)',   borderRadius:0, stack:'s' },
    ],
  },
  options: {
    responsive: true,
    interaction: { mode:'index', intersect:false },
    plugins: { legend:{ display:false } },
    scales: {
      x: { stacked:true, grid:{ display:false } },
      y: { stacked:true, beginAtZero:true, grid:{ color:'rgba(255,255,255,0.05)' },
           ticks:{ callback: v => v.toLocaleString() } },
    },
  },
});

// Salary Distribution
const PALETTE = ['#6366f1','#22c55e','#f59e0b','#ef4444','#3b82f6'];
new Chart(document.getElementById('salDistChart'), {
  type: 'doughnut',
  data: {
    labels: salDist.map(s => s.bracket),
    datasets: [{
      data: salDist.map(s => parseInt(s.employees)||0),
      backgroundColor: PALETTE,
      borderWidth: 0,
      hoverOffset: 6,
    }],
  },
  options: {
    responsive: true,
    cutout: '60%',
    plugins: {
      legend: { position:'bottom', labels:{ boxWidth:12, padding:12 } },
      tooltip: { callbacks:{ label: ctx => ` ${ctx.label}: ${ctx.parsed} employees` } },
    },
  },
});
</script>
<?php $this->stop() ?>
