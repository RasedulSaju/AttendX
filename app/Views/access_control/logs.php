<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Access Logs</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/access-control" class="text-secondary">Access Control</a></li>
          <li class="breadcrumb-item active text-secondary">Logs</li>
        </ol>
      </nav>
    </div>
    <a href="/access-control/logs?<?= http_build_query(array_merge($filters, ['export' => 1])) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-download me-1"></i>Export CSV
    </a>
  </div>

  <!-- Filters -->
  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" action="/access-control/logs" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label text-secondary small">Employee</label>
          <select name="employee_id" class="form-select form-select-dark form-select-sm">
            <option value="">All Employees</option>
            <?php foreach ($employees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= ($filters['employee_id'] ?? 0) == $e['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_code'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label text-secondary small">Zone</label>
          <select name="zone_id" class="form-select form-select-dark form-select-sm">
            <option value="">All Zones</option>
            <?php foreach ($zones as $z): ?>
            <option value="<?= $z['id'] ?>" <?= ($filters['zone_id'] ?? 0) == $z['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($z['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label text-secondary small">Event Type</label>
          <select name="event_type" class="form-select form-select-dark form-select-sm">
            <option value="">All Events</option>
            <option value="access_granted" <?= ($filters['event_type'] ?? '') === 'access_granted' ? 'selected' : '' ?>>Granted</option>
            <option value="access_denied"  <?= ($filters['event_type'] ?? '') === 'access_denied'  ? 'selected' : '' ?>>Denied</option>
            <option value="alarm"          <?= ($filters['event_type'] ?? '') === 'alarm'          ? 'selected' : '' ?>>Alarm</option>
            <option value="tamper"         <?= ($filters['event_type'] ?? '') === 'tamper'         ? 'selected' : '' ?>>Tamper</option>
            <option value="door_open"      <?= ($filters['event_type'] ?? '') === 'door_open'      ? 'selected' : '' ?>>Door Open</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label text-secondary small">From</label>
          <input type="date" name="date_from" class="form-control form-control-dark form-control-sm"
                 value="<?= htmlspecialchars($filters['date_from'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label text-secondary small">To</label>
          <input type="date" name="date_to" class="form-control form-control-dark form-control-sm"
                 value="<?= htmlspecialchars($filters['date_to'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-1 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
            <i class="bi bi-search"></i>
          </button>
          <a href="/access-control/logs" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x"></i>
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Logs Table -->
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
      <h6 class="text-white mb-0">Results <span class="text-secondary fw-normal">(<?= number_format($total) ?> records)</span></h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4">Employee / Badge</th>
              <th>Zone / Door</th>
              <th>Event</th>
              <th>Direction</th>
              <th>Device</th>
              <th>Time</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr><td colspan="7" class="text-center text-secondary py-5">
                <i class="bi bi-journal-x fs-2 d-block mb-2"></i>No access logs match your filters.
              </td></tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
              <?php
                [$evtColor, $evtIcon] = match ($log['event_type']) {
                  'access_granted' => ['success', 'check-circle'],
                  'access_denied'  => ['danger', 'x-circle'],
                  'alarm'          => ['warning', 'bell'],
                  'tamper'         => ['danger', 'exclamation-triangle'],
                  default          => ['secondary', 'circle'],
                };
              ?>
              <tr>
                <td class="ps-4">
                  <?php if ($log['employee_name']): ?>
                    <div class="fw-semibold text-white"><?= htmlspecialchars($log['employee_name']) ?></div>
                    <small class="text-secondary"><?= $log['employee_code'] ?></small>
                  <?php elseif ($log['badge_number']): ?>
                    <span class="text-secondary">Badge: <?= htmlspecialchars($log['badge_number']) ?></span>
                  <?php else: ?>
                    <span class="text-secondary fst-italic">Unknown</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div><?= htmlspecialchars($log['zone_name'] ?? '—') ?></div>
                  <?php if ($log['door_name']): ?>
                  <small class="text-secondary"><?= htmlspecialchars($log['door_name']) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-<?= $evtColor ?> bg-opacity-20 text-<?= $evtColor ?> d-flex align-items-center gap-1" style="width:fit-content">
                    <i class="bi bi-<?= $evtIcon ?>"></i>
                    <?= str_replace('_', ' ', ucwords($log['event_type'], '_')) ?>
                  </span>
                </td>
                <td>
                  <?php if ($log['direction'] !== 'unknown'): ?>
                  <span class="badge bg-<?= $log['direction'] === 'in' ? 'success' : 'warning' ?> bg-opacity-20 text-<?= $log['direction'] === 'in' ? 'success' : 'warning' ?>">
                    <i class="bi bi-arrow-<?= $log['direction'] === 'in' ? 'right' : 'left' ?>"></i> <?= ucfirst($log['direction']) ?>
                  </span>
                  <?php else: ?>
                  <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-secondary"><?= htmlspecialchars($log['device_name'] ?? '—') ?></td>
                <td class="text-secondary">
                  <div><?= date('d M Y', strtotime($log['event_time'])) ?></div>
                  <div class="fw-semibold text-white"><?= date('H:i:s', strtotime($log['event_time'])) ?></div>
                </td>
                <td class="text-danger small">
                  <?= $log['deny_reason'] ? htmlspecialchars($log['deny_reason']) : '' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total > $perPage): ?>
      <div class="d-flex justify-content-between align-items-center px-4 py-3">
        <small class="text-secondary">
          Showing <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $total) ?> of <?= number_format($total) ?>
        </small>
        <?php
          $queryBase = http_build_query(array_filter(array_merge($filters, ['page' => '__PAGE__'])));
          $pages = ceil($total / $perPage);
        ?>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
            <li class="page-item <?= $i==$page?'active':'' ?>">
              <a class="page-link" href="?<?= str_replace('__PAGE__', $i, $queryBase) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $this->stop() ?>
