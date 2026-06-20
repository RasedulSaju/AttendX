<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">User Management</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/system/settings" class="text-secondary">System</a></li>
          <li class="breadcrumb-item active text-secondary">Users</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <a href="/system/roles" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield me-1"></i>Roles</a>
      <button class="btn btn-primary btn-sm" onclick="openUserModal()">
        <i class="bi bi-plus-lg me-1"></i>Add User
      </button>
    </div>
  </div>

  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
      <h6 class="text-white mb-0">System Users (<?= count($users) ?>)</h6>
      <input type="text" id="userSearch" class="form-control form-control-dark form-control-sm w-auto" placeholder="Search…">
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="usersTable">
          <thead>
            <tr class="text-secondary small">
              <th class="ps-4">Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th class="text-end pe-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr class="user-row" data-search="<?= strtolower($u['name'].' '.$u['email']) ?>">
              <td class="ps-4">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle bg-primary bg-opacity-20 d-flex align-items-center justify-content-center fw-semibold text-primary"
                       style="width:36px;height:36px;font-size:0.85rem">
                    <?= strtoupper(substr($u['name'],0,1)) ?>
                  </div>
                  <span class="fw-semibold text-white"><?= htmlspecialchars($u['name']) ?></span>
                </div>
              </td>
              <td class="text-secondary"><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <span class="badge bg-primary bg-opacity-20 text-primary"><?= htmlspecialchars($u['role_name'] ?? '—') ?></span>
              </td>
              <td>
                <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>">
                  <?= ucfirst($u['status']) ?>
                </span>
              </td>
              <td class="text-secondary small">
                <?= !empty($u['last_login_at']) ? date('d M Y H:i', strtotime($u['last_login_at'])) : 'Never' ?>
              </td>
              <td class="text-end pe-4">
                <div class="d-flex gap-2 justify-content-end">
                  <button class="btn btn-xs btn-outline-primary" onclick='openUserModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-xs btn-outline-<?= $u['status'] === 'active' ? 'warning' : 'success' ?>"
                          onclick="toggleUser(<?= $u['id'] ?>, this)">
                    <i class="bi bi-<?= $u['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                  </button>
                  <button class="btn btn-xs btn-outline-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <form action="/system/users/save" method="POST" novalidate id="userForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id" id="userId">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-white" id="userModalTitle">Add User</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-secondary">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="uName" class="form-control form-control-dark" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" id="uEmail" class="form-control form-control-dark" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary">Password <span id="pwRequired" class="text-danger">*</span></label>
            <input type="password" name="password" id="uPassword" class="form-control form-control-dark" autocomplete="new-password" minlength="8">
            <small class="text-secondary" id="pwHint">Minimum 8 characters.</small>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label text-secondary">Role <span class="text-danger">*</span></label>
              <select name="role_id" id="uRole" class="form-select form-select-dark" required>
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label text-secondary">Status</label>
              <select name="status" id="uStatus" class="form-select form-select-dark">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';

document.getElementById('userSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.user-row').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});

function openUserModal(user = null) {
  const editing = !!user;
  document.getElementById('userId').value    = user ? user.id : '';
  document.getElementById('uName').value     = user ? user.name : '';
  document.getElementById('uEmail').value    = user ? user.email : '';
  document.getElementById('uPassword').value = '';
  document.getElementById('uRole').value     = user ? user.role_id : '';
  document.getElementById('uStatus').value   = user ? user.status : 'active';
  document.getElementById('userModalTitle').textContent = editing ? 'Edit User' : 'Add User';
  document.getElementById('pwRequired').style.display   = editing ? 'none' : '';
  document.getElementById('pwHint').textContent         = editing ? 'Leave blank to keep current password.' : 'Minimum 8 characters.';
  document.getElementById('uPassword').required         = !editing;
  new bootstrap.Modal(document.getElementById('userModal')).show();
}

async function toggleUser(id, btn) {
  const r    = await fetch('/api/v1/system/users/' + id + '/toggle', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token: CSRF}),
  });
  const data = await r.json();
  if (data.success) location.reload();
  else alert(data.message || 'Error.');
}

async function deleteUser(id, name) {
  if (!confirm('Delete user "' + name + '"?')) return;
  const r    = await fetch('/api/v1/system/users/' + id + '/delete', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token: CSRF}),
  });
  const data = await r.json();
  if (data.success) location.reload();
  else alert(data.message || 'Error.');
}
</script>
<?php $this->stop() ?>
