<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Backup & Restore</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/system/settings" class="text-secondary">System</a></li>
          <li class="breadcrumb-item active text-secondary">Backup</li>
        </ol>
      </nav>
    </div>
  </div>

  <div class="row g-4">

    <!-- Create Backup -->
    <div class="col-lg-4">
      <div class="card card-dark border-0 mb-4">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0">Create Backup</h6>
        </div>
        <div class="card-body d-flex flex-column gap-3">
          <button class="btn btn-primary" onclick="createBackup('full')">
            <i class="bi bi-cloud-arrow-up me-2"></i>Full Backup (DB + Files)
          </button>
          <button class="btn btn-outline-primary" onclick="createBackup('db_only')">
            <i class="bi bi-database me-2"></i>Database Only
          </button>
          <button class="btn btn-outline-secondary" onclick="createBackup('files_only')">
            <i class="bi bi-folder me-2"></i>Files Only
          </button>
          <div id="createStatus" class="d-none">
            <div class="d-flex align-items-center gap-2 text-secondary small">
              <div class="spinner-border spinner-border-sm"></div>
              <span id="createMsg">Creating backup…</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Upload Backup -->
      <div class="card card-dark border-0 mb-4">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0">Upload Backup</h6>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label text-secondary small">Backup File (.sql, .sql.gz)</label>
            <input type="file" id="uploadFile" class="form-control form-control-dark" accept=".sql,.gz">
          </div>
          <button class="btn btn-outline-secondary w-100" onclick="uploadBackup()">
            <i class="bi bi-upload me-1"></i>Upload
          </button>
          <div id="uploadStatus" class="mt-2"></div>
        </div>
      </div>

      <!-- Disk Usage -->
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0">Storage</h6>
        </div>
        <div class="card-body">
          <?php
            $free  = $disk_free;
            $total = disk_total_space('/');
            $used  = $total - $free;
            $pct   = $total > 0 ? round($used / $total * 100) : 0;
            $fmt   = fn($b) => $b >= 1073741824 ? round($b/1073741824,1).' GB' : round($b/1048576,1).' MB';
          ?>
          <p class="text-secondary small mb-2">Disk Usage</p>
          <div class="progress mb-2" style="height:8px;background:rgba(255,255,255,0.1)">
            <div class="progress-bar bg-<?= $pct > 85 ? 'danger' : ($pct > 65 ? 'warning' : 'primary') ?>"
                 style="width:<?= $pct ?>%"></div>
          </div>
          <div class="d-flex justify-content-between small text-secondary">
            <span>Used: <?= $fmt($used) ?></span>
            <span>Free: <?= $fmt($free) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Backup List -->
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Backup Files (<?= count($backups) ?>)</h6>
          <button class="btn btn-link btn-sm text-secondary p-0" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
          </button>
        </div>
        <div class="card-body p-0">
          <?php if (empty($backups)): ?>
          <div class="text-center text-secondary py-5">
            <i class="bi bi-archive fs-2 d-block mb-2"></i>No backups found. Create one to get started.
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 small">
              <thead>
                <tr class="text-secondary">
                  <th class="ps-4">Filename</th>
                  <th>Type</th>
                  <th>Size</th>
                  <th>Created</th>
                  <th class="text-end pe-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($backups as $b): ?>
                <tr>
                  <td class="ps-4">
                    <i class="bi bi-file-earmark-zip text-secondary me-2"></i>
                    <span class="text-white font-monospace" style="font-size:0.8rem"><?= htmlspecialchars($b['filename']) ?></span>
                  </td>
                  <td>
                    <span class="badge bg-<?= $b['type'] === 'Database' ? 'primary' : ($b['type'] === 'Files' ? 'info' : 'secondary') ?>">
                      <?= $b['type'] ?>
                    </span>
                  </td>
                  <td class="text-secondary">
                    <?php
                      $sz = $b['size'];
                      echo $sz >= 1073741824 ? round($sz/1073741824,2).' GB' : ($sz >= 1048576 ? round($sz/1048576,1).' MB' : round($sz/1024,1).' KB');
                    ?>
                  </td>
                  <td class="text-secondary"><?= date('d M Y H:i', $b['modified']) ?></td>
                  <td class="text-end pe-4">
                    <div class="d-flex gap-2 justify-content-end">
                      <a href="/system/backup/download/<?= urlencode($b['filename']) ?>" class="btn btn-xs btn-outline-secondary">
                        <i class="bi bi-download"></i>
                      </a>
                      <?php if (str_ends_with($b['filename'], '.sql') || str_ends_with($b['filename'], '.sql.gz')): ?>
                      <button class="btn btn-xs btn-outline-warning" onclick="restoreBackup('<?= addslashes($b['filename']) ?>')">
                        <i class="bi bi-arrow-counterclockwise"></i>
                      </button>
                      <?php endif; ?>
                      <button class="btn btn-xs btn-outline-danger" onclick="deleteBackup('<?= addslashes($b['filename']) ?>')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';

async function createBackup(type) {
  const status = document.getElementById('createStatus');
  const msg    = document.getElementById('createMsg');
  status.classList.remove('d-none');
  msg.textContent = 'Creating ' + type.replace('_',' ') + ' backup…';

  const r    = await fetch('/api/v1/backup/create', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token: CSRF, type}),
  });
  const data = await r.json();
  status.classList.add('d-none');

  if (data.success) {
    showToast('Backup created: ' + (data.filename || ''), 'success');
    setTimeout(() => location.reload(), 1000);
  } else {
    showToast(data.message || 'Backup failed.', 'danger');
  }
}

async function deleteBackup(filename) {
  if (!confirm('Delete backup: ' + filename + '?')) return;
  const r    = await fetch('/api/v1/backup/delete', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token: CSRF, filename}),
  });
  const data = await r.json();
  if (data.success) { showToast('Backup deleted.','success'); setTimeout(() => location.reload(), 800); }
  else showToast(data.message || 'Error.','danger');
}

async function restoreBackup(filename) {
  if (!confirm('RESTORE from: ' + filename + '?\n\nThis will overwrite the current database. Make a fresh backup first!')) return;
  showToast('Restoring database… please wait.','warning');
  const r    = await fetch('/api/v1/backup/restore', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token: CSRF, filename}),
  });
  const data = await r.json();
  if (data.success) showToast('Restore complete. Refreshing…','success'), setTimeout(() => location.reload(), 2000);
  else showToast(data.message || 'Restore failed.','danger');
}

async function uploadBackup() {
  const file   = document.getElementById('uploadFile').files[0];
  const status = document.getElementById('uploadStatus');
  if (!file) { status.innerHTML = '<small class="text-warning">Select a file first.</small>'; return; }

  status.innerHTML = '<small class="text-secondary"><span class="spinner-border spinner-border-sm me-1"></span>Uploading…</small>';
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  fd.append('backup_file', file);

  const r    = await fetch('/api/v1/backup/upload', { method:'POST', body: fd });
  const data = await r.json();
  if (data.success) {
    status.innerHTML = '<small class="text-success">Uploaded: ' + data.filename + '</small>';
    setTimeout(() => location.reload(), 1000);
  } else {
    status.innerHTML = '<small class="text-danger">' + (data.message || 'Upload failed.') + '</small>';
  }
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = `alert alert-${type} position-fixed bottom-0 end-0 m-3 shadow`;
  t.style.zIndex = 9999;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}
</script>
<?php $this->stop() ?>
