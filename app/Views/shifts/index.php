<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Shifts</h4>
    <div class="d-flex gap-2">
      <a href="/shifts/roster" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar3 me-1"></i>Roster</a>
      <a href="/shifts/change-requests" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Change Requests</a>
      <button class="btn btn-primary btn-sm" onclick="openShiftModal()"><i class="bi bi-plus-lg me-1"></i>Add Shift</button>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($shifts as $s): ?>
    <div class="col-md-4">
      <div class="card card-dark border-0">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between mb-2">
            <h6 class="text-white fw-semibold mb-0"><?= htmlspecialchars($s['name']) ?></h6>
            <span class="badge bg-<?= $s['is_active']?'success':'secondary' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span>
          </div>
          <p class="text-secondary small mb-2"><i class="bi bi-clock me-1"></i><?= substr($s['start_time'],0,5) ?> – <?= substr($s['end_time'],0,5) ?></p>
          <div class="d-flex gap-3 small text-secondary mb-3">
            <span>Grace: <?= $s['grace_minutes'] ?>m</span>
            <span>OT: <?= $s['ot_threshold'] ?>m</span>
            <span>Assigned: <?= $s['assigned_employees'] ?></span>
          </div>
          <button class="btn btn-outline-primary btn-sm w-100" onclick='openShiftModal(<?= htmlspecialchars(json_encode($s),ENT_QUOTES) ?>)'>Edit</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal fade" id="shiftModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark">
    <form action="/shifts/save" method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
      <input type="hidden" name="id" id="sId">
      <div class="modal-header border-secondary"><h5 class="modal-title text-white" id="sTitle">Add Shift</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input type="text" name="name" id="sName" class="form-control form-control-dark" required></div>
        <div class="mb-3"><label class="form-label text-secondary">Code</label><input type="text" name="code" id="sCode" class="form-control form-control-dark text-uppercase"></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label text-secondary">Start *</label><input type="time" name="start_time" id="sStart" class="form-control form-control-dark" required></div>
          <div class="col-6"><label class="form-label text-secondary">End *</label><input type="time" name="end_time" id="sEnd" class="form-control form-control-dark" required></div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-4"><label class="form-label text-secondary small">Grace (min)</label><input type="number" name="grace_minutes" id="sGrace" class="form-control form-control-dark" value="15"></div>
          <div class="col-4"><label class="form-label text-secondary small">OT Threshold</label><input type="number" name="ot_threshold" id="sOt" class="form-control form-control-dark" value="30"></div>
          <div class="col-4"><label class="form-label text-secondary small">Half Day (min)</label><input type="number" name="half_day_minutes" id="sHalf" class="form-control form-control-dark" value="240"></div>
        </div>
        <label class="form-label text-secondary small">Working Days</label>
        <div class="d-flex gap-2 mb-3" id="dayChecks">
          <?php $dayLabels=['S','M','T','W','T','F','S']; for($d=0;$d<7;$d++): ?>
          <div class="form-check"><input class="form-check-input day-check" type="checkbox" name="working_days[]" value="<?= $d ?>" <?= $d>=1&&$d<=5?'checked':'' ?>>
            <label class="form-check-label text-secondary small"><?= $dayLabels[$d] ?></label></div>
          <?php endfor; ?>
        </div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="sActive" value="1" checked>
          <label class="form-check-label text-secondary">Active</label></div>
      </div>
      <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div></div>
</div>
<script>
function openShiftModal(s=null) {
  document.getElementById('sId').value=s?s.id:'';
  document.getElementById('sName').value=s?s.name:'';
  document.getElementById('sCode').value=s?(s.code||''):'';
  document.getElementById('sStart').value=s?s.start_time.substring(0,5):'';
  document.getElementById('sEnd').value=s?s.end_time.substring(0,5):'';
  document.getElementById('sGrace').value=s?s.grace_minutes:15;
  document.getElementById('sOt').value=s?s.ot_threshold:30;
  document.getElementById('sHalf').value=s?s.half_day_minutes:240;
  document.getElementById('sActive').checked=s?!!s.is_active:true;
  document.getElementById('sTitle').textContent=s?'Edit Shift':'Add Shift';
  new bootstrap.Modal(document.getElementById('shiftModal')).show();
}
</script>
<?php $this->stop() ?>
