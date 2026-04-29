<?php
require_once __DIR__ . '/rider_auth.php';
require_rider_login();
$pdo = db();
$rider = current_rider();
$riderId = (int)$rider['rider_id'];

function delivery_status_label(int $s): string {
    return match($s) {
        0 => 'Assigned',
        1 => 'Accepted',
        2 => 'Picked up',
        3 => 'In transit',
        4 => 'Delivered',
        5 => 'Failed',
        default => 'Unknown'
    };
}

$title = "Assigned Deliveries";

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deliveryId = (int)($_POST['delivery_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($deliveryId > 0) {
        // Ensure delivery belongs to this rider
        $st = $pdo->prepare("SELECT d.id, d.status, o.order_id, p.payment_method
                             FROM delivery d
                             JOIN orders o ON o.order_id = d.Orderorder_id
                             JOIN payment p ON p.id = o.paymentid
                             WHERE d.id=? AND d.deliveryriderrider_id=? LIMIT 1");
        $st->execute([$deliveryId, $riderId]);
        $d = $st->fetch();
        if ($d) {
            $cur = (int)($d['status'] ?? 0);

            if ($action === 'accept' && $cur === 0) {
                $pdo->prepare("UPDATE delivery SET status=1 WHERE id=?")->execute([$deliveryId]);
                flash_set('success','Task accepted.');
            } elseif ($action === 'pickup' && in_array($cur, [0,1], true)) {
                $pdo->prepare("UPDATE delivery SET status=2 WHERE id=?")->execute([$deliveryId]);
                $pdo->prepare("UPDATE orders SET status='out_for_delivery' WHERE order_id=?")->execute([(int)$d['order_id']]);
                flash_set('success','Marked as picked up.');
            } elseif ($action === 'transit' && in_array($cur, [2,3], true)) {
                $pdo->prepare("UPDATE delivery SET status=3 WHERE id=?")->execute([$deliveryId]);
                $pdo->prepare("UPDATE orders SET status='out_for_delivery' WHERE order_id=?")->execute([(int)$d['order_id']]);
                flash_set('success','Marked in transit.');
            } elseif ($action === 'delivered' && in_array($cur, [2,3], true)) {
                $pdo->prepare("UPDATE delivery SET status=4 WHERE id=?")->execute([$deliveryId]);
                $pdo->prepare("UPDATE orders SET status='delivered' WHERE order_id=?")->execute([(int)$d['order_id']]);
                flash_set('success','Marked delivered.');
            } elseif ($action === 'failed' && $cur !== 4) {
                $pdo->prepare("UPDATE delivery SET status=5 WHERE id=?")->execute([$deliveryId]);
                $pdo->prepare("UPDATE orders SET status='cancelled' WHERE order_id=?")->execute([(int)$d['order_id']]);
                flash_set('success','Marked failed.');
            } elseif ($action === 'cod' && $d['payment_method'] === 'cash_on_delivery') {
                // Track COD collection in payment.transaction_id note (lightweight, no schema change)
                $pdo->prepare("UPDATE payment p
                               JOIN orders o ON o.paymentid=p.id
                               JOIN delivery d ON d.Orderorder_id=o.order_id
                               SET p.payment_status='paid'
                               WHERE d.id=? AND d.deliveryriderrider_id=?")->execute([$deliveryId, $riderId]);
                flash_set('success','COD collection confirmed.');
            }
        }
    }
    redirect('/delivery_rider/deliveries.php');
}

$st = $pdo->prepare("SELECT d.id, d.status, d.pickup_address, d.delivery_address, d.expected_delivery_time,
                            o.order_id, o.status AS order_status,
                            c.name AS customer_name, c.phone AS customer_phone,
                            p.total_amount, p.delivery_charge, p.payment_method, p.payment_status
                     FROM delivery d
                     JOIN orders o ON o.order_id = d.Orderorder_id
                     JOIN customer c ON c.id = o.customerid
                     JOIN payment p ON p.id = o.paymentid
                     WHERE d.deliveryriderrider_id=?
                     ORDER BY d.id DESC");
$st->execute([$riderId]);
$deliveries = $st->fetchAll();

include __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1"><?= h($title) ?></h3>
    <div class="text-muted">Accept tasks, pickup orders, update status, confirm COD, upload proof.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-soft" href="/delivery_rider/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
  </div>
</div>

<div class="card app-card">
  <div class="card-body">
    <?php if (!$deliveries): ?>
      <div class="text-muted">No deliveries assigned yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Order</th>
              <th>Customer</th>
              <th>Addresses</th>
              <th>Payment</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($deliveries as $d): 
              $s = (int)$d['status'];
            ?>
              <tr>
                <td><?= (int)$d['id'] ?></td>
                <td>#<?= (int)$d['order_id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= h($d['customer_name'] ?: 'Customer') ?></div>
                  <div class="text-muted small"><?= h($d['customer_phone']) ?></div>
                </td>
                <td>
                  <div class="small"><span class="text-muted">Pickup:</span> <?= h($d['pickup_address']) ?></div>
                  <div class="small"><span class="text-muted">Drop:</span> <?= h($d['delivery_address']) ?></div>
                </td>
                <td>
                  <div class="small"><?= h($d['payment_method']) ?> / <?= h($d['payment_status']) ?></div>
                  <div class="small text-muted">Total: <?= h($d['total_amount']) ?> + Delivery <?= h($d['delivery_charge']) ?></div>
                </td>
                <td>
                  <span class="badge bg-soft"><?= h(delivery_status_label($s)) ?></span>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="delivery_id" value="<?= (int)$d['id'] ?>">
                      <input type="hidden" name="action" value="accept">
                      <button class="btn btn-sm btn-soft" <?= $s!==0?'disabled':''; ?>>Accept</button>
                    </form>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="delivery_id" value="<?= (int)$d['id'] ?>">
                      <input type="hidden" name="action" value="pickup">
                      <button class="btn btn-sm btn-soft" <?= !in_array($s,[0,1],true)?'disabled':''; ?>>Picked up</button>
                    </form>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="delivery_id" value="<?= (int)$d['id'] ?>">
                      <input type="hidden" name="action" value="transit">
                      <button class="btn btn-sm btn-soft" <?= !in_array($s,[2,3],true)?'disabled':''; ?>>In transit</button>
                    </form>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="delivery_id" value="<?= (int)$d['id'] ?>">
                      <input type="hidden" name="action" value="delivered">
                      <button class="btn btn-sm btn-primary" <?= !in_array($s,[2,3],true)?'disabled':''; ?>>Delivered</button>
                    </form>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="delivery_id" value="<?= (int)$d['id'] ?>">
                      <input type="hidden" name="action" value="failed">
                      <button class="btn btn-sm btn-danger" <?= $s===4?'disabled':''; ?>>Failed</button>
                    </form>
                    <?php if (($d['payment_method'] ?? '') === 'cash_on_delivery'): ?>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="delivery_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="action" value="cod">
                        <button class="btn btn-sm btn-soft" <?= ($d['payment_status'] ?? '')==='paid'?'disabled':''; ?>>Confirm COD</button>
                      </form>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-soft" href="/delivery_rider/proof.php?delivery_id=<?= (int)$d['id'] ?>">Proof</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
