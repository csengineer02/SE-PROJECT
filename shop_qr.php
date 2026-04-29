<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);

$pdo = db();
$u = current_user();

$st = $pdo->prepare('SELECT id, name, qr_code, qr_code_path, status FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([(int)$u['id']]);
$shop = $st->fetch();
if (!$shop) {
  flash_set('error', 'Create a shop first.');
  redirect('/shop_owner/setup_shop.php');
}

$shopId = (int)$shop['id'];
$qrCode = (string)($shop['qr_code'] ?? '');
$qrFile = (string)($shop['qr_code_path'] ?? '');

$localImgUrl = $qrFile ? (BASE_URL . '/uploads/shop_qr/' . rawurlencode($qrFile)) : '';
$qrPayload = BASE_URL . '/customer/shop.php?shop=' . $shopId . '&code=' . urlencode($qrCode);
$fallbackUrl = 'https://chart.googleapis.com/chart?chs=320x320&cht=qr&chl=' . rawurlencode($qrPayload) . '&choe=UTF-8';

$title = 'Shop QR';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1">Shop QR</h3>
    <div class="text-muted">Use this QR for quick shop access & checkout.</div>
  </div>
  <div>
    <a class="btn btn-soft" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-2"><?= h($shop['name']) ?></h6>
        <div class="text-muted small mb-3">Status: <span class="badge text-bg-<?= ($shop['status']==='verified'?'success':'warning') ?>"><?= h($shop['status']) ?></span></div>

        <div class="d-flex justify-content-center">
          <img
            src="<?= h($localImgUrl ?: $fallbackUrl) ?>"
            alt="Shop QR"
            class="img-fluid rounded-4 border"
            style="max-width:320px"
          />
        </div>

        <div class="mt-3 text-muted small">
          If the QR image doesn't load, your server may not have internet access. In that case, re-open this page after internet is available to generate the PNG.
        </div>

        <?php if ($localImgUrl): ?>
          <div class="mt-3 d-flex gap-2">
            <a class="btn btn-outline-success" href="<?= h($localImgUrl) ?>" target="_blank"><i class="bi bi-download me-1"></i>Open / Download</a>
            <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-3">QR Destination</h6>
        <div class="p-3 rounded-4 bg-soft-green">
          <div class="text-muted small mb-1">URL encoded in the QR:</div>
          <div class="fw-semibold" style="word-break:break-all"><?= h($qrPayload) ?></div>
        </div>

        <hr class="my-4" />

        <h6 class="mb-2">How customers use it</h6>
        <ol class="text-muted small mb-0 ps-3">
          <li>Customer scans QR from phone.</li>
          <li>Customer lands on your shop page and can add products to cart.</li>
          <li>Checkout and track order delivery from their dashboard.</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
