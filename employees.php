<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);
$pdo = db();
$u = current_user();

$st = $pdo->prepare('SELECT id, status FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([$u['id']]);
$shop = $st->fetch();
if (!$shop) {
    flash_set('error', 'Create a shop first.');
    redirect('/shop_owner/setup_shop.php');
}

// Employee approval flow removed. Employees are active immediately after signup.

$employees = $pdo->prepare('SELECT e.id, e.name, e.phone, e.active, e.roleid FROM employee e WHERE e.shopid=? ORDER BY e.id DESC');
$employees->execute([(int)$shop['id']]);
$employees = $employees->fetchAll();

$title = 'Employees';
?>
<div class="card">
  <div class="h1">Employees</div>
  <div class="muted">Share your <b>Shop ID: <?= (int)$shop['id'] ?></b> so employees can request joining.</div>
</div>

<div class="card">
  <h3>Employee List</h3>
  <?php if (!$employees): ?>
    <div class="muted">No employees yet.</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($employees as $e): ?>
        <tr>
          <td><?= (int)$e['id'] ?></td>
          <td><?= h($e['name'] ?? '') ?></td>
          <td><?= h($e['phone'] ?? '') ?></td>
          <td>
            <?php if ($e['active']==='active'): ?><span class="badge ok">active</span>
            <?php elseif ($e['active']==='pending'): ?><span class="badge warn">pending</span>
            <?php else: ?><span class="badge danger"><?= h($e['active']) ?></span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <a class="btn secondary" href="dashboard.php">Back</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
