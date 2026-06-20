<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Holidays — <?= $year ?></h4>
    <div class="d-flex gap-2">
      <form method="GET" class="d-flex gap-2">
        <input type="number" name="year" class="form-control form-control-dark form-control-sm" value="<?= $year ?>" style="width:100px">
        <button class="btn btn-outline-secondary btn-sm">Go</button>
      </form>
      <a href="/leave" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
      <button class="btn btn-primary btn-sm" onclick="openHolidayModal()"><i class="bi bi-plus-lg me-1"></i>Add Holiday</button>
    </div>
  </div>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead><tr class="text-secondary small"><th class="ps-4">Name</th><th>Date</th><th>Day</th><th>Type</th><th class="pe-4 text-end">Actions</th></tr></thead>
          <tbody>
            <?php if (empty($holidays)): ?>
            <tr><td colspan="5" class="text-center text-secondary py-5">No holidays defined for <?= $year ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($holidays as $h): ?>
            <tr>
              <td class="ps-4 text-white"><?= htmlspecialchars($h['name']) ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($h['holiday_date'])) ?></td>
              <td class="text-secondary"><?= date('l', strtotime($h['holiday_date'])) ?></td>
              <td><span class="badge bg-info"><?= ucfirst($h['type']) ?></span></td>
              <td class="pe-4 text-end">
                <button class="btn btn-xs btn-outline-danger" onclick="deleteHoliday(<?= $h['id'] ?>)"><i class="bi bi-trash"></i></button>
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

<div class="modal fade" id="holidayModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark">
    <form action="/leave/holidays/save" method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
      <div class="modal-header border-secondary"><h5 class="modal-title text-white">Add Holiday</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input type="text" name="name" class="form-control form-control-dark" required></div>
        <div class="mb-3"><label class="form-label text-secondary">Date *</label><input type="date" name="holiday_date" class="form-control form-control-dark" required></div>
        <div class="mb-3"><label class="form-label text-secondary">Type</label>
          <select name="type" class="form-select form-select-dark">
            <option value="public">Public</option><option value="optional">Optional</option><option value="company">Company</option>
          </select></div>
        <div><label class="form-label text-secondary">Description</label><textarea name="description" class="form-control form-control-dark" rows="2"></textarea></div>
      </div>
      <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div></div>
</div>

<script>
const CSRF = '<?= $csrf_token ?>';
function openHolidayModal() { new bootstrap.Modal(document.getElementById('holidayModal')).show(); }
async function deleteHoliday(id) {
  if (!confirm('Delete this holiday?')) return;
  await fetch(`/leave/holidays/${id}/delete`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  location.reload();
}
</script>
<?php $this->stop() ?>
