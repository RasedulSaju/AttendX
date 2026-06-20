<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Roles & Permissions</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/system/users" class="text-secondary">Users</a></li>
          <li class="breadcrumb-item active text-secondary">Roles</li>
        </ol>
      </nav>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openRoleModal()">
      <i class="bi bi-plus-lg me-1"></i>Add Role
    </button>
  </div>

  <div class="row g-4">

    <!-- Roles List -->
    <div class="col-lg-4">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0">Roles</h6>
        </div>
        <div class="list-group list-group-flush" id="roleList">
          <?php foreach ($roles as $i => $role): ?>
          <a href="#" class="list-group-item list-group-item-action bg-transparent border-secondary text-white role-item <?= $i === 0 ? 'active' : '' ?>"
             data-role-id="<?= $role['id'] ?>"
             onclick="selectRole(this, <?= htmlspecialchars(json_encode($role)) ?>); return false;">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($role['name']) ?></div>
                <small class="text-secondary"><?= $role['user_count'] ?> user<?= $role['user_count'] != 1 ? 's' : '' ?></small>
              </div>
              <div class="d-flex gap-1">
                <button class="btn btn-xs btn-outline-primary" onclick="event.stopPropagation(); openRoleModal(<?= htmlspecialchars(json_encode($role)) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Permissions Matrix -->
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Permissions: <span id="activeRoleName"><?= htmlspecialchars($roles[0]['name'] ?? '') ?></span></h6>
          <button class="btn btn-primary btn-sm" onclick="savePermissions()">
            <i class="bi bi-save me-1"></i>Save Permissions
          </button>
        </div>
        <div class="card-body p-0">
          <?php
            $modules = [];
            foreach ($permissions as $p) {
              $modules[$p['module']][] = $p;
            }
          ?>
          <form id="permForm">
            <input type="hidden" id="permRoleId" value="<?= $roles[0]['id'] ?? '' ?>">
            <?php foreach ($modules as $module => $perms): ?>
            <div class="border-bottom border-secondary">
              <div class="px-4 py-2 d-flex align-items-center justify-content-between" style="background:rgba(255,255,255,0.03)">
                <span class="text-secondary small text-uppercase fw-semibold"><?= ucfirst($module) ?></span>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleModule('<?= $module ?>')">Toggle All</button>
              </div>
              <div class="px-4 py-3 row g-2">
                <?php foreach ($perms as $p): ?>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input class="form-check-input perm-check" type="checkbox"
                           id="perm_<?= $p['id'] ?>"
                           name="permissions[]"
                           value="<?= $p['id'] ?>"
                           data-module="<?= $module ?>"
                           <?php
                             $firstRoleId = $roles[0]['id'] ?? 0;
                             if (!empty($rolePerms[$firstRoleId][$p['id']])) echo 'checked';
                           ?>>
                    <label class="form-check-label text-secondary small" for="perm_<?= $p['id'] ?>">
                      <?= htmlspecialchars($p['name']) ?>
                    </label>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <form action="/system/roles/save" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id" id="roleId">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-white" id="roleModalTitle">Add Role</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-secondary">Role Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="rName" class="form-control form-control-dark" required>
          </div>
          <div>
            <label class="form-label text-secondary">Description</label>
            <textarea name="description" id="rDesc" class="form-control form-control-dark" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Role</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const CSRF        = '<?= $csrf_token ?>';
const rolePermsData = <?= json_encode($rolePerms) ?>;

function openRoleModal(role = null) {
  document.getElementById('roleId').value   = role ? role.id : '';
  document.getElementById('rName').value    = role ? role.name : '';
  document.getElementById('rDesc').value    = role ? (role.description || '') : '';
  document.getElementById('roleModalTitle').textContent = role ? 'Edit Role' : 'Add Role';
  new bootstrap.Modal(document.getElementById('roleModal')).show();
}

function selectRole(el, role) {
  document.querySelectorAll('.role-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('activeRoleName').textContent = role.name;
  document.getElementById('permRoleId').value = role.id;

  const perms = rolePermsData[role.id] || {};
  document.querySelectorAll('.perm-check').forEach(cb => {
    cb.checked = !!perms[cb.value];
  });
}

function toggleModule(module) {
  const boxes   = document.querySelectorAll(`.perm-check[data-module="${module}"]`);
  const checked = [...boxes].every(b => b.checked);
  boxes.forEach(b => b.checked = !checked);
}

async function savePermissions() {
  const roleId = document.getElementById('permRoleId').value;
  const perms  = [...document.querySelectorAll('.perm-check:checked')].map(b => parseInt(b.value));

  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  fd.append('id', roleId);
  perms.forEach(p => fd.append('permissions[]', p));

  const r    = await fetch('/system/roles/save', { method: 'POST', body: fd });
  // Follow redirect or show toast
  if (r.ok) {
    const t = document.createElement('div');
    t.className = 'alert alert-success position-fixed bottom-0 end-0 m-3 shadow';
    t.style.zIndex = 9999;
    t.textContent = 'Permissions saved.';
    document.body.appendChild(t);
    setTimeout(() => { t.remove(); location.reload(); }, 1200);
  }
}
</script>
<?php $this->stop() ?>
