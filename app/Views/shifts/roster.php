<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Roster Management</h4>
    <a href="/shifts" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Shifts</a>
  </div>

  <div class="row g-4">
    <!-- Assign Shift -->
    <div class="col-lg-4">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Assign Shift to Employee</h6></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label text-secondary">Employee *</label>
            <select id="assignEmp" class="form-select form-select-dark">
              <option value="">Select employee…</option>
              <?php foreach ($employees as $e): ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_code'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary">Shift *</label>
            <select id="assignShift" class="form-select form-select-dark">
              <option value="">Select shift…</option>
              <?php foreach ($shifts as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= substr($s['start_time'],0,5) ?>–<?= substr($s['end_time'],0,5) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6"><label class="form-label text-secondary small">From *</label><input type="date" id="assignFrom" class="form-control form-control-dark" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-6"><label class="form-label text-secondary small">To (optional)</label><input type="date" id="assignTo" class="form-control form-control-dark"></div>
          </div>
          <button class="btn btn-primary w-100" onclick="assignShift()"><i class="bi bi-link-45deg me-1"></i>Assign</button>
        </div>
      </div>
    </div>

    <!-- Rosters List -->
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Active Rosters</h6></div>
        <div class="card-body p-0">
          <?php if (empty($rosters)): ?>
          <p class="text-secondary text-center py-4 mb-0">No rosters defined.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0 small">
              <thead><tr class="text-secondary"><th class="ps-4">Roster</th><th>Shift</th><th>Period</th><th class="pe-4">Employees</th></tr></thead>
              <tbody>
                <?php foreach ($rosters as $r): ?>
                <tr>
                  <td class="ps-4 text-white"><?= htmlspecialchars($r['name']) ?></td>
                  <td class="text-secondary"><?= htmlspecialchars($r['shift_name']) ?></td>
                  <td class="text-secondary"><?= date('d M', strtotime($r['start_date'])) ?><?= $r['end_date']?' – '.date('d M Y',strtotime($r['end_date'])):' (ongoing)' ?></td>
                  <td class="pe-4"><?= $r['employee_count'] ?></td>
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
async function assignShift() {
  const data = {
    csrf_token: CSRF,
    employee_id: document.getElementById('assignEmp').value,
    shift_id: document.getElementById('assignShift').value,
    date_from: document.getElementById('assignFrom').value,
    date_to: document.getElementById('assignTo').value,
  };
  if (!data.employee_id || !data.shift_id || !data.date_from) { alert('Employee, Shift, and From date are required.'); return; }
  const r = await fetch('/shifts/roster/assign', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  const d = await r.json();
  if (d.success) { alert('Shift assigned.'); location.reload(); }
  else alert(d.message || 'Error assigning shift.');
}
</script>
<?php $this->stop() ?>
