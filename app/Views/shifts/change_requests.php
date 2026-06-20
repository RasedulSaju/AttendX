<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Shift Change Requests</h4>
    <a href="/shifts" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Shifts</a>
  </div>

  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" class="d-flex gap-2">
        <select name="status" class="form-select form-select-dark form-select-sm" style="max-width:200px">
          <option value="">All Statuses</option>
          <option value="pending"  <?= $status==='pending'?'selected':'' ?>>Pending</option>
          <option value="approved" <?= $status==='approved'?'selected':'' ?>>Approved</option>
          <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
        </select>
        <button class="btn btn-primary btn-sm">Filter</button>
      </form>
    </div>
  </div>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Employee</th><th>Date</th><th>Current Shift</th><th>Requested Shift</th><th>Reason</th><th>Status</th><th class="pe-4 text-end">Actions</th></tr></thead>
          <tbody>
            <?php if (empty($requests)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-5">No shift change requests.</td></tr>
            <?php else: ?>
            <?php foreach ($requests as $r): ?>
            <?php $sc = match($r['status']) { 'approved'=>'success','rejected'=>'danger',default=>'warning' }; ?>
            <tr>
              <td class="ps-4 text-white"><?= htmlspecialchars($r['employee_name']) ?> <small class="text-secondary">(<?= $r['employee_code'] ?>)</small></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($r['requested_date'])) ?></td>
              <td class="text-secondary"><?= htmlspecialchars($r['current_shift_name']) ?></td>
              <td class="text-info"><?= htmlspecialchars($r['requested_shift_name']) ?></td>
              <td class="text-secondary" style="max-width:200px"><?= htmlspecialchars($r['reason']) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($r['status']) ?></span></td>
              <td class="pe-4 text-end">
                <?php if ($r['status']==='pending'): ?>
                <button class="btn btn-xs btn-outline-success" onclick="decide(<?= $r['id'] ?>,'approved')"><i class="bi bi-check"></i></button>
                <button class="btn btn-xs btn-outline-danger" onclick="decide(<?= $r['id'] ?>,'rejected')"><i class="bi bi-x"></i></button>
                <?php else: ?>
                <span class="text-secondary">—</span>
                <?php endif; ?>
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
<script>
const CSRF = '<?= $csrf_token ?>';
async function decide(id, status) {
  if (!confirm(`Mark this request as ${status}?`)) return;
  const r = await fetch(`/shifts/change-requests/${id}/approve`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token:CSRF, status}),
  });
  const d = await r.json();
  if (d.success) location.reload(); else alert(d.message||'Error.');
}
</script>
<?php $this->stop() ?>
