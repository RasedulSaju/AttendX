<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Departments & Designations</h4>
  </div>

  <ul class="nav nav-pills mb-4" id="tabNav">
    <li class="nav-item"><a class="nav-link active" href="#deptTab" data-tab>Departments</a></li>
    <li class="nav-item"><a class="nav-link" href="#desTab" data-tab>Designations</a></li>
  </ul>

  <!-- Departments Tab -->
  <div id="deptTab" class="tab-pane">
    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-primary btn-sm" onclick="openDeptModal()"><i class="bi bi-plus-lg me-1"></i>Add Department</button>
    </div>
    <div class="card card-dark border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr class="text-secondary small"><th class="ps-4">Name</th><th>Code</th><th>Parent</th><th>Employees</th><th>Status</th><th class="text-end pe-4">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($departments as $d): ?>
              <tr>
                <td class="ps-4 text-white fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
                <td><code class="text-info"><?= htmlspecialchars($d['code']??'') ?></code></td>
                <td class="text-secondary"><?= htmlspecialchars($d['parent_name']??'—') ?></td>
                <td><?= $d['employee_count'] ?></td>
                <td><span class="badge bg-<?= $d['is_active']?'success':'secondary' ?>"><?= $d['is_active']?'Active':'Inactive' ?></span></td>
                <td class="text-end pe-4">
                  <button class="btn btn-xs btn-outline-primary" onclick='openDeptModal(<?= htmlspecialchars(json_encode($d),ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-xs btn-outline-danger" onclick="deleteDept(<?= $d['id'] ?>)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Designations Tab -->
  <div id="desTab" class="tab-pane" style="display:none">
    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-primary btn-sm" onclick="openDesModal()"><i class="bi bi-plus-lg me-1"></i>Add Designation</button>
    </div>
    <div class="card card-dark border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr class="text-secondary small"><th class="ps-4">Name</th><th>Department</th><th>Employees</th><th>Status</th><th class="text-end pe-4">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($designations as $d): ?>
              <tr>
                <td class="ps-4 text-white fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
                <td class="text-secondary"><?= htmlspecialchars($d['department_name']??'—') ?></td>
                <td><?= $d['employee_count'] ?></td>
                <td><span class="badge bg-<?= $d['is_active']?'success':'secondary' ?>"><?= $d['is_active']?'Active':'Inactive' ?></span></td>
                <td class="text-end pe-4">
                  <button class="btn btn-xs btn-outline-primary" onclick='openDesModal(<?= htmlspecialchars(json_encode($d),ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-xs btn-outline-danger" onclick="deleteDes(<?= $d['id'] ?>)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Department Modal -->
<div class="modal fade" id="deptModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark">
    <form action="/departments/save" method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
      <input type="hidden" name="id" id="deptId">
      <div class="modal-header border-secondary"><h5 class="modal-title text-white" id="deptModalTitle">Add Department</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input type="text" name="name" id="deptName" class="form-control form-control-dark" required></div>
        <div class="mb-3"><label class="form-label text-secondary">Code</label><input type="text" name="code" id="deptCode" class="form-control form-control-dark text-uppercase"></div>
        <div class="mb-3"><label class="form-label text-secondary">Parent Department</label>
          <select name="parent_id" id="deptParent" class="form-select form-select-dark">
            <option value="">— None —</option>
            <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="deptActive" value="1" checked>
          <label class="form-check-label text-secondary">Active</label></div>
      </div>
      <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div></div>
</div>

<!-- Designation Modal -->
<div class="modal fade" id="desModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark">
    <form action="/designations/save" method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
      <input type="hidden" name="id" id="desId">
      <div class="modal-header border-secondary"><h5 class="modal-title text-white" id="desModalTitle">Add Designation</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input type="text" name="name" id="desName" class="form-control form-control-dark" required></div>
        <div class="mb-3"><label class="form-label text-secondary">Department</label>
          <select name="department_id" id="desDept" class="form-select form-select-dark">
            <option value="">— None —</option>
            <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="mb-3"><label class="form-label text-secondary">Grade</label><input type="text" name="grade" id="desGrade" class="form-control form-control-dark"></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="desActive" value="1" checked>
          <label class="form-check-label text-secondary">Active</label></div>
      </div>
      <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div></div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';

document.querySelectorAll('[data-tab]').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('[data-tab]').forEach(l=>l.classList.remove('active'));
    link.classList.add('active');
    document.querySelectorAll('.tab-pane').forEach(t=>t.style.display='none');
    document.querySelector(link.getAttribute('href')).style.display='';
  });
});

function openDeptModal(d=null) {
  document.getElementById('deptId').value = d?d.id:'';
  document.getElementById('deptName').value = d?d.name:'';
  document.getElementById('deptCode').value = d?(d.code||''):'';
  document.getElementById('deptParent').value = d?(d.parent_id||''):'';
  document.getElementById('deptActive').checked = d?!!d.is_active:true;
  document.getElementById('deptModalTitle').textContent = d?'Edit Department':'Add Department';
  new bootstrap.Modal(document.getElementById('deptModal')).show();
}
function openDesModal(d=null) {
  document.getElementById('desId').value = d?d.id:'';
  document.getElementById('desName').value = d?d.name:'';
  document.getElementById('desDept').value = d?(d.department_id||''):'';
  document.getElementById('desGrade').value = d?(d.grade||''):'';
  document.getElementById('desActive').checked = d?!!d.is_active:true;
  document.getElementById('desModalTitle').textContent = d?'Edit Designation':'Add Designation';
  new bootstrap.Modal(document.getElementById('desModal')).show();
}
async function deleteDept(id) {
  if (!confirm('Delete this department?')) return;
  const r = await fetch(`/departments/${id}/delete`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  const d = await r.json();
  if (d.success) location.reload(); else alert(d.message||'Cannot delete.');
}
async function deleteDes(id) {
  if (!confirm('Delete this designation?')) return;
  const r = await fetch(`/designations/${id}/delete`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  const d = await r.json();
  if (d.success) location.reload(); else alert(d.message||'Cannot delete.');
}
</script>
<?php $this->stop() ?>
