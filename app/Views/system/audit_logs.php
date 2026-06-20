<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Audit Logs</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/system/settings" class="text-secondary">System</a></li>
          <li class="breadcrumb-item active text-secondary">Audit Logs</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <a href="/system/audit/export?<?= http_build_query($filters) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
      </a>
      <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#purgeModal">
        <i class="bi bi-trash me-1"></i>Purge Old Logs
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" action="/system/audit" class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label text-secondary small">User</label>
          <select name="user_id" class="form-select form-select-dark form-select-sm">
            <option value="">All Users</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= ($filters['user_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label text-secondary small">Module</label>
          <select name="module" class="form-select form-select-dark form-select-sm">
            <option value="">All Modules</option>
            <?php foreach ($modules as $m): ?>
            <option value="<?= $m ?>" <?= ($filters['module'] ?? '') === $m ? 'selected' : '' ?>>
              <?= ucfirst($m) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label text-secondary small">Action</label>
          <input type="text" name="action" class="form-control form-control-dark form-control-sm"
                 placeholder="e.g. login" value="<?= htmlspecialchars($filters['action'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label text-secondary small">From</label>
          <input type="date" name="date_from" class="form-control form-control-dark form-control-sm"
                 value="<?= htmlspecialchars($filters['date_from'] ?? date('Y-m-d', strtotime('-7 days'))) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label text-secondary small">To</label>
          <input type="date" name="date_to" class="form-control form-control-dark form-control-sm"
                 value="<?= htmlspecialchars($filters['date_to'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i></button>
          <a href="/system/audit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Logs Table -->
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
      <h6 class="text-white mb-0">
        Results <span class="text-secondary fw-normal">(<?= number_format($total) ?> records)</span>
      </h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4" style="width:40px">#</th>
              <th>User</th>
              <th>Module</th>
              <th>Action</th>
              <th>Description</th>
              <th>IP Address</th>
              <th>Time</th>
              <th class="pe-4 text-end">Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr><td colspan="8" class="text-center text-secondary py-5">
                <i class="bi bi-journal-x fs-2 d-block mb-2"></i>No logs found for selected filters.
              </td></tr>
            <?php else: ?>
              <?php foreach ($logs as $i => $log): ?>
              <?php
                $moduleColor = match ($log['module']) {
                  'auth'    => 'info',
                  'system'  => 'warning',
                  'payroll' => 'success',
                  'access'  => 'primary',
                  default   => 'secondary',
                };
              ?>
              <tr>
                <td class="ps-4 text-secondary"><?= ($page-1)*$perPage + $i + 1 ?></td>
                <td>
                  <span class="text-white"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                </td>
                <td>
                  <span class="badge bg-<?= $moduleColor ?> bg-opacity-20 text-<?= $moduleColor ?>">
                    <?= ucfirst($log['module']) ?>
                  </span>
                </td>
                <td><code class="text-secondary"><?= htmlspecialchars($log['action']) ?></code></td>
                <td class="text-secondary" style="max-width:280px">
                  <span class="d-inline-block text-truncate" style="max-width:280px" title="<?= htmlspecialchars($log['description'] ?? '') ?>">
                    <?= htmlspecialchars($log['description'] ?? '—') ?>
                  </span>
                </td>
                <td class="text-secondary font-monospace"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                <td class="text-secondary">
                  <div><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                  <div class="text-white"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                </td>
                <td class="pe-4 text-end">
                  <button class="btn btn-xs btn-outline-secondary" onclick="viewDetail(<?= $log['id'] ?>)">
                    <i class="bi bi-eye"></i>
                  </button>
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
          $queryBase = http_build_query(array_merge($filters, ['page' => '__PAGE__']));
          $pages     = ceil($total / $perPage);
        ?>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
            <li class="page-item <?= $i==$page?'active':'' ?>">
              <a class="page-link" href="?<?= str_replace('__PAGE__',$i,$queryBase) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-white">Audit Log Detail</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailBody">
        <div class="text-center text-secondary py-4"><div class="spinner-border spinner-border-sm"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- Purge Modal -->
<div class="modal fade" id="purgeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-white">Purge Old Logs</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle me-2"></i>
          This permanently deletes old audit logs. This cannot be undone.
        </div>
        <div>
          <label class="form-label text-secondary">Delete logs older than</label>
          <select id="purgeDays" class="form-select form-select-dark">
            <option value="30">30 days</option>
            <option value="60">60 days</option>
            <option value="90" selected>90 days</option>
            <option value="180">180 days</option>
            <option value="365">1 year</option>
          </select>
        </div>
        <div id="purgeResult" class="mt-3"></div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="purge()">Purge Logs</button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';

async function viewDetail(id) {
  document.getElementById('detailBody').innerHTML = '<div class="text-center text-secondary py-4"><div class="spinner-border spinner-border-sm"></div></div>';
  new bootstrap.Modal(document.getElementById('detailModal')).show();

  const r    = await fetch('/api/v1/audit/' + id);
  const data = await r.json();

  if (!data.success) {
    document.getElementById('detailBody').innerHTML = '<p class="text-danger">Failed to load detail.</p>';
    return;
  }

  const log = data.log;
  const fmt = v => v ? '<pre class="bg-black rounded p-3 small text-success mb-0" style="max-height:200px;overflow-y:auto">' + JSON.stringify(v, null, 2) + '</pre>' : '<span class="text-secondary">—</span>';

  document.getElementById('detailBody').innerHTML = `
    <table class="table table-sm table-dark table-borderless mb-0">
      <tr><td class="text-secondary" style="width:130px">ID</td><td>#${log.id}</td></tr>
      <tr><td class="text-secondary">User</td><td>${log.user_name || 'System'} ${log.user_email ? '<small class="text-secondary">(' + log.user_email + ')</small>' : ''}</td></tr>
      <tr><td class="text-secondary">Module</td><td>${log.module}</td></tr>
      <tr><td class="text-secondary">Action</td><td><code>${log.action}</code></td></tr>
      <tr><td class="text-secondary">Description</td><td>${log.description || '—'}</td></tr>
      <tr><td class="text-secondary">IP Address</td><td><code>${log.ip_address || '—'}</code></td></tr>
      <tr><td class="text-secondary">User Agent</td><td class="small text-secondary">${log.user_agent || '—'}</td></tr>
      <tr><td class="text-secondary">Time</td><td>${log.created_at}</td></tr>
      <tr><td class="text-secondary">Old Values</td><td>${fmt(log.old_values)}</td></tr>
      <tr><td class="text-secondary">New Values</td><td>${fmt(log.new_values)}</td></tr>
    </table>`;
}

async function purge() {
  const days   = document.getElementById('purgeDays').value;
  const result = document.getElementById('purgeResult');
  result.innerHTML = '<div class="spinner-border spinner-border-sm text-secondary"></div>';

  const r    = await fetch('/api/v1/audit/purge', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ csrf_token: CSRF, days }),
  });
  const data = await r.json();
  result.innerHTML = data.success
    ? `<div class="alert alert-success mb-0">Deleted ${data.deleted} log entries.</div>`
    : `<div class="alert alert-danger mb-0">${data.message || 'Error purging logs.'}</div>`;

  if (data.success && data.deleted > 0) {
    setTimeout(() => location.reload(), 1500);
  }
}
</script>
<?php $this->stop() ?>
