<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);
$pdo = db();
$u = current_user();

$st = $pdo->prepare('SELECT id FROM shop WHERE shopownerid=? LIMIT 1');
$st->execute([$u['id']]);
$has = $st->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'Local Shop';
    $address = trim($_POST['address'] ?? '');
    $outlet = trim($_POST['outlet_name'] ?? '');
    $tin = trim($_POST['tin_number'] ?? '');

    // Banner upload
    $bannerName = null;
    if (isset($_FILES['banner_photo']) && is_array($_FILES['banner_photo'])) {
        $f = $_FILES['banner_photo'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed, true)) {
                flash_set('error', 'Shop banner must be an image (jpg, png, webp).');
                redirect('/shop_owner/setup_shop.php');
            }
            $dir = __DIR__ . '/../uploads/shop_banners';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $bannerName = 'shop_' . (int)$u['id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . '/' . $bannerName;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                flash_set('error', 'Failed to upload banner photo.');
                redirect('/shop_owner/setup_shop.php');
            }
        }
    }

    if ($name === '' || $address === '' || $tin === '' || !$bannerName) {
        flash_set('error', 'Shop name, address, TIN Number and banner photo are required.');
    } else {
        // Create shop + generate unique shop QR code (saved as PNG)
        $qr = bin2hex(random_bytes(8));
        $st = $pdo->prepare('INSERT INTO shop (name, type, address, banner_path, status, qr_code_path, banner_photo, tin_number, qr_code, shopownerid, outlet_name) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$name, $type, $address, null, 'pending', null, $bannerName, $tin, $qr, $u['id'], $outlet ?: null]);

        $shopId = (int)$pdo->lastInsertId();
        // QR payload points to the shop page for customers
        $qrPayload = (defined('BASE_URL') ? BASE_URL : '') . '/customer/shop.php?shop=' . $shopId . '&code=' . urlencode($qr);
        $qrDir = __DIR__ . '/../uploads/shop_qr';
        if (!is_dir($qrDir)) {
            @mkdir($qrDir, 0775, true);
        }
        $qrFile = 'shop_' . $shopId . '.png';
        $qrFullPath = $qrDir . '/' . $qrFile;

        // Use Google Chart QR endpoint to generate PNG (works with local XAMPP as long as internet is available)
        $qrUrl = 'https://chart.googleapis.com/chart?chs=320x320&cht=qr&chl=' . rawurlencode($qrPayload) . '&choe=UTF-8';
        $img = @file_get_contents($qrUrl);
        if ($img !== false && strlen($img) > 100) {
            @file_put_contents($qrFullPath, $img);
            $pdo->prepare('UPDATE shop SET qr_code_path=? WHERE id=?')->execute([$qrFile, $shopId]);
        }
        flash_set('success', 'Shop created and sent for verification.');
        redirect('/shop_owner/dashboard.php');
    }
}

$title = 'Create Shop';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1">Create your Shop</h3>
    <div class="text-muted">After submission, admin will verify your shop. Only verified shops appear to customers.</div>
  </div>
  <div>
    <a class="btn btn-soft" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Shop Name <span class="text-danger">*</span></label>
            <input class="form-control" name="name" required placeholder="e.g., Rahim Grocery" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Type <span class="text-danger">*</span></label>
            <select class="form-select" name="type">
              <option value="Local Shop">Local Shop</option>
              <option value="Super Shop">Super Shop</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Address <span class="text-danger">*</span></label>
            <input class="form-control" name="address" required placeholder="Dhaka, Bangladesh" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Outlet Name (optional)</label>
            <input class="form-control" name="outlet_name" placeholder="e.g., Mirpur Branch" />
          </div>
          <div class="col-md-6">
            <label class="form-label">TIN Number <span class="text-danger">*</span></label>
            <input class="form-control" name="tin_number" required placeholder="e.g., 123456789" />
          </div>
          <div class="col-12">
            <label class="form-label">Shop Banner Photo <span class="text-danger">*</span></label>
            <input class="form-control" type="file" name="banner_photo" required accept="image/*" />
            <div class="form-text">Recommended: JPG/PNG, landscape image.</div>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Submit for Verification</button>
            <a class="btn btn-outline-success" href="dashboard.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-2">What happens next?</h6>
        <ol class="text-muted small mb-0 ps-3">
          <li>Your shop is created with <b>Pending</b> status.</li>
          <li>Admin verifies your shop.</li>
          <li>Customers will be able to discover your shop by area.</li>
          <li>A QR code is generated automatically for quick access.</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
