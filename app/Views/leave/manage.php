<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Leave Type Management</h4>
    <div class="d-flex gap-2">
      <a href="/leave" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
      <button class="btn btn-primary btn-sm" onclick="openTypeModal()"><i class="bi bi-plus-lg me-1"></i>Add Type</button>
    </div>
  </div>
  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead><tr class="text-secondary small"><th class="ps-4">Name</th><th>Code</th><th>Days/Year</th><th>Carry Forward</th><th>Document Req.</th><th>Status</th><th class="pe-4 text-end">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($types as $t): ?>
            <tr>
              <td class="ps-4 text-white fw-semibold"><?= htmlspecialchars($t['name']) ?></td>
              <td><code class="text-info"><?= htmlspecialchars($t['code']) ?></code></td>
              <td><?= $t['days_allowed'] ?></td>
              <td><?= $t['carry_forward']?'<i class="bi bi-check-circle text-success"></i>':'<i class="bi bi-x-circle text-secondary"></i>' ?></td>
              <td><?= $t['requires_document']?'<i class="bi bi-check-circle text-warning"></i>':'<i class="bi bi-x-circle text-secondary"></i>' ?></td>
              <td><span class="badge bg-<?= $t['is_active']?'success':'secondary' ?>"><?= $t['is_active']?'Active':'Inactive' ?></span></td>
              <td class="pe-4 text-end">
                <button class="btn btn-xs btn-outline-primary" onclick='openTypeModal(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                <button class="btn btn-xs btn-outline-danger" onclick="deleteType(<?= $t['id'] ?>)"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="typeModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark">
    <form action="/leave/types/save" method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
      <input type="hidden" name="id" id="tId">
      <div class="modal-header border-secondary"><h5 class="modal-title text-white" id="tTitle">Add Leave Type</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input type="text" name="name" id="tName" class="form-control form-control-dark" required></div>
        <div class="mb-3"><label class="form-label text-secondary">Code * (e.g. AL, SL)</label><input type="text" name="code" id="tCode" class="form-control form-control-dark text-uppercase" required maxlength="20"></div>
        <div class="mb-3"><label class="form-label text-secondary">Days Allowed / Year *</label><input type="number" step="0.5" name="days_allowed" id="tDays" class="form-control form-control-dark" required></div>
        <div class="mb-3"><label class="form-label text-secondary">Description</label><textarea name="description" id="tDesc" class="form-control form-control-dark" rows="2"></textarea></div>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="carry_forward" id="tCarry" value="1">
          <label class="form-check-label text-secondary">Allow Carry Forward</label></div>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="requires_document" id="tDoc" value="1">
          <label class="form-check-label text-secondary">Requires Document</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="tActive" value="1" checked>
          <label class="form-check-label text-secondary">Active</label></div>
      </div>
      <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div></div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';
function openTypeModal(t=null) {
  document.getElementById('tId').value=t?t.id:'';
  document.getElementById('tName').value=t?t.name:'';
  document.getElementById('tCode').value=t?t.code:'';
  document.getElementById('tDays').value=t?t.days_allowed:'';
  document.getElementById('tDesc').value=t?(t.description||''):'';
  document.getElementById('tCarry').checked=t?!!t.carry_forward:false;
  document.getElementById('tDoc').checked=t?!!t.requires_document:false;
  document.getElementById('tActive').checked=t?!!t.is_active:true;
  document.getElementById('tTitle').textContent=t?'Edit Leave Type':'Add Leave Type';
  new bootstrap.Modal(document.getElementById('typeModal')).show();
}
async function deleteType(id) {
  if (!confirm('Delete this leave type?')) return;
  await fetch(`/leave/types/${id}/delete`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  location.reload();
}
</script>
<?php $this->stop() ?>
