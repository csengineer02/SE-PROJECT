<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['customer']);
$pdo = db();
$shopId = (int)($_GET['shop_id'] ?? 0);
if ($shopId<=0) redirect('/customer/dashboard.php');

$st = $pdo->prepare("SELECT * FROM shop WHERE id=? AND status='verified' LIMIT 1");
$st->execute([$shopId]);
$shop = $st->fetch();
if (!$shop) {
    flash_set('error', 'Shop not found or not verified.');
    redirect('/customer/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId>0) {
        $u = current_user();
        // Upsert cart item quantity
        $st = $pdo->prepare('SELECT id, quantity FROM cart_item WHERE customerid=? AND productid=? LIMIT 1');
        $st->execute([$u['id'], $productId]);
        $row = $st->fetch();
        if ($row) {
            $pdo->prepare('UPDATE cart_item SET quantity=quantity+1 WHERE id=?')->execute([(int)$row['id']]);
        } else {
            $pdo->prepare('INSERT INTO cart_item (customerid, productid, quantity) VALUES (?, ?, 1)')->execute([$u['id'], $productId]);
        }
        flash_set('success', 'Added to cart.');
        redirect('/customer/shop.php?shop_id=' . $shopId);
    }
}

// Active deals (if table exists)
$dealMap = [];
try {
    $st = $pdo->prepare("SELECT productid, discount_percent, start_date, end_date, is_active FROM shop_deals WHERE shopid=? AND is_active=1");
    $st->execute([$shopId]);
    $rows = $st->fetchAll();
    $today = date('Y-m-d');
    foreach ($rows as $r) {
        $sd = $r['start_date'] ?? null;
        $ed = $r['end_date'] ?? null;
        $ok = true;
        if ($sd && $today < $sd) $ok = false;
        if ($ed && $today > $ed) $ok = false;
        if ($ok) {
            $dealMap[(int)$r['productid']] = (int)$r['discount_percent'];
        }
    }
} catch (Throwable $e) {
    // Ignore if table doesn't exist yet.
}

$products = $pdo->prepare('SELECT p.id,p.name,p.sku,p.buying_price AS price,p.unit,p.current_stock,
                                  b.Name AS brand,
                                  c.Name AS category,
                                  d.discount_percent
                           FROM product p
                           JOIN brand b ON b.Brand_id=p.brandBrand_id
                           JOIN category c ON c.Category_id=p.CategoryCategory_id
                           LEFT JOIN shop_deals d
                             ON d.shopid=p.shopid AND d.productid=p.id AND d.is_active=1
                             AND (d.start_date IS NULL OR d.start_date <= CURDATE())
                             AND (d.end_date IS NULL OR d.end_date >= CURDATE())
                           WHERE p.shopid=?
                           ORDER BY p.id DESC');
$products->execute([$shopId]);
$products = $products->fetchAll();

$title = 'Shop - ' . ($shop['name'] ?? '');
?>
<div class="card">
  <div class="h1"><?= h($shop['name']) ?></div>
  <div class="muted"><?= h($shop['address']) ?></div>
  <div style="margin-top:10px">
    <a class="btn secondary" href="dashboard.php">Back</a>
    <a class="btn" href="cart.php" style="margin-left:10px">Cart</a>
  </div>
</div>

<div class="card">
  <h3>Products</h3>
  <?php if (!$products): ?>
    <div class="muted">No products in this shop yet.</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <?= h($p['name']) ?>
            <div class="muted"><?= h($p['brand']) ?> &middot; <?= h($p['category']) ?> &middot; SKU: <?= h($p['sku']) ?></div>
          </td>
          <td>
            <?php
              $base = (float)($p['price'] ?? 0);
              $disc = $dealMap[(int)$p['id']] ?? 0;
              if ($disc > 0) {
                $new = $base * (1 - ($disc / 100));
                echo '<span class="badge ok" style="margin-right:6px">-' . (int)$disc . '%</span>';
                echo '<s class="muted">' . number_format($base, 2) . '</s> <b>' . number_format($new, 2) . '</b> / ' . h($p['unit']);
              } else {
                echo number_format($base, 2) . ' / ' . h($p['unit']);
              }
            ?>
          </td>
          <td><?= (int)$p['current_stock'] ?></td>
          <td>
            <?php if ((int)$p['current_stock'] <= 0): ?>
              <span class="badge danger">Out of stock</span>
            <?php else: ?>
              <form method="post" style="margin:0">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>" />
                <button class="btn" type="submit">Add to Cart</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
