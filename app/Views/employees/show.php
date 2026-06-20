<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
      <li class="breadcrumb-item"><a href="/employees" class="text-secondary">Employees</a></li>
      <li class="breadcrumb-item active text-secondary"><?= htmlspecialchars($emp['full_name']) ?></li>
    </ol></nav>
    <div class="d-flex gap-2">
      <?php if (\App\Core\Auth::can('employees.edit')): ?>
      <a href="/employees/<?= $emp['id'] ?>/edit" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
      <?php endif; ?>
      <a href="/attendance/employee/<?= $emp['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>Attendance</a>
    </div>
  </div>

  <div class="row g-4">
    <!-- Profile Card -->
    <div class="col-lg-4">
      <div class="card card-dark border-0 text-center p-4 mb-4">
        <?php if (!empty($emp['profile_photo']) && file_exists(base_path($emp['profile_photo']))): ?>
        <img src="/<?= htmlspecialchars($emp['profile_photo']) ?>" class="rounded-circle mx-auto mb-3" style="width:100px;height:100px;object-fit:cover">
        <?php else: ?>
        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold text-white" style="width:100px;height:100px;font-size:2.2rem">
          <?= strtoupper(substr($emp['full_name'],0,1)) ?>
        </div>
        <?php endif; ?>
        <h5 class="text-white mb-1"><?= htmlspecialchars($emp['full_name']) ?></h5>
        <p class="text-secondary small mb-2"><?= htmlspecialchars($emp['designation_name']??'—') ?></p>
        <?php $sc = match($emp['status']) { 'active'=>'success','inactive'=>'warning','terminated'=>'danger',default=>'secondary' }; ?>
        <span class="badge bg-<?= $sc ?> mx-auto" style="width:fit-content"><?= ucfirst($emp['status']) ?></span>

        <hr class="border-secondary">
        <div class="text-start small">
          <p class="mb-2"><i class="bi bi-envelope text-secondary me-2"></i><?= htmlspecialchars($emp['email']??'—') ?></p>
          <p class="mb-2"><i class="bi bi-telephone text-secondary me-2"></i><?= htmlspecialchars($emp['phone']??'—') ?></p>
          <p class="mb-2"><i class="bi bi-building text-secondary me-2"></i><?= htmlspecialchars($emp['department_name']??'—') ?></p>
          <p class="mb-2"><i class="bi bi-calendar text-secondary me-2"></i>Joined <?= date('d M Y', strtotime($emp['join_date'])) ?></p>
          <p class="mb-0"><i class="bi bi-credit-card text-secondary me-2"></i>Badge: <?= htmlspecialchars($emp['badge_number']??'—') ?></p>
        </div>
      </div>

      <!-- Status Change -->
      <?php if (\App\Core\Auth::can('employees.edit')): ?>
      <div class="card card-dark border-0 mb-4">
        <div class="card-body">
          <label class="form-label text-secondary small">Change Status</label>
          <select id="statusSelect" class="form-select form-select-dark form-select-sm" onchange="changeStatus(this.value)">
            <?php foreach (['active','inactive','on_leave','terminated'] as $s): ?>
            <option value="<?= $s ?>" <?= $emp['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <!-- Current Salary -->
      <?php if ($currentSalary): ?>
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Current Salary</h6></div>
        <div class="card-body">
          <p class="text-secondary small mb-1"><?= htmlspecialchars($currentSalary['structure_name']) ?></p>
          <h4 class="text-success fw-bold mb-0"><?= number_format((float)$currentSalary['basic_salary'],2) ?></h4>
          <small class="text-secondary">Effective from <?= date('d M Y', strtotime($currentSalary['effective_from'])) ?></small>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right Column -->
    <div class="col-lg-8">

      <!-- Documents -->
      <div class="card card-dark border-0 mb-4">
        <div class="card-header bg-transparent border-bottom border-secondary d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Documents</h6>
          <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#docModal"><i class="bi bi-upload me-1"></i>Upload</button>
        </div>
        <div class="card-body p-0">
          <?php if (empty($docs)): ?>
          <p class="text-secondary text-center py-4 mb-0">No documents uploaded.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0 small">
              <thead><tr class="text-secondary"><th class="ps-4">Type</th><th>Name</th><th>Expiry</th><th class="pe-4 text-end">Actions</th></tr></thead>
              <tbody>
                <?php foreach ($docs as $d): ?>
                <tr>
                  <td class="ps-4 text-secondary"><?= htmlspecialchars($d['document_type']) ?></td>
                  <td class="text-white"><?= htmlspecialchars($d['document_name']) ?></td>
                  <td class="text-secondary"><?= $d['expiry_date'] ? date('d M Y', strtotime($d['expiry_date'])) : '—' ?></td>
                  <td class="pe-4 text-end">
                    <a href="/<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    <button class="btn btn-xs btn-outline-danger" onclick="deleteDoc(<?= $d['id'] ?>)"><i class="bi bi-trash"></i></button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Transfer History -->
      <div class="card card-dark border-0 mb-4">
        <div class="card-header bg-transparent border-bottom border-secondary d-flex align-items-center justify-content-between">
          <h6 class="text-white mb-0">Transfer History</h6>
          <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-arrow-left-right me-1"></i>Transfer</button>
        </div>
        <div class="card-body p-0">
          <?php if (empty($transfers)): ?>
          <p class="text-secondary text-center py-4 mb-0">No transfer history.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0 small">
              <thead><tr class="text-secondary"><th class="ps-4">Date</th><th>Department</th><th>Designation</th><th class="pe-4">Reason</th></tr></thead>
              <tbody>
                <?php foreach ($transfers as $t): ?>
                <tr>
                  <td class="ps-4 text-secondary"><?= date('d M Y', strtotime($t['effective_date'])) ?></td>
                  <td class="text-white"><?= htmlspecialchars($t['dept_name']??'—') ?></td>
                  <td class="text-secondary"><?= htmlspecialchars($t['des_name']??'—') ?></td>
                  <td class="pe-4 text-secondary"><?= htmlspecialchars($t['reason']??'') ?></td>
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

<!-- Document Upload Modal -->
<div class="modal fade" id="docModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header border-secondary"><h5 class="modal-title text-white">Upload Document</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label text-secondary">Document Type</label>
          <input type="text" id="docType" class="form-control form-control-dark" placeholder="e.g. NID, Certificate"></div>
        <div class="mb-3"><label class="form-label text-secondary">Document Name</label>
          <input type="text" id="docName" class="form-control form-control-dark"></div>
        <div class="mb-3"><label class="form-label text-secondary">Expiry Date</label>
          <input type="date" id="docExpiry" class="form-control form-control-dark"></div>
        <div><label class="form-label text-secondary">File *</label>
          <input type="file" id="docFile" class="form-control form-control-dark" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="uploadDoc()">Upload</button>
      </div>
    </div>
  </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header border-secondary"><h5 class="modal-title text-white">Transfer Employee</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label text-secondary">Effective Date *</label>
          <input type="date" id="transferDate" class="form-control form-control-dark" value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-3"><label class="form-label text-secondary">Reason</label>
          <textarea id="transferReason" class="form-control form-control-dark" rows="2"></textarea></div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitTransfer()">Submit Transfer</button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';
const EMP_ID = <?= $emp['id'] ?>;

async function changeStatus(status) {
  const r = await fetch(`/employees/${EMP_ID}/status`, {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token: CSRF, status}),
  });
  const d = await r.json();
  if (d.success) location.reload();
  else alert(d.message || 'Error updating status.');
}

async function uploadDoc() {
  const file = document.getElementById('docFile').files[0];
  if (!file) { alert('Select a file.'); return; }
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  fd.append('document_type', document.getElementById('docType').value || 'other');
  fd.append('document_name', document.getElementById('docName').value || file.name);
  fd.append('expiry_date', document.getElementById('docExpiry').value);
  fd.append('document', file);
  const r = await fetch(`/employees/${EMP_ID}/document`, { method:'POST', body: fd });
  const d = await r.json();
  if (d.success) location.reload();
  else alert(d.message || 'Upload failed.');
}

async function deleteDoc(id) {
  if (!confirm('Delete this document?')) return;
  const r = await fetch(`/employees/${EMP_ID}/document/delete`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token: CSRF, document_id: id}),
  });
  const d = await r.json();
  if (d.success) location.reload();
}

async function submitTransfer() {
  const data = {
    csrf_token: CSRF,
    effective_date: document.getElementById('transferDate').value,
    reason: document.getElementById('transferReason').value,
  };
  const r = await fetch(`/employees/${EMP_ID}/transfer`, {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data),
  });
  const d = await r.json();
  if (d.success) location.reload();
  else alert(d.message || 'Transfer failed.');
}
</script>
<?php $this->stop() ?>
