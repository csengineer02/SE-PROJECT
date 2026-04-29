<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);

$pdo = db();
$u = current_user();

$st = $pdo->prepare('SELECT id, name, address, outlet_name, tin_number, banner_photo, status FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([(int)$u['id']]);
$shop = $st->fetch();

if (!$shop) {
  flash_set('error', 'Create a shop first.');
  redirect('/shop_owner/setup_shop.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mode = $_POST['mode'] ?? '';

  if ($mode === 'shop') {
    $name = trim($_POST['shop_name'] ?? '');
    $address = trim($_POST['shop_address'] ?? '');
    $outlet = trim($_POST['outlet_name'] ?? '');
    if ($name === '' || $address === '') {
      flash_set('error', 'Shop name and address are required.');
      redirect('/shop_owner/settings.php');
    }
    $st = $pdo->prepare('UPDATE shop SET name=?, address=?, outlet_name=? WHERE id=? AND shopownerid=?');
    $st->execute([$name, $address, ($outlet === '' ? null : $outlet), (int)$shop['id'], (int)$u['id']]);
    flash_set('success', 'Shop settings updated.');
    redirect('/shop_owner/settings.php');
  }

  if ($mode === 'password') {
    $p1 = (string)($_POST['new_password'] ?? '');
    $p2 = (string)($_POST['confirm_password'] ?? '');
    if (strlen($p1) < 4) {
      flash_set('error', 'Password must be at least 4 characters.');
      redirect('/shop_owner/settings.php');
    }
    if ($p1 !== $p2) {
      flash_set('error', 'Passwords do not match.');
      redirect('/shop_owner/settings.php');
    }
    $hash = password_hash($p1, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=? AND role="shop_owner"')->execute([$hash, (int)$u['id']]);
    flash_set('success', 'Password updated.');
    redirect('/shop_owner/settings.php');
  }
}

// Reload
$st = $pdo->prepare('SELECT id, name, address, outlet_name, tin_number, banner_photo, status FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([(int)$u['id']]);
$shop = $st->fetch();

$title = 'Settings';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1">Settings</h3>
    <div class="text-muted">Manage shop details and account settings.</div>
  </div>
  <div>
    <a class="btn btn-soft" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-3">Shop Settings</h6>
        <form method="post" class="row g-3">
          <input type="hidden" name="mode" value="shop" />
          <div class="col-12">
            <label class="form-label">Shop Name</label>
            <input class="form-control" name="shop_name" value="<?= h($shop['name']) ?>" required />
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <input class="form-control" name="shop_address" value="<?= h($shop['address']) ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Outlet Name</label>
            <input class="form-control" name="outlet_name" value="<?= h($shop['outlet_name'] ?? '') ?>" placeholder="e.g., Vatara Branch" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <input class="form-control" value="<?= h($shop['status'] ?? '') ?>" disabled />
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save2 me-1"></i>Save</button>
            <a class="btn btn-outline-success" href="settings.php">Reset</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-3">Change Password</h6>
        <form method="post" class="row g-3">
          <input type="hidden" name="mode" value="password" />
          <div class="col-12">
            <label class="form-label">New Password</label>
            <input class="form-control" type="password" name="new_password" minlength="4" required />
          </div>
          <div class="col-12">
            <label class="form-label">Confirm Password</label>
            <input class="form-control" type="password" name="confirm_password" minlength="4" required />
          </div>
          <div class="col-12">
            <button class="btn btn-outline-success" type="submit"><i class="bi bi-shield-lock me-1"></i>Update Password</button>
          </div>
        </form>

        <hr />
        <div class="text-muted small">
          <div><b>TIN:</b> <?= h($shop['tin_number'] ?? '') ?></div>
          <div class="mt-1"><b>Banner:</b> <?= h($shop['banner_photo'] ?? '—') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
