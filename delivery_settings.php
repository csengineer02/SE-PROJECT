<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);

$pdo = db();
$u = current_user();

// Find shop
$st = $pdo->prepare('SELECT id, name FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([(int)$u['id']]);
$shop = $st->fetch();
if (!$shop) {
  flash_set('error', 'Create a shop first.');
  redirect('/shop_owner/setup_shop.php');
}
$shopId = (int)$shop['id'];

// Ensure settings table exists (this page only)
$pdo->exec("CREATE TABLE IF NOT EXISTS shop_delivery_settings (
  shopid INT(10) NOT NULL PRIMARY KEY,
  base_fee INT(11) NOT NULL DEFAULT 20,
  per_km_fee INT(11) NOT NULL DEFAULT 10,
  max_radius_km DECIMAL(6,2) NOT NULL DEFAULT 3.00,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $base = (int)($_POST['base_fee'] ?? 20);
  $perKm = (int)($_POST['per_km_fee'] ?? 10);
  $radius = (float)($_POST['max_radius_km'] ?? 3);

  if ($base < 0 || $perKm < 0 || $radius <= 0) {
    flash_set('error', 'Please enter valid delivery values.');
    redirect('/shop_owner/delivery_settings.php');
  }

  $st = $pdo->prepare('INSERT INTO shop_delivery_settings (shopid, base_fee, per_km_fee, max_radius_km) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE base_fee=VALUES(base_fee), per_km_fee=VALUES(per_km_fee), max_radius_km=VALUES(max_radius_km)');
  $st->execute([$shopId, $base, $perKm, $radius]);

  flash_set('success', 'Delivery settings saved.');
  redirect('/shop_owner/delivery_settings.php');
}

$st = $pdo->prepare('SELECT base_fee, per_km_fee, max_radius_km FROM shop_delivery_settings WHERE shopid=?');
$st->execute([$shopId]);
$cfg = $st->fetch() ?: ['base_fee'=>20,'per_km_fee'=>10,'max_radius_km'=>3];

$title = 'Delivery Settings';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1">Delivery Settings</h3>
    <div class="text-muted">Configure delivery charge rules for <b><?= h($shop['name']) ?></b>.</div>
  </div>
  <div>
    <a class="btn btn-soft" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-3">Pricing Rules</h6>

        <form method="post" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Base Fee (৳)</label>
            <input class="form-control" type="number" name="base_fee" min="0" value="<?= (int)$cfg['base_fee'] ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Per KM Fee (৳)</label>
            <input class="form-control" type="number" name="per_km_fee" min="0" value="<?= (int)$cfg['per_km_fee'] ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Radius (km)</label>
            <input class="form-control" type="number" name="max_radius_km" step="0.1" min="0.5" value="<?= h($cfg['max_radius_km']) ?>" />
          </div>

          <div class="col-12">
            <div class="p-3 rounded-4 bg-soft-green">
              <div class="fw-semibold mb-1">How delivery charge is calculated</div>
              <div class="text-muted small">Delivery Charge = Base Fee + (Per KM Fee × Distance in KM). Orders beyond Max Radius can be rejected or charged extra in future.</div>
            </div>
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save2 me-1"></i>Save</button>
            <a class="btn btn-outline-success" href="delivery_settings.php">Reset</a>
          </div>
        </form>

      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-2">Preview</h6>
        <div class="text-muted small mb-3">Example delivery charges with your settings:</div>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted">0.5 km</span>
          <span class="fw-semibold">৳ <?= (int)$cfg['base_fee'] + (int)round($cfg['per_km_fee']*0.5) ?></span>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted">1 km</span>
          <span class="fw-semibold">৳ <?= (int)$cfg['base_fee'] + (int)round($cfg['per_km_fee']*1) ?></span>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted">2 km</span>
          <span class="fw-semibold">৳ <?= (int)$cfg['base_fee'] + (int)round($cfg['per_km_fee']*2) ?></span>
        </div>
        <hr/>
        <div class="text-muted small">Max Radius: <b><?= h($cfg['max_radius_km']) ?> km</b></div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
