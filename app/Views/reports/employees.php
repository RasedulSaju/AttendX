<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-semibold text-white">Employee Report</h4>
    <div class="d-flex gap-2">
      <a href="/reports" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
      <a href="/reports/employees/export?<?= http_build_query($filters) ?>" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Export</a>
    </div>
  </div>
  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3"><select name="status" class="form-select form-select-dark form-select-sm">
          <option value="active" <?= ($filters['status']??'')=='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= ($filters['status']??'')=='inactive'?'selected':'' ?>>Inactive</option>
          <option value="" <?= ($filters['status']??'')==''?'selected':'' ?>>All</option>
        </select></div>
        <div class="col-md-3"><select name="department_id" class="form-select form-select-dark form-select-sm">
          <option value="">All Departments</option>
          <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= ($filters['department_id']??0)==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-3"><button class="btn btn-primary btn-sm">Apply</button></div>
      </form>
    </div>
  </div>
  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small">
          <thead><tr class="text-secondary"><th class="ps-4">Code</th><th>Name</th><th>Dept</th><th>Designation</th><th>Join Date</th><th class="pe-4">Status</th></tr></thead>
          <tbody>
            <?php foreach ($employees as $e): ?>
            <tr>
              <td class="ps-4 text-secondary"><?= $e['employee_code'] ?></td>
              <td class="text-white"><?= htmlspecialchars($e['full_name']) ?></td>
              <td class="text-secondary"><?= htmlspecialchars($e['department_name']??'') ?></td>
              <td class="text-secondary"><?= htmlspecialchars($e['designation_name']??'') ?></td>
              <td class="text-secondary"><?= date('d M Y', strtotime($e['join_date'])) ?></td>
              <td class="pe-4"><span class="badge bg-<?= $e['status']==='active'?'success':'secondary' ?>"><?= ucfirst($e['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php $this->stop() ?>
