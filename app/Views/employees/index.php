<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Employees</h4>
      <p class="text-secondary small mb-0"><?= number_format($total) ?> employee<?= $total!=1?'s':'' ?> found</p>
    </div>
    <?php if (\App\Core\Auth::can('employees.create')): ?>
    <a href="/employees/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Employee</a>
    <?php endif; ?>
  </div>

  <!-- Filters -->
  <div class="card card-dark border-0 mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
          <input type="text" name="search" class="form-control form-control-dark form-control-sm"
                 placeholder="Search name, code, email…" value="<?= htmlspecialchars($filters['search']??'') ?>">
        </div>
        <div class="col-md-3">
          <select name="department_id" class="form-select form-select-dark form-select-sm">
            <option value="">All Departments</option>
            <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($filters['department_id']??0)==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select form-select-dark form-select-sm">
            <option value="active"     <?= ($filters['status']??'')=='active'?'selected':'' ?>>Active</option>
            <option value="inactive"   <?= ($filters['status']??'')=='inactive'?'selected':'' ?>>Inactive</option>
            <option value="terminated" <?= ($filters['status']??'')=='terminated'?'selected':'' ?>>Terminated</option>
            <option value=""           <?= ($filters['status']??'')==''?'selected':'' ?>>All Status</option>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search me-1"></i>Search</button>
          <a href="/employees" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr class="text-secondary small">
              <th class="ps-4">Employee</th><th>Department</th><th>Designation</th>
              <th>Join Date</th><th>Badge</th><th>Status</th><th class="text-end pe-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($employees)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-5">
              <i class="bi bi-people fs-2 d-block mb-2"></i>No employees found.
            </td></tr>
            <?php else: ?>
            <?php foreach ($employees as $e): ?>
            <?php $sc = match($e['status']) { 'active'=>'success','inactive'=>'warning','terminated'=>'danger',default=>'secondary' }; ?>
            <tr>
              <td class="ps-4">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle bg-primary bg-opacity-20 d-flex align-items-center justify-content-center fw-semibold text-primary flex-shrink-0" style="width:36px;height:36px;font-size:.8rem">
                    <?php if (!empty($e['profile_photo']) && file_exists(base_path($e['profile_photo']))): ?>
                    <img src="/<?= htmlspecialchars($e['profile_photo']) ?>" class="rounded-circle" style="width:36px;height:36px;object-fit:cover">
                    <?php else: ?>
                    <?= strtoupper(substr($e['full_name'],0,1)) ?>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="fw-semibold text-white small"><?= htmlspecialchars($e['full_name']) ?></div>
                    <div class="text-secondary" style="font-size:.75rem"><?= $e['employee_code'] ?></div>
                  </div>
                </div>
              </td>
              <td class="text-secondary small"><?= htmlspecialchars($e['department_name']??'—') ?></td>
              <td class="text-secondary small"><?= htmlspecialchars($e['designation_name']??'—') ?></td>
              <td class="text-secondary small"><?= date('d M Y', strtotime($e['join_date'])) ?></td>
              <td><code class="text-info small"><?= htmlspecialchars($e['badge_number']??'—') ?></code></td>
              <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($e['status']) ?></span></td>
              <td class="text-end pe-4">
                <div class="d-flex gap-1 justify-content-end">
                  <a href="/employees/<?= $e['id'] ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-eye"></i></a>
                  <?php if (\App\Core\Auth::can('employees.edit')): ?>
                  <a href="/employees/<?= $e['id'] ?>/edit" class="btn btn-xs btn-outline-primary"><i class="bi bi-pencil"></i></a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($total > $perPage): ?>
      <div class="d-flex justify-content-between align-items-center px-4 py-3">
        <small class="text-secondary">Showing <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage,$total) ?> of <?= $total ?></small>
        <?php $q = http_build_query(array_merge($filters,['page'=>'__P__'])); $pages=ceil($total/$perPage); ?>
        <nav><ul class="pagination pagination-sm mb-0">
          <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
          <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?<?= str_replace('__P__',$i,$q) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
        </ul></nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $this->stop() ?>
