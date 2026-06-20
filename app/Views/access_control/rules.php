<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">
        Access Rules: <span class="text-primary"><?= htmlspecialchars($zone['name']) ?></span>
      </h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/access-control" class="text-secondary">Access Control</a></li>
          <li class="breadcrumb-item"><a href="/access-control/zones" class="text-secondary">Zones</a></li>
          <li class="breadcrumb-item active text-secondary">Rules</li>
        </ol>
      </nav>
    </div>
    <?php
      $secLabel = match ((int)$zone['security_level']) {
        1 => ['Low','success'], 2 => ['Medium','info'], 3 => ['High','warning'], 4 => ['Critical','danger'], default => ['—','secondary']
      };
    ?>
    <span class="badge bg-<?= $secLabel[1] ?> fs-6">Security: <?= $secLabel[0] ?></span>
  </div>

  <div class="row g-4">

    <!-- Add Rule -->
    <div class="col-lg-4">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0">Add Access Rule</h6>
        </div>
        <div class="card-body">
          <form action="/access-control/rules/<?= $zone['id'] ?>" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="mb-3">
              <label class="form-label text-secondary">Permission <span class="text-danger">*</span></label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="permission" value="allow" id="permAllow" checked>
                  <label class="form-check-label text-success" for="permAllow">Allow</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="permission" value="deny" id="permDeny">
                  <label class="form-check-label text-danger" for="permDeny">Deny</label>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label text-secondary">Apply To <span class="text-danger">*</span></label>
              <select name="rule_type" id="ruleType" class="form-select form-select-dark" required onchange="toggleRef(this.value)">
                <option value="all">All Employees</option>
                <option value="employee">Specific Employee</option>
                <option value="department">Department</option>
                <option value="designation">Designation</option>
              </select>
            </div>

            <div class="mb-3 d-none" id="refBox">
              <label class="form-label text-secondary">Select <span class="text-danger">*</span></label>
              <select name="ref_id" id="refSelect" class="form-select form-select-dark">
                <option value="">—</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label text-secondary">Schedule (optional)</label>
              <select name="schedule_id" class="form-select form-select-dark">
                <option value="">Any Time</option>
                <?php foreach ($schedules as $sc): ?>
                <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label text-secondary small">Valid From</label>
                <input type="date" name="valid_from" class="form-control form-control-dark form-control-sm">
              </div>
              <div class="col-6">
                <label class="form-label text-secondary small">Valid To</label>
                <input type="date" name="valid_to" class="form-control form-control-dark form-control-sm">
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-shield-plus me-1"></i>Add Rule
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Rules List -->
    <div class="col-lg-8">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="text-white mb-0">Rules (<?= count($rules) ?>)</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($rules)): ?>
          <div class="text-center text-secondary py-5">
            <i class="bi bi-shield-x fs-2 d-block mb-2"></i>No access rules defined. Everyone is denied by default.
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 small">
              <thead>
                <tr class="text-secondary">
                  <th class="ps-4">Permission</th>
                  <th>Applies To</th>
                  <th>Schedule</th>
                  <th>Valid Period</th>
                  <th class="pe-4 text-end">Remove</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rules as $r): ?>
                <tr>
                  <td class="ps-4">
                    <span class="badge bg-<?= $r['permission'] === 'allow' ? 'success' : 'danger' ?> bg-opacity-20 text-<?= $r['permission'] === 'allow' ? 'success' : 'danger' ?>">
                      <i class="bi bi-<?= $r['permission'] === 'allow' ? 'check' : 'x' ?>"></i>
                      <?= ucfirst($r['permission']) ?>
                    </span>
                  </td>
                  <td class="text-white">
                    <?php if ($r['rule_type'] === 'all'): ?>
                      <span class="text-secondary">All Employees</span>
                    <?php elseif ($r['rule_type'] === 'employee'): ?>
                      <?php
                        $emp = array_filter($employees, fn($e) => $e['id'] == $r['ref_id']);
                        $emp = reset($emp);
                      ?>
                      <i class="bi bi-person text-secondary me-1"></i>
                      <?= $emp ? htmlspecialchars($emp['full_name']) : 'Employee #'.$r['ref_id'] ?>
                    <?php elseif ($r['rule_type'] === 'department'): ?>
                      <?php
                        $dept = array_filter($departments, fn($d) => $d['id'] == $r['ref_id']);
                        $dept = reset($dept);
                      ?>
                      <i class="bi bi-building text-secondary me-1"></i>
                      <?= $dept ? htmlspecialchars($dept['name']) : 'Dept #'.$r['ref_id'] ?>
                    <?php elseif ($r['rule_type'] === 'designation'): ?>
                      <?php
                        $des = array_filter($designations, fn($d) => $d['id'] == $r['ref_id']);
                        $des = reset($des);
                      ?>
                      <i class="bi bi-briefcase text-secondary me-1"></i>
                      <?= $des ? htmlspecialchars($des['name']) : 'Designation #'.$r['ref_id'] ?>
                    <?php endif; ?>
                  </td>
                  <td class="text-secondary">
                    <?= $r['schedule_name'] ? htmlspecialchars($r['schedule_name']) : 'Any time' ?>
                  </td>
                  <td class="text-secondary">
                    <?php if ($r['valid_from'] || $r['valid_to']): ?>
                      <?= $r['valid_from'] ?? '∞' ?> – <?= $r['valid_to'] ?? '∞' ?>
                    <?php else: ?>
                      <span class="text-secondary">Permanent</span>
                    <?php endif; ?>
                  </td>
                  <td class="pe-4 text-end">
                    <button class="btn btn-xs btn-outline-danger" onclick="deleteRule(<?= $r['id'] ?>)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
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

const employees    = <?= json_encode(array_map(fn($e) => ['id'=>$e['id'],'label'=>$e['full_name'].' ('.$e['employee_code'].')'], $employees)) ?>;
const departments  = <?= json_encode(array_map(fn($d) => ['id'=>$d['id'],'label'=>$d['name']], $departments)) ?>;
const designations = <?= json_encode(array_map(fn($d) => ['id'=>$d['id'],'label'=>$d['name']], $designations)) ?>;

function toggleRef(type) {
  const box = document.getElementById('refBox');
  const sel = document.getElementById('refSelect');

  if (type === 'all') {
    box.classList.add('d-none');
    sel.required = false;
    return;
  }

  box.classList.remove('d-none');
  sel.required = true;
  sel.innerHTML = '<option value="">Select…</option>';

  const list = type === 'employee' ? employees : type === 'department' ? departments : designations;
  list.forEach(item => {
    const opt = document.createElement('option');
    opt.value = item.id;
    opt.textContent = item.label;
    sel.appendChild(opt);
  });
}

async function deleteRule(id) {
  if (!confirm('Remove this access rule?')) return;
  const r    = await fetch('/api/v1/access/rule/' + id, {
    method: 'DELETE',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({csrf_token: CSRF}),
  });
  const data = await r.json();
  if (data.success) location.reload();
  else alert('Error removing rule.');
}
</script>
<?php $this->stop() ?>
