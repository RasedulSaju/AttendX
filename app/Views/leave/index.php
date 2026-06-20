<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Leave Management</h4>
      <p class="text-secondary small mb-0"><?= $pending ?> pending approval(s)</p>
    </div>
    <div class="d-flex gap-2">
      <a href="/leave/apply" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Apply Leave</a>
      <?php if (\App\Core\Auth::can('leave.manage')): ?>
      <a href="/leave/manage" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i>Leave Types</a>
      <a href="/leave/holidays" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-event me-1"></i>Holidays</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3"><select name="status" class="form-select form-select-dark form-select-sm">
          <option value="">All Status</option>
          <?php foreach (['pending','approved','rejected','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="col-md-3"><select name="leave_type_id" class="form-select form-select-dark form-select-sm">
          <option value="">All Types</option>
          <?php foreach ($types as $t): ?>
          <option value="<?= $t['id'] ?>" <?= ($filters['leave_type_id']??0)==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><input type="number" name="year" class="form-control form-control-dark form-control-sm" value="<?= $filters['year']??date('Y') ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
        <div class="col-md-2"><a href="/leave/export?year=<?= $filters['year']??date('Y') ?>" class="btn btn-outline-secondary btn-sm w-100">Export</a></div>
      </form>
    </div>
  </div>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th class="pe-4 text-end">Actions</th></tr></thead>
          <tbody>
            <?php if (empty($apps)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-5">No leave applications found.</td></tr>
            <?php else: ?>
            <?php foreach ($apps as $a): ?>
            <?php $sc = match($a['status']) { 'approved'=>'success','rejected'=>'danger','pending'=>'warning','cancelled'=>'secondary',default=>'secondary' }; ?>
            <tr>
              <td class="ps-4">
                <div class="fw-semibold text-white"><?= htmlspecialchars($a['full_name']) ?></div>
                <div class="text-secondary" style="font-size:.7rem"><?= $a['employee_code'] ?></div>
              </td>
              <td class="text-secondary"><?= htmlspecialchars($a['leave_type_name']) ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($a['from_date'])) ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($a['to_date'])) ?></td>
              <td><?= $a['total_days'] ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($a['status']) ?></span></td>
              <td class="pe-4 text-end">
                <?php if ($a['status']==='pending' && \App\Core\Auth::can('leave.approve')): ?>
                <button class="btn btn-xs btn-outline-success" onclick="approveLeave(<?= $a['id'] ?>)"><i class="bi bi-check"></i></button>
                <button class="btn btn-xs btn-outline-danger" onclick="rejectLeave(<?= $a['id'] ?>)"><i class="bi bi-x"></i></button>
                <?php endif; ?>
                <button class="btn btn-xs btn-outline-secondary" onclick="viewLeave(<?= $a['id'] ?>)"><i class="bi bi-eye"></i></button>
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

<div class="modal fade" id="viewModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content bg-dark">
  <div class="modal-header border-secondary"><h5 class="modal-title text-white">Leave Application</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="viewBody"></div>
</div></div></div>

<script>
const CSRF = '<?= $csrf_token ?>';
async function approveLeave(id) {
  if (!confirm('Approve this leave application?')) return;
  const r = await fetch(`/leave/${id}/approve`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  const d = await r.json(); if(d.success) location.reload(); else alert(d.message||'Failed.');
}
async function rejectLeave(id) {
  const remarks = prompt('Reason for rejection:');
  if (!remarks) return;
  const r = await fetch(`/leave/${id}/reject`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF,remarks})});
  const d = await r.json(); if(d.success) location.reload(); else alert(d.message||'Failed.');
}
async function viewLeave(id) {
  const r = await fetch(`/leave/${id}`);
  const d = await r.json();
  if (!d.success) return;
  const a = d.application;
  document.getElementById('viewBody').innerHTML = `
    <p><strong class="text-white">${a.full_name}</strong> <span class="text-secondary">(${a.employee_code})</span></p>
    <p class="text-secondary small mb-1">Type: ${a.leave_type_name}</p>
    <p class="text-secondary small mb-1">Period: ${a.from_date} → ${a.to_date} (${a.total_days} days)</p>
    <p class="text-secondary small mb-1">Status: ${a.status}</p>
    <p class="text-secondary small mb-1">Reason:</p>
    <p class="text-white small">${a.reason}</p>
    ${a.remarks ? `<p class="text-secondary small">Remarks: ${a.remarks}</p>` : ''}
  `;
  new bootstrap.Modal(document.getElementById('viewModal')).show();
}
</script>
<?php $this->stop() ?>
