<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);

$pdo = db();
$u = current_user();

$st = $pdo->prepare('SELECT id, status FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([(int)$u['id']]);
$shop = $st->fetch();
if (!$shop) {
    flash_set('error', 'Create a shop first.');
    redirect('/shop_owner/setup_shop.php');
}
$shopId = (int)$shop['id'];

// Create / update / delete deals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '';
    if ($mode === 'create') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $percent = (int)($_POST['discount_percent'] ?? 0);
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');

        if ($productId <= 0 || $percent <= 0 || $percent > 90) {
            flash_set('error', 'Please select a product and a discount between 1% and 90%.');
            redirect('/shop_owner/discounts.php');
        }

        // Ensure product belongs to the shop
        $st = $pdo->prepare('SELECT id FROM product WHERE id=? AND shopid=? LIMIT 1');
        $st->execute([$productId, $shopId]);
        if (!$st->fetch()) {
            flash_set('error', 'Invalid product.');
            redirect('/shop_owner/discounts.php');
        }

        $startDate = $start !== '' ? $start : null;
        $endDate = $end !== '' ? $end : null;
        if ($startDate && $endDate && $startDate > $endDate) {
            flash_set('error', 'Start date cannot be after end date.');
            redirect('/shop_owner/discounts.php');
        }

        // Upsert: one active deal per product
        $st = $pdo->prepare('SELECT id FROM shop_deals WHERE shopid=? AND productid=? LIMIT 1');
        $st->execute([$shopId, $productId]);
        $existing = $st->fetch();
        if ($existing) {
            $pdo->prepare('UPDATE shop_deals SET discount_percent=?, start_date=?, end_date=?, is_active=1 WHERE id=?')
                ->execute([$percent, $startDate, $endDate, (int)$existing['id']]);
            flash_set('success', 'Deal updated.');
        } else {
            $pdo->prepare('INSERT INTO shop_deals (shopid, productid, discount_percent, start_date, end_date, is_active) VALUES (?,?,?,?,?,1)')
                ->execute([$shopId, $productId, $percent, $startDate, $endDate]);
            flash_set('success', 'Deal created.');
        }
        redirect('/shop_owner/discounts.php');
    }

    if ($mode === 'toggle') {
        $dealId = (int)($_POST['deal_id'] ?? 0);
        $to = (int)($_POST['to'] ?? 0);
        if ($dealId > 0) {
            $pdo->prepare('UPDATE shop_deals SET is_active=? WHERE id=? AND shopid=?')
                ->execute([$to ? 1 : 0, $dealId, $shopId]);
            flash_set('success', 'Deal updated.');
        }
        redirect('/shop_owner/discounts.php');
    }
}

$products = $pdo->prepare('SELECT id, name, sku, buying_price, unit, current_stock FROM product WHERE shopid=? ORDER BY id DESC');
$products->execute([$shopId]);
$products = $products->fetchAll();

$deals = $pdo->prepare('SELECT d.*, p.name AS product_name, p.sku, p.buying_price, p.unit
                        FROM shop_deals d
                        JOIN product p ON p.id=d.productid
                        WHERE d.shopid=?
                        ORDER BY d.id DESC');
$deals->execute([$shopId]);
$deals = $deals->fetchAll();

$title = 'Discounts & Offers';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1">Discounts & Offers</h3>
    <div class="text-muted">Create product deals. Customers will see discounted prices inside your shop.</div>
  </div>
  <div>
    <a class="btn btn-soft" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-3">Create / Update Deal</h6>
        <?php if (!$products): ?>
          <div class="text-muted">Add products in Inventory first.</div>
        <?php else: ?>
          <form method="post" class="row g-3">
            <input type="hidden" name="mode" value="create" />
            <div class="col-12">
              <label class="form-label">Product <span class="text-danger">*</span></label>
              <select class="form-select" name="product_id" required>
                <option value="">Select product</option>
                <?php foreach ($products as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> (SKU: <?= h($p['sku']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Discount % <span class="text-danger">*</span></label>
              <input class="form-control" name="discount_percent" type="number" min="1" max="90" required placeholder="e.g., 10" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <input class="form-control" value="Active" disabled />
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Date (optional)</label>
              <input class="form-control" name="start_date" type="date" />
            </div>
            <div class="col-md-6">
              <label class="form-label">End Date (optional)</label>
              <input class="form-control" name="end_date" type="date" />
            </div>
            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary" type="submit"><i class="bi bi-save2 me-1"></i>Save Deal</button>
              <button class="btn btn-outline-success" type="reset">Reset</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <h6 class="mb-0">Active Deals</h6>
          <span class="text-muted small">Total: <?= (int)count($deals) ?></span>
        </div>
        <?php if (!$deals): ?>
          <div class="text-muted">No deals yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Discount</th>
                  <th>Dates</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($deals as $d): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h($d['product_name'] ?? '') ?></div>
                    <div class="text-muted small">SKU: <?= h($d['sku'] ?? '') ?> · Price: <?= number_format((float)($d['buying_price'] ?? 0), 2) ?> / <?= h($d['unit'] ?? '') ?></div>
                  </td>
                  <td>
                    <span class="badge text-bg-success"><?= (int)$d['discount_percent'] ?>%</span>
                    <?php if ((int)$d['is_active'] !== 1): ?>
                      <span class="badge text-bg-warning ms-1">Paused</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="text-muted small"><?= h($d['start_date'] ?? '—') ?> → <?= h($d['end_date'] ?? '—') ?></div>
                  </td>
                  <td class="text-end">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="mode" value="toggle" />
                      <input type="hidden" name="deal_id" value="<?= (int)$d['id'] ?>" />
                      <?php if ((int)$d['is_active'] === 1): ?>
                        <button class="btn btn-outline-danger btn-sm" name="to" value="0" type="submit">Pause</button>
                      <?php else: ?>
                        <button class="btn btn-outline-success btn-sm" name="to" value="1" type="submit">Resume</button>
                      <?php endif; ?>
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
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
