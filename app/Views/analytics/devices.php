<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Device Activity Analytics</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/analytics" class="text-secondary">Analytics</a></li>
          <li class="breadcrumb-item active text-secondary">Devices</li>
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

  <!-- Device Cards -->
  <div class="row g-3 mb-4">
    <?php foreach ($deviceData as $d): ?>
    <?php
      $punchesPerDay = $d['active_days'] > 0 ? round($d['total_punches'] / $d['active_days']) : 0;
    ?>
    <div class="col-md-4">
      <div class="card card-dark border-0">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
              <h6 class="fw-semibold text-white mb-1"><?= htmlspecialchars($d['device_name']) ?></h6>
              <small class="text-secondary"><?= htmlspecialchars($d['ip_address'] ?? '') ?></small>
            </div>
            <div class="rounded-circle bg-primary bg-opacity-15 d-flex align-items-center justify-content-center"
                 style="width:40px;height:40px">
              <i class="bi bi-hdd-network text-primary"></i>
            </div>
          </div>
          <div class="row g-2 text-center">
            <div class="col-4">
              <p class="text-secondary x-small mb-0">Punches</p>
              <h5 class="fw-bold text-white mb-0"><?= number_format((int)$d['total_punches']) ?></h5>
            </div>
            <div class="col-4">
              <p class="text-secondary x-small mb-0">Active Days</p>
              <h5 class="fw-bold text-white mb-0"><?= (int)$d['active_days'] ?></h5>
            </div>
            <div class="col-4">
              <p class="text-secondary x-small mb-0">Avg/Day</p>
              <h5 class="fw-bold text-info mb-0"><?= $punchesPerDay ?></h5>
            </div>
          </div>
          <div class="mt-3">
            <div class="progress" style="height:4px;background:rgba(255,255,255,0.1)">
              <?php
                $maxPunches = max(array_column($deviceData, 'total_punches') ?: [1]);
                $pct = $maxPunches > 0 ? round($d['total_punches'] / $maxPunches * 100) : 0;
              ?>
              <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($deviceData)): ?>
    <div class="col-12">
      <div class="card card-dark border-0 text-center py-5">
        <i class="bi bi-hdd-network fs-2 text-secondary d-block mb-2"></i>
        <p class="text-secondary mb-0">No device activity in the selected period.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Activity Chart -->
  <?php if (!empty($deviceData)): ?>
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0">
      <h6 class="text-white mb-0">Total Punches by Device</h6>
    </div>
    <div class="card-body">
      <canvas id="deviceChart" height="80"></canvas>
    </div>
  </div>
  <?php endif; ?>

  <!-- Table View -->
  <?php if (!empty($deviceData)): ?>
  <div class="card card-dark border-0 mt-4">
    <div class="card-header bg-transparent border-0 pb-0">
      <h6 class="text-white mb-0">Device Summary Table</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4">Device</th>
              <th>IP Address</th>
              <th>Total Punches</th>
              <th>Active Days</th>
              <th class="pe-4">Avg Punches/Day</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($deviceData as $d): ?>
            <tr>
              <td class="ps-4 fw-semibold text-white"><?= htmlspecialchars($d['device_name']) ?></td>
              <td class="text-secondary font-monospace"><?= htmlspecialchars($d['ip_address'] ?? '—') ?></td>
              <td class="fw-semibold"><?= number_format((int)$d['total_punches']) ?></td>
              <td><?= (int)$d['active_days'] ?></td>
              <td class="pe-4 text-info">
                <?= $d['active_days'] > 0 ? round($d['total_punches'] / $d['active_days'], 1) : '0' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php if (!empty($deviceData)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const deviceData = <?= json_encode($deviceData) ?>;

Chart.defaults.color       = '#9ca3af';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

new Chart(document.getElementById('deviceChart'), {
  type: 'bar',
  data: {
    labels: deviceData.map(d => d.device_name),
    datasets: [
      {
        label: 'Total Punches',
        data: deviceData.map(d => parseInt(d.total_punches) || 0),
        backgroundColor: 'rgba(99,102,241,0.75)',
        borderRadius: 4,
      },
      {
        label: 'Active Days',
        data: deviceData.map(d => parseInt(d.active_days) || 0),
        backgroundColor: 'rgba(34,197,94,0.6)',
        borderRadius: 4,
        yAxisID: 'y2',
      },
    ],
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { boxWidth: 12, padding: 16 } } },
    scales: {
      x:  { grid: { display: false } },
      y:  { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => v.toLocaleString() } },
      y2: { beginAtZero: true, position: 'right', grid: { display: false }, title: { display: true, text: 'Active Days', color: '#9ca3af' } },
    },
  },
});
</script>
<?php endif; ?>
<?php $this->stop() ?>
