<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Process Attendance</h4>
    <a href="/attendance" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Run Processing</h6></div>
        <div class="card-body">
          <p class="text-secondary small">Converts raw device punches into daily attendance records: present/absent/late status, work minutes, and overtime.</p>
          <form action="/attendance/process" method="POST" onsubmit="return confirm('Process attendance for the selected date? This will overwrite existing records for that day.')">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="mb-3">
              <label class="form-label text-secondary">Date *</label>
              <input type="date" name="date" class="form-control form-control-dark" value="<?= date('Y-m-d') ?>" required max="<?= date('Y-m-d') ?>">
            </div>
            <button type="submit" class="btn btn-warning w-100"><i class="bi bi-cpu me-1"></i>Run Processing</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary"><h6 class="text-white mb-0">Pending Sync Queue (<?= count($pending) ?>)</h6></div>
        <div class="card-body p-0">
          <?php if (empty($pending)): ?>
          <p class="text-secondary text-center py-4 mb-0">No devices pending sync.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0 small">
              <thead><tr class="text-secondary"><th class="ps-4">Device</th><th class="pe-4">Queued At</th></tr></thead>
              <tbody>
                <?php foreach ($pending as $p): ?>
                <tr><td class="ps-4 text-white"><?= htmlspecialchars($p['device_name']) ?></td>
                    <td class="pe-4 text-secondary"><?= date('d M H:i', strtotime($p['queued_at'])) ?></td></tr>
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
<?php $this->stop() ?>
