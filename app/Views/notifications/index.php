<?php $this->layout('layouts/main', ['title' => $title]) ?>
<?php $this->start('content') ?>
<div class="container-fluid px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-0 fw-semibold text-white">Notifications</h4>
      <p class="text-secondary small mb-0"><?= $unread ?> unread</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" onclick="markAllRead()"><i class="bi bi-check-all me-1"></i>Mark All Read</button>
      <button class="btn btn-outline-danger btn-sm" onclick="deleteAllRead()"><i class="bi bi-trash me-1"></i>Clear Read</button>
    </div>
  </div>

  <div class="card card-dark border-0">
    <div class="card-body p-0">
      <?php if (empty($notifications)): ?>
      <p class="text-secondary text-center py-5 mb-0"><i class="bi bi-bell-slash fs-2 d-block mb-2"></i>No notifications.</p>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($notifications as $n): ?>
        <div class="list-group-item bg-transparent border-secondary d-flex align-items-start gap-3 py-3 <?= $n['is_read']?'':'bg-primary bg-opacity-10' ?>" id="notif-<?= $n['id'] ?>">
          <div class="rounded-circle bg-primary bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px">
            <i class="bi bi-bell text-primary"></i>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between">
              <h6 class="text-white mb-1"><?= htmlspecialchars($n['title']) ?></h6>
              <small class="text-secondary"><?= date('d M, H:i', strtotime($n['created_at'])) ?></small>
            </div>
            <p class="text-secondary small mb-0"><?= htmlspecialchars($n['body']) ?></p>
          </div>
          <div class="d-flex gap-1">
            <?php if (!$n['is_read']): ?>
            <button class="btn btn-xs btn-outline-secondary" onclick="markRead(<?= $n['id'] ?>)" title="Mark Read"><i class="bi bi-check"></i></button>
            <?php endif; ?>
            <button class="btn btn-xs btn-outline-danger" onclick="deleteNotif(<?= $n['id'] ?>)" title="Delete"><i class="bi bi-x"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
const CSRF = '<?= $csrf_token ?>';
async function markRead(id) {
  await fetch(`/notifications/${id}/read`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  location.reload();
}
async function markAllRead() {
  await fetch('/notifications/read-all',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  location.reload();
}
async function deleteNotif(id) {
  await fetch(`/notifications/${id}/delete`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  document.getElementById(`notif-${id}`).remove();
}
async function deleteAllRead() {
  if (!confirm('Delete all read notifications?')) return;
  await fetch('/notifications/delete-all-read',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF})});
  location.reload();
}
</script>
<?php $this->stop() ?>
