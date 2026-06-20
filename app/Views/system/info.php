<?php $this->layout('layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>
<div class="container-fluid px-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">System Information</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/dashboard" class="text-secondary">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="/system/settings" class="text-secondary">System</a></li>
          <li class="breadcrumb-item active text-secondary">Info</li>
        </ol>
      </nav>
    </div>
  </div>

  <div class="row g-4">

    <!-- Server Info -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0"><i class="bi bi-server me-2 text-primary"></i>Server Environment</h6>
        </div>
        <div class="card-body p-0">
          <table class="table table-dark table-sm mb-0">
            <?php
              $rows = [
                ['PHP Version',     $info['php_version']],
                ['Database',        'MySQL ' . $info['db_version']],
                ['Operating System',$info['os']],
                ['Web Server',      $info['server_software']],
                ['Timezone',        $info['timezone']],
                ['Memory Limit',    $info['memory_limit']],
                ['Upload Max Size', $info['upload_max']],
                ['Max Exec Time',   $info['max_execution'] . 's'],
              ];
              foreach ($rows as [$label, $value]):
            ?>
            <tr>
              <td class="text-secondary ps-4" style="width:45%"><?= $label ?></td>
              <td class="text-white"><?= htmlspecialchars((string)$value) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
              <td class="text-secondary ps-4">Disk Free</td>
              <td class="text-<?= $info['disk_free'] < 1073741824 ? 'danger' : 'success' ?>">
                <?= round($info['disk_free'] / 1073741824, 2) ?> GB
                of <?= round($info['disk_total'] / 1073741824, 2) ?> GB
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>

    <!-- PHP Extensions -->
    <div class="col-lg-6">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0"><i class="bi bi-puzzle me-2 text-info"></i>PHP Extensions</h6>
        </div>
        <div class="card-body">
          <?php
            $required = ['pdo','pdo_mysql','mbstring','openssl','json','curl','gd','zip','fileinfo','intl'];
            foreach ($required as $ext):
              $loaded = extension_loaded($ext);
          ?>
          <div class="d-flex align-items-center justify-content-between py-1 border-bottom border-secondary">
            <span class="text-secondary"><?= $ext ?></span>
            <span class="badge bg-<?= $loaded ? 'success' : 'danger' ?>">
              <?= $loaded ? 'Loaded' : 'Missing' ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Database Tables -->
    <div class="col-12">
      <div class="card card-dark border-0">
        <div class="card-header bg-transparent border-bottom border-secondary">
          <h6 class="text-white mb-0"><i class="bi bi-table me-2 text-success"></i>Database Tables</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-dark table-hover table-sm align-middle mb-0 small">
              <thead>
                <tr class="text-secondary">
                  <th class="ps-4">Table Name</th>
                  <th>Rows (est.)</th>
                  <th class="pe-4">Size</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dbStats as $t): ?>
                <tr>
                  <td class="ps-4 font-monospace text-secondary"><?= htmlspecialchars($t['table_name']) ?></td>
                  <td><?= number_format((int)$t['table_rows']) ?></td>
                  <td class="pe-4 text-secondary"><?= $t['size_mb'] ?> MB</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<?php $this->stop() ?>
