<?php
require_once __DIR__ . '/includes/public_header.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $u = current_user();
    if ($u['role'] === 'shop_owner') redirect('/shop_owner/dashboard.php');
    if ($u['role'] === 'employee') redirect('/employee/dashboard.php');
    if ($u['role'] === 'customer') redirect('/customer/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? ''); 
    $password = $_POST['password'] ?? '';
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $st->execute([$phone]);
        $user = $st->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash_set('error', 'Invalid phone or password.');
        } elseif ((int)$user['is_active'] !== 1) {
            flash_set('error', 'Your account is disabled.');
        } else {
            // role gating checks
            if ($user['role'] === 'shop_owner') {
                // must have a shop
                $st2 = $pdo->prepare('SELECT id, status FROM shop WHERE shopownerid=? ORDER BY id DESC LIMIT 1');
                $st2->execute([(int)$user['id']]);
                $shop = $st2->fetch();
                if (!$shop) {
                    // allow login but send to setup
                }
            }
            // Employee approval removed: employees can login immediately.

            login_user($user);
            flash_set('success', 'Logged in successfully.');
            if ($user['role'] === 'shop_owner') redirect('/shop_owner/dashboard.php');
            if ($user['role'] === 'employee') redirect('/employee/dashboard.php');
            if ($user['role'] === 'customer') redirect('/customer/dashboard.php');
            redirect('/index.php');
        }
    } catch (Throwable $e) {
        flash_set('error', 'Login failed: ' . $e->getMessage());
    }
}

$title = 'Login';
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-5">
    <div class="app-card">
      <div class="app-card-header">
        <h1 class="h4 fw-bold mb-1">Login</h1>
        <p class="text-muted mb-0">Access your dashboard securely.</p>
      </div>
      <div class="app-card-body">
        <form method="post" class="row g-3">
          <div class="col-12">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" required placeholder="01XXXXXXXXX" />
          </div>
<div class="col-12">
            <label class="form-label">Password</label>
            <input class="form-control" name="password" type="password" required />
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-success flex-grow-1" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Login</button>
            <a class="btn btn-outline-success" href="<?= BASE_URL ?>/index.php">Back</a>
          </div>
          <div class="col-12">
            <div class="text-muted small">Don't have an account? <a href="<?= BASE_URL ?>/signup.php">Create one</a></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
