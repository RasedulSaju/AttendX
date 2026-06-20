<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Devices</h4>
    <button class="btn btn-primary btn-sm" onclick="openDeviceModal()"><i class="bi bi-plus-lg me-1"></i>Add Device</button>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card card-dark border-0 text-center"><div class="card-body py-3">
      <h4 class="fw-bold text-white mb-0"><?= $stats['total']??0 ?></h4><p class="text-secondary small mb-0">Total</p></div></div></div>
    <div class="col-md-3"><div class="card card-dark border-0 text-center"><div class="card-body py-3">
      <h4 class="fw-bold text-success mb-0"><?= $stats['online']??0 ?></h4><p class="text-secondary small mb-0">Online</p></div></div></div>
    <div class="col-md-3"><div class="card card-dark border-0 text-center"><div class="card-body py-3">
      <h4 class="fw-bold text-danger mb-0"><?= $stats['offline']??0 ?></h4><p class="text-secondary small mb-0">Offline</p></div></div></div>
    <div class="col-md-3"><div class="card card-dark border-0 text-center"><div class="card-body py-3">
      <h4 class="fw-bold text-info mb-0"><?= $stats['active']??0 ?></h4><p class="text-secondary small mb-0">Active</p></div></div></div>
  </div>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Name</th><th>IP / Port</th><th>ADMS Port</th><th>Location</th><th>Today's Punches</th><th>Status</th><th class="pe-4 text-end">Actions</th></tr></thead>
          <tbody>
            <?php if (empty($devices)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-5">No devices registered.</td></tr>
            <?php else: ?>
            <?php foreach ($devices as $d): ?>
            <tr>
              <td class="ps-4 text-white fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
              <td class="text-secondary"><code><?= htmlspecialchars($d['ip_address']??'—') ?>:<?= $d['port'] ?></code></td>
              <td><code class="text-info"><?= $d['adms_port'] ?></code></td>
              <td class="text-secondary"><?= htmlspecialchars($d['location']??'—') ?></td>
              <td><?= $d['today_punches'] ?></td>
              <td>
                <span class="badge bg-<?= $d['status']==='online'?'success':'danger' ?>"><?= ucfirst($d['status']) ?></span>
                <?php if (!$d['is_active']): ?><span class="badge bg-secondary">Disabled</span><?php endif; ?>
              </td>
              <td class="pe-4 text-end">
                <button class="btn btn-xs btn-outline-info" onclick="syncDevice(<?= $d['id'] ?>)" title="Sync"><i class="bi bi-arrow-repeat"></i></button>
                <button class="btn btn-xs btn-outline-primary" onclick='openDeviceModal(<?= htmlspecialchars(json_encode($d),ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                <button class="btn btn-xs btn-outline-secondary" onclick="toggleDevice(<?= $d['id'] ?>)"><i class="bi bi-power"></i></button>
                <button class="btn btn-xs btn-outline-danger" onclick="deleteDevice(<?= $d['id'] ?>)"><i class="bi bi-trash"></i></button>
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

<div class="modal fade" id="deviceModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark">
    <form action="/devices/save" method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
      <input type="hidden" name="id" id="dId">
      <div class="modal-header border-secondary"><h5 class="modal-title text-white" id="dTitle">Add Device</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input type="text" name="name" id="dName" class="form-control form-control-dark" required></div>
        <div class="mb-3"><label class="form-label text-secondary">IP Address *</label><input type="text" name="ip_address" id="dIp" class="form-control form-control-dark" placeholder="192.168.1.100" required></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label text-secondary small">SDK Port (pull)</label><input type="number" name="port" id="dPort" class="form-control form-control-dark" value="4370"></div>
          <div class="col-6"><label class="form-label text-secondary small">ADMS Port (push)</label><input type="number" name="adms_port" id="dAdmsPort" class="form-control form-control-dark" value="8080"></div>
        </div>
        <div class="mb-3"><label class="form-label text-secondary">Model</label><input type="text" name="model" id="dModel" class="form-control form-control-dark" placeholder="Granding F09"></div>
        <div class="mb-3"><label class="form-label text-secondary">Location</label><input type="text" name="location" id="dLocation" class="form-control form-control-dark"></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="dActive" value="1" checked>
          <label class="form-check-label text-secondary">Active</label></div>
      </div>
      <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div></div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';
function openDeviceModal(d=null) {
  document.getElementById('dId').value=d?d.id:'';
  document.getElementById('dName').value=d?d.name:'';
  document.getElementById('dIp').value=d?(d.ip_address||''):'';
  document.getElementById('dPort').value=d?d.port:4370;
  document.getElementById('dAdmsPort').value=d?d.adms_port:8080;
  document.getElementById('dModel').value=d?(d.model||''):'';
  document.getElementById('dLocation').value=d?(d.location||''):'';
  document.getElementById('dActive').checked=d?!!d.is_active:true;
  document.getElementById('dTitle').textContent=d?'Edit Device':'Add Device';
  new bootstrap.Modal(document.getElementById('deviceModal')).show();
}
async function syncDevice(id) {
  const r = await fetch(`/devices/${id}/sync`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  const d = await r.json();
  alert(d.message || (d.success?'Sync queued.':'Sync failed.'));
}
async function toggleDevice(id) {
  await fetch(`/devices/${id}/toggle`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  location.reload();
}
async function deleteDevice(id) {
  if (!confirm('Delete this device? All associated logs will remain but unlinked.')) return;
  await fetch(`/devices/${id}/delete`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  location.reload();
}
</script>
<?php $this->stop() ?>
