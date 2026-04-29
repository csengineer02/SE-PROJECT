<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);
$pdo = db();
$u = current_user();

$st = $pdo->prepare('SELECT * FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
$st->execute([$u['id']]);
$shop = $st->fetch();

$lowStock = [];
$kpi = null;
if ($shop) {
    $shopId = (int)$shop['id'];
    $st = $pdo->prepare('SELECT id,name,current_stock,min_stock_level FROM product WHERE shopid=? AND current_stock <= IFNULL(min_stock_level,5) ORDER BY current_stock ASC LIMIT 20');
    $st->execute([$shopId]);
    $lowStock = $st->fetchAll();

    // Stock-out prediction (simple average daily sales last 14 days)
    $today = new DateTime('today');
    foreach ($lowStock as &$p) {
        $st2 = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) AS sold FROM orderitem WHERE productid=? AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)');
        $st2->execute([(int)$p['id']]);
        $sold = (int)($st2->fetchColumn() ?: 0);
        $avg = $sold / 14.0;
        if ($avg > 0.01) {
            $days = (int)ceil(((int)$p['current_stock']) / $avg);
            $pred = clone $today;
            $pred->modify('+' . $days . ' days');
            $p['pred_days'] = $days;
            $p['pred_date'] = $pred->format('Y-m-d');
        } else {
            $p['pred_days'] = null;
            $p['pred_date'] = null;
        }
    }
    unset($p);

    // KPIs: Today's sale + last 30 days profit/loss
    $todayDate = date('Y-m-d');
    $from30 = (new DateTime('today'))->modify('-30 days')->format('Y-m-d');

    // Sales totals (grouped by order to avoid duplicates due to orderitem join)
    $st = $pdo->prepare('SELECT COALESCE(SUM(t.order_total),0) AS total, COALESCE(SUM(t.cost_total),0) AS cost
      FROM (
        SELECT o.order_id,
               MAX(p.total_amount) AS order_total,
               SUM(oi.quantity * pr.buying_price) AS cost_total
        FROM orders o
        JOIN payment p ON p.id = o.paymentid
        JOIN orderitem oi ON oi.Orderorder_id = o.order_id
        JOIN product pr ON pr.id = oi.productid
        WHERE pr.shopid = ? AND p.date = ?
        GROUP BY o.order_id
      ) t');
    $st->execute([$shopId, $todayDate]);
    $todayRow = $st->fetch() ?: ['total'=>0,'cost'=>0];

    $st = $pdo->prepare('SELECT COALESCE(SUM(t.order_total),0) AS total, COALESCE(SUM(t.cost_total),0) AS cost
      FROM (
        SELECT o.order_id,
               MAX(p.total_amount) AS order_total,
               SUM(oi.quantity * pr.buying_price) AS cost_total
        FROM orders o
        JOIN payment p ON p.id = o.paymentid
        JOIN orderitem oi ON oi.Orderorder_id = o.order_id
        JOIN product pr ON pr.id = oi.productid
        WHERE pr.shopid = ? AND p.date >= ?
        GROUP BY o.order_id
      ) t');
    $st->execute([$shopId, $from30]);
    $mRow = $st->fetch() ?: ['total'=>0,'cost'=>0];

    // Expenses last 30 days (by shopowner)
    $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expense WHERE shopownerid=? AND date >= ?');
    $st->execute([(int)$u['id'], $from30]);
    $mExpenses = (float)($st->fetchColumn() ?: 0);

    $todaySales = (float)($todayRow['total'] ?? 0);
    $todayCost = (float)($todayRow['cost'] ?? 0);
    $monthSales = (float)($mRow['total'] ?? 0);
    $monthCost = (float)($mRow['cost'] ?? 0);
    $monthProfit = $monthSales - $monthCost - $mExpenses;
    $todayProfit = $todaySales - $todayCost;
    $kpi = [
      'today_sales' => $todaySales,
      'today_profit' => $todayProfit,
      'month_sales' => $monthSales,
      'month_profit' => $monthProfit,
      'month_expenses' => $mExpenses,
      'from30' => $from30,
      'today' => $todayDate,
    ];
}


$title = 'Shop Owner Dashboard';
?>
<div class="card">
  <div class="h1">Shop Owner Dashboard</div>
  <?php if ($shop && $lowStock): ?>
    <div class="alert alert-warning mt-3">
      <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-1"></i> Low stock alert</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Product</th><th class="text-end">Stock</th><th class="text-end">Min</th><th>Predicted stock-out</th></tr></thead>
          <tbody>
            <?php foreach ($lowStock as $p): ?>
              <tr>
                <td><?= h($p['name']) ?></td>
                <td class="text-end fw-bold"><?= (int)$p['current_stock'] ?></td>
                <td class="text-end"><?= (int)$p['min_stock_level'] ?></td>
                <td>
                  <?php if ($p['pred_date']): ?>
                    <span class="badge bg-warning-subtle text-dark border">~<?= h($p['pred_date']) ?> (<?= (int)$p['pred_days'] ?> days)</span>
                  <?php else: ?>
                    <span class="text-muted">Not enough data</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-2">
        <a class="btn btn-sm btn-outline-success" href="inventory.php">Restock now</a>
        <a class="btn btn-sm btn-outline-success" href="ai_low_stock.php" style="margin-left:8px">view</a>
      </div>
    </div>
  <?php endif; ?>
  <?php if (!$shop): ?>
    <p class="muted">You have not created a shop yet.</p>
    <a class="btn" href="setup_shop.php">Create a Shop</a>
  <?php else: ?>
    <div class="muted">Shop ID: <b><?= (int)$shop['id'] ?></b> &middot; Status: 
      <?php if ($shop['status']==='verified'): ?><span class="badge ok">verified</span>
      <?php elseif ($shop['status']==='rejected'): ?><span class="badge danger">rejected</span>
      <?php else: ?><span class="badge warn">pending</span><?php endif; ?>
    </div>

    <?php if ($kpi): ?>
      <div class="row g-3 mt-3">
        <div class="col-lg-6">
          <div class="card app-card">
            <div class="card-body">
              <div class="d-flex align-items-start justify-content-between">
                <div>
                  <div class="text-muted small">Today's Sale (<?= h($kpi['today']) ?>)</div>
                  <div class="h4 mb-1">৳ <?= number_format($kpi['today_sales'], 0) ?></div>
                  <div class="text-muted small">Profit (today): <b>৳ <?= number_format($kpi['today_profit'], 0) ?></b></div>
                </div>
                <div class="mini-icon"><i class="bi bi-cash-coin"></i></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card app-card">
            <div class="card-body">
              <div class="d-flex align-items-start justify-content-between">
                <div>
                  <div class="text-muted small">Profit/Loss (last 30 days since <?= h($kpi['from30']) ?>)</div>
                  <div class="h4 mb-1">৳ <?= number_format($kpi['month_profit'], 0) ?></div>
                  <div class="text-muted small">Sales: ৳ <?= number_format($kpi['month_sales'], 0) ?> · Expenses: ৳ <?= number_format($kpi['month_expenses'], 0) ?></div>
                </div>
                <div class="mini-icon"><i class="bi bi-graph-up"></i></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div style="margin-top:12px" class="grid grid-2">
      <div class="card">
        <h3>Inventory</h3>
        <p class="muted">Add products, track stock, low-stock alerts.</p>
        <a class="btn" href="inventory.php">Manage Inventory</a>
      </div>
      <div class="card">
        <h3>Employees</h3>
        <p class="muted">Approve employees and manage roles.</p>
        <a class="btn" href="employees.php">Manage Employees</a>
      </div>
      <div class="card">
        <h3>Orders</h3>
        <p class="muted">View customer orders.</p>
        <a class="btn" href="orders.php">View Orders</a>
      </div>
      <div class="card">
        <h3>POS</h3>
        <p class="muted">Create walk-in orders and billing.</p>
        <a class="btn" href="pos.php">Open POS</a>
      </div>
    </div>
    <?php if ($shop['status'] !== 'verified'): ?>
      <div class="alert error">Your shop is not verified yet. Admin must verify it before it appears to customers.</div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
