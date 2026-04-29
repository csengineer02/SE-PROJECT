<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);
$pdo = db();
$u = current_user();

$st = $pdo->prepare('SELECT id FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([$u['id']]);
$shopId = (int)($st->fetchColumn() ?: 0);
if ($shopId<=0) {
    flash_set('error','Create a shop first.');
    redirect('/shop_owner/setup_shop.php');
}

// Pick an active employee to record POS sales
$st = $pdo->prepare("SELECT id FROM employee WHERE shopid=? AND active='active' ORDER BY id ASC LIMIT 1");
$st->execute([$shopId]);
$empId = (int)($st->fetchColumn() ?: 0);

$products = $pdo->prepare('SELECT id,name,buying_price AS price,unit,current_stock FROM product WHERE shopid=? ORDER BY name');
$products->execute([$shopId]);
$products = $products->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if ($empId<=0) {
        flash_set('error','You need at least one approved employee to use POS (schema requires employeeid).');
        redirect('/shop_owner/employees.php');
    }
    $productId=(int)($_POST['product_id']??0);
    $qty=(int)($_POST['qty']??1);
    $method=$_POST['payment_method']??'cash';
    if ($productId<=0||$qty<=0) {
        flash_set('error','Select a product and quantity.');
        redirect('/shop_owner/pos.php');
    }
    $st = $pdo->prepare('SELECT buying_price AS price, current_stock FROM product WHERE id=? AND shopid=?');
    $st->execute([$productId,$shopId]);
    $p=$st->fetch();
    if(!$p){flash_set('error','Product not found.');redirect('/shop_owner/pos.php');}
    if((int)$p['current_stock']<$qty){flash_set('error','Not enough stock.');redirect('/shop_owner/pos.php');}
    $total=(float)$p['price']*$qty;

    $pdo->beginTransaction();
    try{
        $custId=1;
        $st=$pdo->prepare('SELECT id FROM discount WHERE shopid=? AND percentage=0 ORDER BY id DESC LIMIT 1');
        $st->execute([$shopId]);
        $disc=$st->fetch();
        if(!$disc){
            $pdo->prepare('INSERT INTO discount (percentage,start_date,end_date,shopid) VALUES (0,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 10 YEAR),?)')->execute([$shopId]);
            $discountId=(int)$pdo->lastInsertId();
        } else $discountId=(int)$disc['id'];
        $txn='pos_' . bin2hex(random_bytes(6));
        $pdo->prepare('INSERT INTO payment (total_amount,payment_method,payment_status,date,transaction_id,customerid,discountid) VALUES (?,?,?,?,?,?,?)')
            ->execute([(int)round($total),$method,'paid',date('Y-m-d'),$txn,$custId,$discountId]);
        $paymentId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO orders (status, customerid, employeeid, paymentid) VALUES (?,?,?,?)')
            ->execute(['completed',$custId,$empId,$paymentId]);
        $orderId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO orderitem (date,time,quantity,productid,Orderorder_id) VALUES (?,?,?,?,?)')
            ->execute([date('Y-m-d'),date('H:i:s'),$qty,$productId,$orderId]);
        $pdo->prepare('UPDATE product SET current_stock=current_stock-? WHERE id=? AND shopid=?')
            ->execute([$qty,$productId,$shopId]);
        $pdo->commit();
        flash_set('success','POS order completed. Order ID: ' . $orderId);
        redirect('/shop_owner/pos.php');
    } catch(Throwable $e){
        $pdo->rollBack();
        flash_set('error','POS failed: ' . $e->getMessage());
        redirect('/shop_owner/pos.php');
    }
}

$title='POS';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1">POS / Billing</h3>
    <div class="text-muted">Create a quick bill and record payment.</div>
  </div>
  <div>
    <a class="btn btn-soft" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<?php if ($empId<=0): ?>
  <div class="alert alert-danger">
    To use POS, approve at least one employee first.
    <a href="employees.php" class="alert-link">Go to Employees</a>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-3">Create Bill</h6>
        <form method="post" class="row g-3">
          <div class="col-12">
            <label class="form-label">Product</label>
            <select class="form-select" name="product_id" required>
              <option value="">Select a product</option>
              <?php foreach($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> (stock <?= (int)$p['current_stock'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Quantity</label>
            <input class="form-control" type="number" name="qty" value="1" min="1" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Payment Method</label>
            <select class="form-select" name="payment_method">
              <option value="cash">Cash</option>
              <option value="bkash">bKash (mock)</option>
              <option value="card">Card (mock)</option>
            </select>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit" <?= $empId<=0 ? 'disabled' : '' ?>><i class="bi bi-check2-circle me-1"></i>Complete Sale</button>
            <button class="btn btn-outline-success" type="reset">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-2">Tips</h6>
        <ul class="text-muted small mb-0 ps-3">
          <li>Ensure stock is available before billing.</li>
          <li>POS sales are stored as completed orders.</li>
          <li>Use Inventory page to adjust stock.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
