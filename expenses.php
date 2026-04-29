<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['shop_owner']);

$pdo = db();
$u = current_user();

// Map current user -> shopowner row (existing schema)
$st = $pdo->prepare('SELECT id FROM shopowner WHERE id=? OR phone=? LIMIT 1');
$st->execute([(int)($u['id'] ?? 0), (string)($u['phone'] ?? '')]);
$shopownerId = (int)($st->fetchColumn() ?: 0);
if ($shopownerId <= 0) {
  // If legacy shopowner table isn't in use yet, still allow UI with user id
  $shopownerId = (int)($u['id'] ?? 0);
}

// Ensure common categories exist
$pdo->exec("CREATE TABLE IF NOT EXISTS expense_category (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS expense (
  id INT(10) NOT NULL AUTO_INCREMENT,
  amount INT(10) DEFAULT NULL,
  date DATE NOT NULL,
  shopownerid INT(10) NOT NULL,
  expense_category_id INT(11) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$st = $pdo->query('SELECT COUNT(*) FROM expense_category');
$catCount = (int)$st->fetchColumn();
if ($catCount === 0) {
  $defaults = ['Employee salary', 'Bills', 'External expenses', 'Other'];
  $ins = $pdo->prepare('INSERT INTO expense_category (name) VALUES (?)');
  foreach ($defaults as $d) {
    $ins->execute([$d]);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $amount = (int)($_POST['amount'] ?? 0);
  $date = trim($_POST['date'] ?? date('Y-m-d'));
  $catId = (int)($_POST['expense_category_id'] ?? 0);

  if ($amount <= 0 || $catId <= 0 || $date === '') {
    flash_set('error', 'Please enter amount, date and category.');
    redirect('/shop_owner/expenses.php');
  }
  $pdo->prepare('INSERT INTO expense (amount, date, shopownerid, expense_category_id) VALUES (?,?,?,?)')
      ->execute([$amount, $date, $shopownerId, $catId]);
  flash_set('success', 'Expense added.');
  redirect('/shop_owner/expenses.php');
}

$cats = $pdo->query('SELECT id, name FROM expense_category ORDER BY name')->fetchAll();
$st = $pdo->prepare('SELECT e.id, e.amount, e.date, c.name AS category
                      FROM expense e
                      JOIN expense_category c ON c.id = e.expense_category_id
                      WHERE e.shopownerid=?
                      ORDER BY e.date DESC, e.id DESC
                      LIMIT 100');
$st->execute([$shopownerId]);
$expenses = $st->fetchAll();

$title = 'Expenses';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1">Expenses</h3>
    <div class="text-muted">Track employee salary, bills, external expenses and more.</div>
  </div>
  <div>
    <a class="btn btn-soft" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-3">Add Expense</h6>
        <form method="post" class="row g-3">
          <div class="col-12">
            <label class="form-label">Category</label>
            <select class="form-select" name="expense_category_id" required>
              <option value="">Select category</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Amount (৳)</label>
            <input class="form-control" type="number" name="amount" min="1" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Date</label>
            <input class="form-control" type="date" name="date" value="<?= h(date('Y-m-d')) ?>" required />
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Add</button>
            <button class="btn btn-outline-success" type="reset">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <h6 class="mb-0">Recent Expenses</h6>
          <span class="text-muted small">Showing last <?= (int)count($expenses) ?> entries</span>
        </div>
        <?php if (!$expenses): ?>
          <div class="text-muted">No expenses yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Category</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($expenses as $e): ?>
                  <tr>
                    <td><?= h($e['date']) ?></td>
                    <td><?= h($e['category']) ?></td>
                    <td class="text-end fw-semibold">৳ <?= (int)$e['amount'] ?></td>
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
