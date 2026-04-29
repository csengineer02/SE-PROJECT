<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);

$pdo = db();
$u = current_user();

$st = $pdo->prepare('SELECT id FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([(int)$u['id']]);
$shop = $st->fetch();
if (!$shop) {
    flash_set('error', 'Create a shop first.');
    redirect('/shop_owner/setup_shop.php');
}
$shopId = (int)$shop['id'];

$min = (int)($_GET['min'] ?? 5);
if ($min < 1) $min = 1;
if ($min > 500) $min = 500;

$st = $pdo->prepare('SELECT id, name, sku, unit, current_stock FROM product WHERE shopid=? AND current_stock <= ? ORDER BY current_stock ASC, id DESC');
$st->execute([$shopId, $min]);
$low = $st->fetchAll();

$title = 'Low Stock Alert ()';
?>

<div class="card">
  <div class="h1">Low Stock Alert ()</div>
  <div class="muted">This page flags low stock products and suggests a reorder quantity (simple heuristic).</div>
</div>

<div class="card">
  <form class="form" method="get" action="<?= BASE_URL ?>/shop_owner/ai_low_stock.php" style="max-width:520px">
    <div>
      <label>Min level (alert when stock ≤ this)</label>
      <input type="number" name="min" min="1" max="500" value="<?= (int)$min ?>" />
    </div>
    <button class="btn" type="submit">Run</button>
  </form>
</div>

<div class="card">
  <h3>Results</h3>
  <?php if (!$low): ?>
    <div class="muted">No low stock items found for the chosen threshold.</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Product</th><th>SKU</th><th>Current</th><th>Suggested reorder</th></tr></thead>
      <tbody>
      <?php foreach ($low as $p):
        $cur = (int)($p['current_stock'] ?? 0);
        // Suggest reorder up to 3x threshold
        $suggest = max(0, ($min * 3) - $cur);
      ?>
        <tr>
          <td><?= h($p['name'] ?? '') ?> <div class="muted">Unit: <?= h($p['unit'] ?? '') ?></div></td>
          <td><?= h($p['sku'] ?? '') ?></td>
          <td><span class="badge danger"><?= (int)$cur ?></span></td>
          <td><span class="badge ok"><?= (int)$suggest ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card"><a class="btn secondary" href="dashboard.php">Back</a></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
