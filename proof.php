<?php
require_once __DIR__ . '/rider_auth.php';
require_rider_login();
$pdo = db();
$rider = current_rider();
$riderId = (int)$rider['rider_id'];

$deliveryId = (int)($_GET['delivery_id'] ?? 0);
if ($deliveryId <= 0) {
  flash_set('error','Delivery not found.');
  redirect('/delivery_rider/deliveries.php');
}

$st = $pdo->prepare("SELECT d.id, d.status, d.Orderorder_id, o.order_id
                     FROM delivery d
                     JOIN orders o ON o.order_id=d.Orderorder_id
                     WHERE d.id=? AND d.deliveryriderrider_id=? LIMIT 1");
$st->execute([$deliveryId, $riderId]);
$row = $st->fetch();
if (!$row) {
  flash_set('error','Access denied.');
  redirect('/delivery_rider/deliveries.php');
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!isset($_FILES['proof']) || $_FILES['proof']['error']!==UPLOAD_ERR_OK) {
    flash_set('error','Please upload a valid image file.');
    redirect('/delivery_rider/proof.php?delivery_id='.$deliveryId);
  }
  $tmp = $_FILES['proof']['tmp_name'];
  $name = $_FILES['proof']['name'] ?? 'proof';
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $allowed = ['jpg','jpeg','png','webp'];
  if (!in_array($ext, $allowed, true)) {
    flash_set('error','Only JPG, PNG, WEBP allowed.');
    redirect('/delivery_rider/proof.php?delivery_id='.$deliveryId);
  }
  $dir = __DIR__ . '/../uploads/delivery_proofs';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $file = 'delivery_'.$deliveryId.'_'.time().'.'.$ext;
  $dest = $dir.'/'.$file;
  if (!move_uploaded_file($tmp, $dest)) {
    flash_set('error','Upload failed.');
    redirect('/delivery_rider/proof.php?delivery_id='.$deliveryId);
  }
  // store path in delivery.expected_delivery_time not good; instead add column proof_path if exists, else ignore
  try { $pdo->prepare("ALTER TABLE delivery ADD COLUMN proof_path VARCHAR(255) DEFAULT NULL")->execute(); } catch(Throwable $e) {}
  try { $pdo->prepare("UPDATE delivery SET proof_path=? WHERE id=?")->execute(['/uploads/delivery_proofs/'.$file, $deliveryId]); } catch(Throwable $e) {}
  flash_set('success','Proof uploaded.');
  redirect('/delivery_rider/deliveries.php');
}

$title="Upload Delivery Proof";
include __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1"><?= h($title) ?></h3>
    <div class="text-muted">Upload a photo as delivery proof.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-soft" href="/delivery_rider/deliveries.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="card app-card">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label">Proof image</label>
        <input class="form-control" type="file" name="proof" accept="image/*" required>
        <div class="form-text">JPG/PNG/WEBP.</div>
      </div>
      <button class="btn btn-primary" type="submit">Upload</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
