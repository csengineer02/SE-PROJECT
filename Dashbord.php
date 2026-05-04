<?php
require_once __DIR__ . '/../includes/dashboard_header.php';
require_once __DIR__ . '/includes/admin_auth.php';
admin_require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $shopId = (int)($_POST['shop_id'] ?? 0);
    if ($shopId > 0 && in_array($action, ['verified','rejected'], true)) {
        $st = $pdo->prepare('UPDATE shop SET status=? WHERE id=?');
        $st->execute([$action, $shopId]);
        flash_set('success', 'Shop updated to: ' . $action);
        redirect('/admin/dashboard.php');
    }
}

$pending = $pdo->query("SELECT s.id, s.name, s.type, s.address, s.created_at, s.status, so.name AS owner_name, so.phone AS owner_phone
                        FROM shop s
                        JOIN shopowner so ON so.id = s.shopownerid
                        WHERE s.status='pending'
                        ORDER BY s.created_at DESC")->fetchAll();

$stats = $pdo->query("SELECT 
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status='verified' THEN 1 ELSE 0 END) AS verified,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected
  FROM shop")->fetch();

$title = 'Admin Dashboard';
?>
<div class="app-card">
  <div class="app-card-body">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
      <div>
        <h1 class="h4 fw-bold mb-1">Admin Dashboard</h1>
        <div class="text-muted">Verify or reject shops.</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-warning">Pending: <?= (int)($stats['pending'] ?? 0) ?></span>
        <span class="badge text-bg-success">Verified: <?= (int)($stats['verified'] ?? 0) ?></span>
        <span class="badge text-bg-danger">Rejected: <?= (int)($stats['rejected'] ?? 0) ?></span>
      </div>
    </div>
  </div>
</div>

<div class="app-card mt-3">
  <div class="app-card-body">
  <h2 class="h5 fw-bold mb-3">Pending Shop Verifications</h2>
  <?php if (!$pending): ?>
    <div class="text-muted">No pending shops right now.</div>
  <?php else: ?>
    <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>ID</th><th>Shop</th><th>Owner</th><th>Address</th><th>Created</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pending as $s): ?>
        <tr>
          <td><?= (int)$s['id'] ?></td>
          <td><?= h($s['name']) ?><div class="text-muted small"><?= h($s['type']) ?></div></td>
          <td><?= h($s['owner_name'] ?? '') ?><div class="text-muted small"><?= h($s['owner_phone'] ?? '') ?></div></td>
          <td><?= h($s['address']) ?></td>
          <td><?= h($s['created_at']) ?></td>
          <td>
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="shop_id" value="<?= (int)$s['id'] ?>" />
              <button class="btn btn-success btn-sm" name="action" value="verified" type="submit">Verify</button>
              <button class="btn btn-outline-danger btn-sm" name="action" value="rejected" type="submit">Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
  </div>
</div>

<div class="mt-3">
  <a class="btn btn-outline-danger" href="<?= BASE_URL ?>/admin/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout Admin</a>
</div>
<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
