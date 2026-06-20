<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Visitor Management</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/access-control" class="text-secondary">Access Control</a></li>
          <li class="breadcrumb-item active text-secondary">Visitors</li>
        </ol>
      </nav>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#visitorModal">
      <i class="bi bi-person-plus me-1"></i>New Visitor Pass
    </button>
  </div>

  <!-- Visitor Passes Table -->
  <div class="card card-dark border-0">
    <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
      <h6 class="text-white mb-0">Visitor Passes (<?= count($passes) ?>)</h6>
      <input type="text" id="visitorSearch" class="form-control form-control-dark form-control-sm w-auto" placeholder="Search visitor…">
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small" id="visitorTable">
          <thead>
            <tr class="text-secondary">
              <th class="ps-4">Visitor</th>
              <th>Badge</th>
              <th>Host</th>
              <th>Purpose</th>
              <th>Valid Period</th>
              <th>Status</th>
              <th>Check In/Out</th>
              <th class="pe-4 text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($passes)): ?>
            <tr><td colspan="8" class="text-center text-secondary py-5">
              <i class="bi bi-person-badge fs-2 d-block mb-2"></i>No visitor passes found.
            </td></tr>
            <?php else: ?>
            <?php foreach ($passes as $p): ?>
            <?php
              $statusColor = match ($p['status']) {
                'active'    => 'success',
                'expired'   => 'secondary',
                'revoked'   => 'danger',
                'completed' => 'info',
                default     => 'secondary',
              };
              $now     = date('Y-m-d H:i:s');
              $expired = $p['valid_to'] < $now;
            ?>
            <tr class="visitor-row" data-search="<?= strtolower($p['visitor_name'].' '.$p['badge_code']) ?>">
              <td class="ps-4">
                <div class="fw-semibold text-white"><?= htmlspecialchars($p['visitor_name']) ?></div>
                <?php if ($p['visitor_company']): ?>
                <small class="text-secondary"><?= htmlspecialchars($p['visitor_company']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <code class="text-info small"><?= htmlspecialchars($p['badge_code']) ?></code>
              </td>
              <td class="text-secondary"><?= htmlspecialchars($p['host_name']) ?></td>
              <td class="text-secondary" style="max-width:180px">
                <span class="d-inline-block text-truncate" style="max-width:180px" title="<?= htmlspecialchars($p['purpose']) ?>">
                  <?= htmlspecialchars($p['purpose']) ?>
                </span>
              </td>
              <td class="text-secondary">
                <div><?= date('d M Y H:i', strtotime($p['valid_from'])) ?></div>
                <div><?= date('d M Y H:i', strtotime($p['valid_to'])) ?></div>
              </td>
              <td>
                <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($p['status']) ?></span>
                <?php if ($expired && $p['status'] === 'active'): ?>
                <span class="badge bg-warning ms-1">Expired</span>
                <?php endif; ?>
              </td>
              <td class="text-secondary small">
                <?php if ($p['checked_in_at']): ?>
                  <div class="text-success"><i class="bi bi-arrow-right-circle me-1"></i><?= date('H:i', strtotime($p['checked_in_at'])) ?></div>
                <?php else: ?>
                  <span class="text-secondary">—</span>
                <?php endif; ?>
                <?php if ($p['checked_out_at']): ?>
                  <div class="text-danger"><i class="bi bi-arrow-left-circle me-1"></i><?= date('H:i', strtotime($p['checked_out_at'])) ?></div>
                <?php endif; ?>
              </td>
              <td class="pe-4 text-end">
                <div class="d-flex gap-1 justify-content-end">
                  <?php if ($p['status'] === 'active' && !$p['checked_out_at']): ?>
                  <button class="btn btn-xs btn-outline-info" onclick="checkoutVisitor(<?= $p['id'] ?>)" title="Check Out">
                    <i class="bi bi-box-arrow-right"></i>
                  </button>
                  <?php endif; ?>
                  <?php if ($p['status'] === 'active'): ?>
                  <button class="btn btn-xs btn-outline-danger" onclick="revokeVisitor(<?= $p['id'] ?>)" title="Revoke">
                    <i class="bi bi-x-circle"></i>
                  </button>
                  <?php endif; ?>
                  <button class="btn btn-xs btn-outline-secondary" onclick="printPass(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" title="Print Pass">
                    <i class="bi bi-printer"></i>
                  </button>
                </div>
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

<!-- New Visitor Pass Modal -->
<div class="modal fade" id="visitorModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <form action="/access-control/visitors" method="POST" novalidate id="visitorForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-white">New Visitor Pass</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <!-- Visitor Info -->
            <div class="col-12">
              <h6 class="text-secondary small text-uppercase mb-2">Visitor Information</h6>
            </div>
            <div class="col-md-6">
              <label class="form-label text-secondary">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="visitor_name" class="form-control form-control-dark" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label text-secondary">Company / Organization</label>
              <input type="text" name="visitor_company" class="form-control form-control-dark" maxlength="150">
            </div>
            <div class="col-md-4">
              <label class="form-label text-secondary">Phone</label>
              <input type="tel" name="visitor_phone" class="form-control form-control-dark" maxlength="20">
            </div>
            <div class="col-md-4">
              <label class="form-label text-secondary">ID Type <span class="text-danger">*</span></label>
              <select name="visitor_id_type" class="form-select form-select-dark" required>
                <option value="nid">National ID</option>
                <option value="passport">Passport</option>
                <option value="driving_license">Driving License</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-secondary">ID Number <span class="text-danger">*</span></label>
              <input type="text" name="visitor_id_no" class="form-control form-control-dark" required maxlength="50">
            </div>

            <!-- Visit Details -->
            <div class="col-12 mt-2">
              <h6 class="text-secondary small text-uppercase mb-2">Visit Details</h6>
            </div>
            <div class="col-md-6">
              <label class="form-label text-secondary">Host Employee <span class="text-danger">*</span></label>
              <select name="host_employee_id" class="form-select form-select-dark" required>
                <option value="">Select employee…</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_code'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label text-secondary">Allowed Zones</label>
              <select name="zones[]" class="form-select form-select-dark" multiple style="height:80px">
                <?php foreach ($zones as $z): ?>
                <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-secondary">Hold Ctrl/Cmd to select multiple</small>
            </div>
            <div class="col-12">
              <label class="form-label text-secondary">Purpose of Visit <span class="text-danger">*</span></label>
              <textarea name="purpose" class="form-control form-control-dark" rows="2" required minlength="10" maxlength="500"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label text-secondary">Valid From <span class="text-danger">*</span></label>
              <input type="datetime-local" name="valid_from" class="form-control form-control-dark" required
                     value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label text-secondary">Valid To <span class="text-danger">*</span></label>
              <input type="datetime-local" name="valid_to" class="form-control form-control-dark" required
                     value="<?= date('Y-m-d\TH:i', strtotime('+8 hours')) ?>">
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-person-check me-1"></i>Create Pass</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Print Pass Template (hidden) -->
<div id="printTemplate" class="d-none"></div>

<script>
const CSRF = '<?= $csrf_token ?>';

document.getElementById('visitorSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.visitor-row').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});

async function checkoutVisitor(id) {
  if (!confirm('Check out this visitor?')) return;
  const r    = await fetch('/api/v1/access/visitor/' + id + '/checkout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ csrf_token: CSRF }),
  });
  const data = await r.json();
  if (data.success) location.reload();
  else alert(data.message || 'Error.');
}

async function revokeVisitor(id) {
  if (!confirm('Revoke this visitor pass?')) return;
  const r    = await fetch('/api/v1/access/visitor/' + id + '/revoke', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ csrf_token: CSRF }),
  });
  const data = await r.json();
  if (data.success) location.reload();
  else alert(data.message || 'Error.');
}

function printPass(pass) {
  const w = window.open('', '_blank', 'width=400,height=550');
  w.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Visitor Pass – ${pass.badge_code}</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 24px; color: #000; }
        h2 { text-align: center; margin-bottom: 4px; }
        .badge-code { text-align: center; font-size: 1.5rem; font-weight: bold; letter-spacing: 4px;
                      background: #f0f0f0; padding: 10px; border-radius: 6px; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        td { padding: 6px 4px; border-bottom: 1px solid #eee; }
        td:first-child { color: #666; width: 40%; }
        .footer { text-align: center; margin-top: 16px; font-size: 0.75rem; color: #999; }
      </style>
    </head>
    <body>
      <h2>VISITOR PASS</h2>
      <div class="badge-code">${pass.badge_code}</div>
      <table>
        <tr><td>Name</td><td><strong>${pass.visitor_name}</strong></td></tr>
        ${pass.visitor_company ? `<tr><td>Company</td><td>${pass.visitor_company}</td></tr>` : ''}
        <tr><td>Host</td><td>${pass.host_name}</td></tr>
        <tr><td>Purpose</td><td>${pass.purpose}</td></tr>
        <tr><td>Valid From</td><td>${pass.valid_from}</td></tr>
        <tr><td>Valid To</td><td>${pass.valid_to}</td></tr>
        <tr><td>Status</td><td>${pass.status.toUpperCase()}</td></tr>
      </table>
      <p class="footer">This pass must be worn visibly at all times. Return to reception upon exit.</p>
    </body>
    </html>
  `);
  w.document.close();
  w.print();
}
</script>
<?php $this->stop() ?>
