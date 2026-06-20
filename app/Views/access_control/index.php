<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Access Control</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item active text-secondary">Access Control</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <a href="/access-control/zones" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-map me-1"></i>Zones
      </a>
      <a href="/access-control/visitors" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-person-badge me-1"></i>Visitors
      </a>
      <a href="/access-control/logs" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-journal-text me-1"></i>All Logs
      </a>
    </div>
  </div>

  <!-- KPI Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card card-dark border-0">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success bg-opacity-15 p-3">
              <i class="bi bi-check-circle text-success fs-5"></i>
            </div>
            <div>
              <p class="text-secondary small mb-0">Granted Today</p>
              <h3 class="fw-bold text-white mb-0"><?= (int)($stats['granted'] ?? 0) ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-dark border-0">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-danger bg-opacity-15 p-3">
              <i class="bi bi-x-circle text-danger fs-5"></i>
            </div>
            <div>
              <p class="text-secondary small mb-0">Denied Today</p>
              <h3 class="fw-bold text-white mb-0"><?= (int)($stats['denied'] ?? 0) ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-dark border-0">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning bg-opacity-15 p-3">
              <i class="bi bi-bell text-warning fs-5"></i>
            </div>
            <div>
              <p class="text-secondary small mb-0">Alarms Today</p>
              <h3 class="fw-bold text-white mb-0"><?= (int)($stats['alarms'] ?? 0) ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-dark border-0">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-info bg-opacity-15 p-3">
              <i class="bi bi-door-open text-info fs-5"></i>
            </div>
            <div>
              <p class="text-secondary small mb-0">Active Zones</p>
              <h3 class="fw-bold text-white mb-0"><?= count($zones) ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- Zone Cards -->
    <div class="col-lg-5">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Access Zones</h6>
          <a href="/access-control/zones" class="btn btn-link btn-sm text-secondary p-0">Manage</a>
        </div>
        <div class="card-body">
          <?php if (empty($zones)): ?>
            <p class="text-secondary text-center py-3">No zones configured.</p>
          <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($zones as $z): ?>
            <div class="d-flex align-items-center justify-content-between p-3 rounded" style="background:rgba(255,255,255,0.04)">
              <div class="d-flex align-items-center gap-3">
                <?php
                  $secColor = match ((int)$z['security_level']) {
                    1 => 'success', 2 => 'info', 3 => 'warning', 4 => 'danger', default => 'secondary'
                  };
                  $secLabel = match ((int)$z['security_level']) {
                    1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Critical', default => 'Unknown'
                  };
                ?>
                <div class="rounded-circle bg-<?= $secColor ?> bg-opacity-20 d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                  <i class="bi bi-shield-lock text-<?= $secColor ?>"></i>
                </div>
                <div>
                  <div class="fw-semibold text-white"><?= htmlspecialchars($z['name']) ?></div>
                  <small class="text-secondary"><?= $z['zone_code'] ?> &bull; <?= $z['door_count'] ?> door<?= $z['door_count'] != 1 ? 's' : '' ?></small>
                </div>
              </div>
              <div class="text-end">
                <span class="badge bg-<?= $secColor ?> bg-opacity-25 text-<?= $secColor ?>"><?= $secLabel ?></span>
                <div class="mt-1">
                  <a href="/access-control/rules/<?= $z['id'] ?>" class="btn btn-xs btn-outline-secondary">Rules</a>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Live Feed -->
    <div class="col-lg-7">
      <div class="card card-dark border-0 h-100">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success rounded-circle p-1" id="liveDot"></span>
            <h6 class="text-white mb-0">Live Access Feed</h6>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <small class="text-secondary" id="lastUpdated"></small>
            <button class="btn btn-outline-secondary btn-sm" onclick="togglePause()" id="pauseBtn">
              <i class="bi bi-pause-fill"></i>
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <div id="liveFeed" style="height:420px;overflow-y:auto">
            <?php foreach ($recentLogs as $log): ?>
            <div class="d-flex align-items-start gap-3 px-4 py-3 border-bottom border-secondary log-entry">
              <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                   style="width:32px;height:32px;background:rgba(<?= $log['event_type'] === 'access_granted' ? '34,197,94' : ($log['event_type'] === 'access_denied' ? '239,68,68' : '234,179,8') ?>,0.15)">
                <i class="bi bi-<?= $log['event_type'] === 'access_granted' ? 'check' : ($log['event_type'] === 'access_denied' ? 'x' : 'bell') ?>
                   text-<?= $log['event_type'] === 'access_granted' ? 'success' : ($log['event_type'] === 'access_denied' ? 'danger' : 'warning') ?> small"></i>
              </div>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center justify-content-between">
                  <span class="fw-semibold text-white small"><?= htmlspecialchars($log['employee_name'] ?? $log['badge_number'] ?? 'Unknown') ?></span>
                  <small class="text-secondary flex-shrink-0 ms-2"><?= date('H:i:s', strtotime($log['event_time'])) ?></small>
                </div>
                <small class="text-secondary d-block">
                  <?= htmlspecialchars($log['zone_name'] ?? 'Unknown Zone') ?>
                  <?php if ($log['door_name']): ?> &bull; <?= htmlspecialchars($log['door_name']) ?><?php endif; ?>
                  &bull; <span class="text-<?= $log['direction'] === 'in' ? 'success' : ($log['direction'] === 'out' ? 'danger' : 'secondary') ?>"><?= ucfirst($log['direction']) ?></span>
                </small>
                <?php if ($log['deny_reason']): ?>
                <small class="text-danger"><?= htmlspecialchars($log['deny_reason']) ?></small>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
let paused = false;
let lastEventTime = null;

function togglePause() {
  paused = !paused;
  const btn = document.getElementById('pauseBtn');
  btn.innerHTML = paused ? '<i class="bi bi-play-fill"></i>' : '<i class="bi bi-pause-fill"></i>';
  document.getElementById('liveDot').className = paused
    ? 'badge bg-secondary rounded-circle p-1'
    : 'badge bg-success rounded-circle p-1';
}

async function fetchLiveStats() {
  if (paused) return;
  try {
    const r    = await fetch('/api/v1/access/live-stats');
    const data = await r.json();
    document.getElementById('lastUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString();

    if (data.recent && data.recent.length > 0) {
      const feed     = document.getElementById('liveFeed');
      const newTime  = data.recent[0].event_time;
      if (lastEventTime && newTime > lastEventTime) {
        const newEntries = data.recent.filter(l => l.event_time > lastEventTime);
        newEntries.reverse().forEach(log => {
          const div = buildLogEntry(log);
          feed.insertBefore(div, feed.firstChild);
        });
        // Trim old entries
        while (feed.children.length > 50) {
          feed.removeChild(feed.lastChild);
        }
      }
      lastEventTime = newTime;
    }
  } catch (e) { /* silent */ }
}

function buildLogEntry(log) {
  const isGranted = log.event_type === 'access_granted';
  const isDenied  = log.event_type === 'access_denied';
  const color     = isGranted ? '34,197,94' : isDenied ? '239,68,68' : '234,179,8';
  const textColor = isGranted ? 'success' : isDenied ? 'danger' : 'warning';
  const icon      = isGranted ? 'check' : isDenied ? 'x' : 'bell';
  const time      = new Date(log.event_time).toLocaleTimeString();

  const div = document.createElement('div');
  div.className = 'd-flex align-items-start gap-3 px-4 py-3 border-bottom border-secondary log-entry';
  div.style.animation = 'fadeIn 0.3s ease';
  div.innerHTML = `
    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
         style="width:32px;height:32px;background:rgba(${color},0.15)">
      <i class="bi bi-${icon} text-${textColor} small"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="d-flex align-items-center justify-content-between">
        <span class="fw-semibold text-white small">${log.employee_name || log.badge_number || 'Unknown'}</span>
        <small class="text-secondary flex-shrink-0 ms-2">${time}</small>
      </div>
      <small class="text-secondary d-block">
        ${log.zone_name || 'Unknown Zone'}${log.door_name ? ' &bull; ' + log.door_name : ''}
      </small>
      ${log.deny_reason ? `<small class="text-danger">${log.deny_reason}</small>` : ''}
    </div>`;
  return div;
}

// Initialize lastEventTime from most recent log
const entries = document.querySelectorAll('.log-entry');
if (entries.length > 0) {
  // We don't have inline time easily; just set to current
  lastEventTime = new Date().toISOString().replace('T', ' ').substring(0, 19);
}

// Poll every 8 seconds
setInterval(fetchLiveStats, 8000);
</script>

<style>
@keyframes fadeIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
</style>
<?php $this->stop() ?>
