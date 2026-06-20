<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Access Zones</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/access-control" class="text-secondary">Access Control</a></li>
          <li class="breadcrumb-item active text-secondary">Zones</li>
        </ol>
      </nav>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openZoneModal()">
      <i class="bi bi-plus-lg me-1"></i>Add Zone
    </button>
  </div>

  <div class="row g-4">

    <!-- Zones List -->
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">All Zones (<?= count($zones) ?>)</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
              <thead>
                <tr class="text-secondary small">
                  <th class="ps-4">Zone</th>
                  <th>Code</th>
                  <th>Security</th>
                  <th>Doors</th>
                  <th>Status</th>
                  <th class="text-end pe-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($zones)): ?>
                  <tr><td colspan="6" class="text-center text-secondary py-5">No zones configured yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($zones as $z): ?>
                  <?php
                    $secColor = match ((int)$z['security_level']) {
                      1 => 'success', 2 => 'info', 3 => 'warning', 4 => 'danger', default => 'secondary'
                    };
                    $secLabel = match ((int)$z['security_level']) {
                      1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Critical', default => '—'
                    };
                  ?>
                  <tr>
                    <td class="ps-4">
                      <div class="fw-semibold text-white"><?= htmlspecialchars($z['name']) ?></div>
                      <?php if ($z['description']): ?>
                      <small class="text-secondary"><?= htmlspecialchars(mb_strimwidth($z['description'], 0, 60, '…')) ?></small>
                      <?php endif; ?>
                    </td>
                    <td><code class="text-info"><?= htmlspecialchars($z['zone_code']) ?></code></td>
                    <td><span class="badge bg-<?= $secColor ?>"><?= $secLabel ?></span></td>
                    <td><?= (int)$z['door_count'] ?></td>
                    <td>
                      <span class="badge bg-<?= $z['is_active'] ? 'success' : 'secondary' ?>">
                        <?= $z['is_active'] ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td class="text-end pe-4">
                      <div class="d-flex gap-2 justify-content-end">
                        <a href="/access-control/rules/<?= $z['id'] ?>" class="btn btn-outline-secondary btn-sm">
                          <i class="bi bi-shield-check"></i> Rules
                        </a>
                        <button class="btn btn-outline-primary btn-sm" onclick="openZoneModal(<?= htmlspecialchars(json_encode($z)) ?>)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteZone(<?= $z['id'] ?>, '<?= addslashes($z['name']) ?>')">
                          <i class="bi bi-trash"></i>
                        </button>
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

    <!-- Add Door -->
    <div class="col-lg-4">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Add Door to Zone</h6>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label text-secondary">Zone <span class="text-danger">*</span></label>
            <select id="doorZone" class="form-select form-select-dark">
              <option value="">Select zone…</option>
              <?php foreach ($zones as $z): ?>
              <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary">Device <span class="text-danger">*</span></label>
            <select id="doorDevice" class="form-select form-select-dark">
              <option value="">Select device…</option>
              <?php foreach ($devices as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= $d['ip_address'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary">Door Name <span class="text-danger">*</span></label>
            <input type="text" id="doorName" class="form-control form-control-dark" placeholder="e.g. Main Entry Door">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label text-secondary">Door Index</label>
              <select id="doorIndex" class="form-select form-select-dark">
                <option value="1">Door 1</option>
                <option value="2">Door 2</option>
                <option value="3">Door 3</option>
                <option value="4">Door 4</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label text-secondary">Direction</label>
              <select id="doorDirection" class="form-select form-select-dark">
                <option value="both">Both</option>
                <option value="in">In Only</option>
                <option value="out">Out Only</option>
              </select>
            </div>
          </div>
          <button class="btn btn-primary w-100" onclick="saveDoor()">
            <i class="bi bi-door-open me-1"></i>Add Door
          </button>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Zone Modal -->
<div class="modal fade" id="zoneModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <form action="/access-control/zones" method="POST" novalidate id="zoneForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id" id="zoneId" value="">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-white" id="zoneModalTitle">Add Zone</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-secondary">Zone Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="zoneName" class="form-control form-control-dark" required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label text-secondary">Zone Code <span class="text-danger">*</span></label>
              <input type="text" name="zone_code" id="zoneCode" class="form-control form-control-dark text-uppercase" placeholder="MAIN_ENT" required>
              <small class="text-secondary">Uppercase letters, underscores only</small>
            </div>
            <div class="col-6">
              <label class="form-label text-secondary">Security Level <span class="text-danger">*</span></label>
              <select name="security_level" id="zoneLevel" class="form-select form-select-dark" required>
                <option value="1">1 – Low</option>
                <option value="2">2 – Medium</option>
                <option value="3">3 – High</option>
                <option value="4">4 – Critical</option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label text-secondary">Description</label>
            <textarea name="description" id="zoneDesc" class="form-control form-control-dark" rows="2"></textarea>
          </div>
          <div class="mt-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" id="zoneActive" value="1" checked>
              <label class="form-check-label text-secondary" for="zoneActive">Active</label>
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Zone</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" action="" method="POST" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
</form>

<script>
const CSRF = '<?= $csrf_token ?>';

function openZoneModal(zone = null) {
  document.getElementById('zoneId').value      = zone ? zone.id : '';
  document.getElementById('zoneName').value    = zone ? zone.name : '';
  document.getElementById('zoneCode').value    = zone ? zone.zone_code : '';
  document.getElementById('zoneLevel').value   = zone ? zone.security_level : '1';
  document.getElementById('zoneDesc').value    = zone ? (zone.description || '') : '';
  document.getElementById('zoneActive').checked = zone ? !!zone.is_active : true;
  document.getElementById('zoneModalTitle').textContent = zone ? 'Edit Zone' : 'Add Zone';
  new bootstrap.Modal(document.getElementById('zoneModal')).show();
}

// Auto uppercase zone code
document.getElementById('zoneCode').addEventListener('input', function () {
  this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
});

function deleteZone(id, name) {
  if (!confirm(`Delete zone "${name}"? This cannot be undone.`)) return;
  const form = document.getElementById('deleteForm');
  form.action = '/access-control/zones/delete/' + id;
  form.submit();
}

async function saveDoor() {
  const data = {
    csrf_token:  CSRF,
    zone_id:     document.getElementById('doorZone').value,
    device_id:   document.getElementById('doorDevice').value,
    name:        document.getElementById('doorName').value.trim(),
    door_index:  document.getElementById('doorIndex').value,
    direction:   document.getElementById('doorDirection').value,
  };

  if (!data.zone_id || !data.device_id || !data.name) {
    alert('Zone, Device, and Door Name are required.'); return;
  }

  const r    = await fetch('/api/v1/access/door', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data),
  });
  const json = await r.json();
  if (json.success) {
    location.reload();
  } else {
    alert(json.message || 'Error saving door.');
  }
}
</script>
<?php $this->stop() ?>
