<?php
require_once __DIR__ . '/rider_auth.php';
require_rider_login();
$pdo = db();
$r = current_rider();
$riderId = (int)$r['rider_id'];

$st = $pdo->prepare("SELECT 
    SUM(CASE WHEN status=0 THEN 1 ELSE 0 END) AS assigned,
    SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS accepted,
    SUM(CASE WHEN status=4 THEN 1 ELSE 0 END) AS delivered
  FROM delivery WHERE deliveryriderrider_id=?");
$st->execute([$riderId]);
$stats = $st->fetch() ?: ['assigned'=>0,'accepted'=>0,'delivered'=>0];

$title="Rider Dashboard";
include __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1"><?= h($title) ?></h3>
    <div class="text-muted">Operational access: tasks and delivery updates.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="/delivery_rider/deliveries.php"><i class="bi bi-truck me-1"></i>My Deliveries</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card app-card"><div class="card-body">
      <div class="text-muted small">Assigned</div>
      <div class="display-6"><?= (int)$stats['assigned'] ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card app-card"><div class="card-body">
      <div class="text-muted small">Accepted</div>
      <div class="display-6"><?= (int)$stats['accepted'] ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card app-card"><div class="card-body">
      <div class="text-muted small">Delivered</div>
      <div class="display-6"><?= (int)$stats['delivered'] ?></div>
    </div></div>
  </div>
</div>

<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
