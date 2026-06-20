<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Payroll Components</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/payroll" class="text-secondary">Payroll</a></li>
          <li class="breadcrumb-item active text-secondary">Components</li>
        </ol>
      </nav>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openModal()">
      <i class="bi bi-plus-lg me-1"></i>Add Component
    </button>
  </div>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr class="text-secondary small">
              <th class="ps-4">Name</th>
              <th>Code</th>
              <th>Type</th>
              <th>Calculation</th>
              <th>Taxable</th>
              <th>Order</th>
              <th>Active</th>
              <th class="text-end pe-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($components as $c): ?>
            <tr>
              <td class="ps-4 text-white fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
              <td><code class="text-info"><?= htmlspecialchars($c['code']) ?></code></td>
              <td>
                <span class="badge bg-<?= $c['type'] === 'earning' ? 'success' : ($c['type'] === 'deduction' ? 'danger' : 'info') ?>">
                  <?= ucfirst($c['type']) ?>
                </span>
              </td>
              <td class="text-secondary"><?= ucfirst($c['calculation']) ?></td>
              <td>
                <span class="badge bg-<?= $c['taxable'] ? 'warning' : 'secondary' ?>">
                  <?= $c['taxable'] ? 'Yes' : 'No' ?>
                </span>
              </td>
              <td class="text-secondary"><?= $c['sort_order'] ?></td>
              <td>
                <span class="badge bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>">
                  <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="text-end pe-4">
                <button class="btn btn-xs btn-outline-primary" onclick='openModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Component Modal -->
<div class="modal fade" id="compModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <form action="/payroll/components/save" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id" id="cId">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-white" id="cModalTitle">Add Component</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label text-secondary">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="cName" class="form-control form-control-dark" required>
            </div>
            <div class="col-md-5">
              <label class="form-label text-secondary">Code <span class="text-danger">*</span></label>
              <input type="text" name="code" id="cCode" class="form-control form-control-dark text-uppercase" placeholder="BASIC" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-secondary">Type <span class="text-danger">*</span></label>
              <select name="type" id="cType" class="form-select form-select-dark" required>
                <option value="earning">Earning</option>
                <option value="deduction">Deduction</option>
                <option value="benefit">Benefit</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-secondary">Calculation <span class="text-danger">*</span></label>
              <select name="calculation" id="cCalc" class="form-select form-select-dark" required>
                <option value="fixed">Fixed</option>
                <option value="percentage">Percentage</option>
                <option value="formula">Formula</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-secondary">Sort Order</label>
              <input type="number" name="sort_order" id="cOrder" class="form-control form-control-dark" min="0" value="0">
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" name="taxable" id="cTaxable" value="1" checked>
                <label class="form-check-label text-secondary" for="cTaxable">Taxable</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" name="is_active" id="cActive" value="1" checked>
                <label class="form-check-label text-secondary" for="cActive">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Component</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openModal(c = null) {
  document.getElementById('cId').value     = c ? c.id : '';
  document.getElementById('cName').value   = c ? c.name : '';
  document.getElementById('cCode').value   = c ? c.code : '';
  document.getElementById('cType').value   = c ? c.type : 'earning';
  document.getElementById('cCalc').value   = c ? c.calculation : 'fixed';
  document.getElementById('cOrder').value  = c ? c.sort_order : 0;
  document.getElementById('cTaxable').checked = c ? !!c.taxable : true;
  document.getElementById('cActive').checked  = c ? !!c.is_active : true;
  document.getElementById('cModalTitle').textContent = c ? 'Edit Component' : 'Add Component';
  new bootstrap.Modal(document.getElementById('compModal')).show();
}
document.getElementById('cCode').addEventListener('input', function () {
  this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
});
</script>
<?php $this->stop() ?>
