<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Salary Structures</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/payroll" class="text-secondary">Payroll</a></li>
          <li class="breadcrumb-item active text-secondary">Structures</li>
        </ol>
      </nav>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#structureModal">
      <i class="bi bi-plus-lg me-1"></i>New Structure
    </button>
  </div>

  <!-- Structures Grid -->
  <div class="row g-4 mb-4">
    <?php if (empty($structures)): ?>
    <div class="col-12">
      <div class="card card-dark border-0 text-center py-5">
        <i class="bi bi-diagram-3 fs-2 text-secondary d-block mb-2"></i>
        <p class="text-secondary mb-0">No salary structures yet. Create one to assign to employees.</p>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($structures as $s): ?>
    <div class="col-md-4">
      <div class="card card-dark border-0 h-100">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
              <h6 class="text-white fw-semibold mb-1"><?= htmlspecialchars($s['name']) ?></h6>
              <small class="text-secondary"><?= $s['component_count'] ?> components</small>
            </div>
            <span class="badge bg-<?= $s['is_active'] ? 'success' : 'secondary' ?>">
              <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </div>
          <?php if ($s['description']): ?>
          <p class="text-secondary small mb-3"><?= htmlspecialchars(mb_strimwidth($s['description'], 0, 80, '…')) ?></p>
          <?php endif; ?>
          <small class="text-secondary d-block mb-3">Created by: <?= htmlspecialchars($s['created_by_name'] ?? '') ?></small>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<!-- New Structure Modal -->
<div class="modal fade" id="structureModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <form action="/payroll/structures/save" method="POST" novalidate id="structForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-white">New Salary Structure</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-md-8">
              <label class="form-label text-secondary">Structure Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control form-control-dark" required placeholder="e.g. Standard Executive">
            </div>
            <div class="col-md-4">
              <label class="form-label text-secondary">Status</label>
              <select name="is_active" class="form-select form-select-dark">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label text-secondary">Description</label>
              <textarea name="description" class="form-control form-control-dark" rows="2"></textarea>
            </div>
          </div>

          <h6 class="text-secondary small text-uppercase mb-3">Components</h6>
          <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-2" id="componentTable">
              <thead>
                <tr class="text-secondary small">
                  <th>Component</th>
                  <th>Amount (fixed)</th>
                  <th>% of Base</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              <tbody id="componentRows">
                <tr id="noComponentRow">
                  <td colspan="4" class="text-secondary text-center py-2">Add components below.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
              <select id="addComponentSelect" class="form-select form-select-dark form-select-sm">
                <option value="">Select component to add…</option>
                <?php foreach ($components as $c): ?>
                <option value="<?= $c['id'] ?>"
                        data-type="<?= $c['type'] ?>"
                        data-calc="<?= $c['calculation'] ?>">
                  <?= htmlspecialchars($c['name']) ?> (<?= $c['type'] ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addComponent()">
              <i class="bi bi-plus-lg"></i> Add
            </button>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Structure</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const compData = <?= json_encode(array_column($components, null, 'id')) ?>;
let rowIdx = 0;

function addComponent() {
  const sel = document.getElementById('addComponentSelect');
  const id  = sel.value;
  if (!id) return;

  const comp = compData[id];
  if (!comp) return;

  document.getElementById('noComponentRow')?.remove();

  const tr = document.createElement('tr');
  tr.id = 'compRow_' + (rowIdx++);
  const isPerc = comp.calculation === 'percentage';
  tr.innerHTML = `
    <td>
      <input type="hidden" name="components[${rowIdx}][component_id]" value="${comp.id}">
      <span class="text-white">${comp.name}</span>
      <small class="badge bg-${comp.type === 'earning' ? 'success' : 'danger'} ms-1">${comp.type}</small>
    </td>
    <td>
      ${isPerc ? '<span class="text-secondary small">N/A (percentage)</span>' :
        `<input type="number" name="components[${rowIdx}][amount]" class="form-control form-control-dark form-control-sm" step="0.01" min="0" value="0.00">`}
    </td>
    <td>
      ${isPerc ? `<input type="number" name="components[${rowIdx}][percentage]" class="form-control form-control-dark form-control-sm" step="0.01" min="0" max="100" placeholder="e.g. 50">` :
        '<span class="text-secondary small">—</span>'}
    </td>
    <td>
      <button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest('tr').remove()">
        <i class="bi bi-x"></i>
      </button>
    </td>`;

  document.getElementById('componentRows').appendChild(tr);
  sel.value = '';
}
</script>
<?php $this->stop() ?>
