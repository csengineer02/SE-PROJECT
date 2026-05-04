<?php
require_once __DIR__ . '/../includes/public_header.php';
require_once __DIR__ . '/includes/admin_auth.php';

if (admin_current()) {
    redirect('/admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT * FROM admin WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $admin = $st->fetch();
        if (!$admin || !password_verify_flexible($password, $admin['password'])) {
            flash_set('error', 'Invalid admin email or password.');
        } else {
            admin_login($admin);
            flash_set('success', 'Admin logged in.');
            redirect('/admin/dashboard.php');
        }
    } catch (Throwable $e) {
        flash_set('error', 'Admin login failed: ' . $e->getMessage());
    }
}

$title = 'Admin Login';
?>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-5">
    <div class="app-card">
      <div class="app-card-header">
        <div class="app-chip mb-2"><i class="bi bi-shield-lock"></i> Admin</div>
        <h1 class="h4 fw-bold mb-1">Admin Login</h1>
        <p class="text-muted mb-0">Default from your SQL: <span class="fw-semibold">admin@grocer.com</span> / <span class="fw-semibold">123</span> (please change it).</p>
      </div>
      <div class="app-card-body">
        <form method="post" class="row g-3">
          <div class="col-12">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" type="email" required placeholder="admin@grocer.com" />
          </div>
          <div class="col-12">
            <label class="form-label">Password</label>
            <input class="form-control" name="password" type="password" required />
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-success flex-grow-1" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Login</button>
            <a class="btn btn-outline-success" href="<?= BASE_URL ?>/index.php">Back</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>
