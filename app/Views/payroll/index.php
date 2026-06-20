<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Payroll Management</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item active text-secondary">Payroll</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <a href="/payroll/salary" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-person-badge me-1"></i>Salary Assignment
      </a>
      <a href="/payroll/structures" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-diagram-3 me-1"></i>Structures
      </a>
      <a href="/payroll/components" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-list-ul me-1"></i>Components
      </a>
      <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPeriodModal">
        <i class="bi bi-plus-lg me-1"></i>New Period
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card card-dark border-0 h-100">
        <div class="card-body">
          <p class="text-secondary small mb-1">Total Periods</p>
          <h3 class="fw-bold text-white mb-0"><?= $total ?></h3>
        </div>
      </div>
    </div>
    <?php
      $lastPeriod = $periods[0] ?? null;
      $processed  = array_filter($periods, fn($p) => in_array($p['status'], ['processed','approved','paid']));
    ?>
    <div class="col-6 col-lg-3">
      <div class="card card-dark border-0 h-100">
        <div class="card-body">
          <p class="text-secondary small mb-1">Last Net Payroll</p>
          <h3 class="fw-bold text-success mb-0">
            <?= $lastPeriod ? number_format((float)$lastPeriod['total_payroll'], 0) : '—' ?>
          </h3>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-dark border-0 h-100">
        <div class="card-body">
          <p class="text-secondary small mb-1">Last Employees Paid</p>
          <h3 class="fw-bold text-white mb-0">
            <?= $lastPeriod ? ($lastPeriod['slip_count'] ?? 0) : '—' ?>
          </h3>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-dark border-0 h-100">
        <div class="card-body">
          <p class="text-secondary small mb-1">Processed This Year</p>
          <h3 class="fw-bold text-info mb-0"><?= count($processed) ?></h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Periods Table -->
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0">
      <h6 class="text-white mb-0">Payroll Periods</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr class="text-secondary small">
              <th class="ps-4">Period</th>
              <th>Date Range</th>
              <th>Employees</th>
              <th>Total Net</th>
              <th>Status</th>
              <th>Created By</th>
              <th class="text-end pe-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($periods)): ?>
              <tr>
                <td colspan="7" class="text-center text-secondary py-5">
                  <i class="bi bi-cash-stack fs-2 d-block mb-2"></i>
                  No payroll periods yet. Create one to get started.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($periods as $p): ?>
              <tr>
                <td class="ps-4">
                  <span class="fw-semibold text-white"><?= htmlspecialchars($p['name']) ?></span>
                </td>
                <td class="text-secondary">
                  <?= date('d M Y', strtotime($p['period_from'])) ?> –
                  <?= date('d M Y', strtotime($p['period_to'])) ?>
                </td>
                <td><?= (int)($p['slip_count'] ?? 0) ?></td>
                <td class="text-success fw-semibold">
                  <?= number_format((float)($p['total_payroll'] ?? 0), 2) ?>
                </td>
                <td>
                  <?php
                    $badge = match ($p['status']) {
                      'draft'      => 'secondary',
                      'processing' => 'warning',
                      'processed'  => 'info',
                      'approved'   => 'primary',
                      'paid'       => 'success',
                      'closed'     => 'dark',
                      default      => 'secondary',
                    };
                  ?>
                  <span class="badge bg-<?= $badge ?>"><?= ucfirst($p['status']) ?></span>
                </td>
                <td class="text-secondary"><?= htmlspecialchars($p['created_by_name'] ?? '') ?></td>
                <td class="text-end pe-4">
                  <div class="d-flex gap-2 justify-content-end">
                    <?php if ($p['status'] === 'draft'): ?>
                      <a href="/payroll/process/<?= $p['id'] ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-play-fill me-1"></i>Process
                      </a>
                    <?php else: ?>
                      <a href="/payroll/view/<?= $p['id'] ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-eye me-1"></i>View
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total > $perPage): ?>
      <div class="d-flex justify-content-between align-items-center px-4 py-3">
        <small class="text-secondary">
          Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?>
        </small>
        <?php include '_partials/pagination.php' ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Create Period Modal -->
<div class="modal fade" id="createPeriodModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <form action="/payroll/create" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-white">New Payroll Period</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-secondary">Period Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-dark" placeholder="e.g. May 2025 Payroll" required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label text-secondary">From Date <span class="text-danger">*</span></label>
              <input type="date" name="period_from" class="form-control form-control-dark" required>
            </div>
            <div class="col-6">
              <label class="form-label text-secondary">To Date <span class="text-danger">*</span></label>
              <input type="date" name="period_to" class="form-control form-control-dark" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label text-secondary">Notes</label>
            <textarea name="notes" class="form-control form-control-dark" rows="2" placeholder="Optional notes…"></textarea>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Period</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Auto-fill period name on date change
document.querySelector('[name="period_from"]').addEventListener('change', function () {
  const from = new Date(this.value);
  if (!isNaN(from)) {
    const name = document.querySelector('[name="name"]');
    if (!name.value) {
      name.value = from.toLocaleString('default', { month: 'long' }) + ' ' + from.getFullYear() + ' Payroll';
    }
  }
});
</script>
<?php $this->stop() ?>
