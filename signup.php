<?php
require_once __DIR__ . '/includes/public_header.php';
require_once __DIR__ . '/includes/db.php';

$role = $_GET['role'] ?? '';
$allowed = ['shop_owner','employee','customer'];
if (!in_array($role, $allowed, true)) {
    flash_set('error', 'Please choose a role to sign up.');
    redirect('/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $nid = trim($_POST['nid'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $shopid = (int)($_POST['shopid'] ?? 0);

    if ($phone === '' || $password === '' || $nid === '') {
        flash_set('error', 'Phone, NID and password are required.');
    } else {
        try {
            $pdo = db();
            // Check unique phone in users
            $st = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
            $st->execute([$phone]);
            if ($st->fetch()) {
                flash_set('error', 'This phone is already registered.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $st = $pdo->prepare('INSERT INTO users (name, nid, phone, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
                $st->execute([$name ?: $phone, $nid, $phone, $hash, $role]);
                $userId = (int)$pdo->lastInsertId();

                if ($role === 'customer') {
                    // Keep customer table in sync for FK relations
                    $st = $pdo->prepare('INSERT INTO customer (id, name, phone, password, address, nid) VALUES (?, ?, ?, ?, ?, ?)');
                    $st->execute([$userId, $name ?: null, $phone, $hash, $address ?: 'Dhaka,Bangladesh', $nid]);
                }

                if ($role === 'shop_owner') {
                    // Keep shopowner table in sync
                    $st = $pdo->prepare('INSERT INTO shopowner (id, name, phone, password, nid, Adminid) VALUES (?, ?, ?, ?, ?, 1)');
                    $st->execute([$userId, $name ?: null, $phone, $hash, $nid]);
                }

                if ($role === 'employee') {
                    if ($shopid <= 0) {
                        flash_set('error', 'Please provide a valid Shop ID to request joining.');
                        // rollback user
                        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
                        redirect('/signup.php?role=employee');
                    }
                    // Employee is active immediately (no owner approval)
                    $st = $pdo->prepare('INSERT INTO employee (id, name, phone, password, nid, active, shopid, roleid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $st->execute([$userId, $name ?: null, $phone, $hash, $nid, 'active', $shopid, 1]);
                }

                flash_set('success', 'Account created. Please login.');
                redirect('/login.php');
            }
        } catch (Throwable $e) {
            flash_set('error', 'Signup failed: ' . $e->getMessage());
        }
    }
}

$title = 'Sign up';
?>

<div class="row justify-content-center">
  <div class="col-md-9 col-lg-6">
    <div class="app-card">
      <div class="app-card-header">
        <div class="app-chip mb-2"><i class="bi bi-person-plus"></i> Create account</div>
        <h1 class="h4 fw-bold mb-1">Sign up as <?= h(str_replace('_',' ', $role)) ?></h1>
        <p class="text-muted mb-0">Complete the form to continue.</p>
      </div>
      <div class="app-card-body">
        <form method="post" class="row g-3">
          <div class="col-12">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" placeholder="Your name" />
          </div>
          <div class="col-12">
            <label class="form-label">Phone <span class="text-danger">*</span></label>
            <input class="form-control" name="phone" required placeholder="01XXXXXXXXX" />
          </div>
          <div class="col-12">
            <label class="form-label">NID <span class="text-danger">*</span></label>
            <input class="form-control" name="nid" required placeholder="Your NID" />
          </div>

          <?php if ($role === 'customer'): ?>
          <div class="col-12">
            <label class="form-label">Address</label>
            <input class="form-control" name="address" placeholder="Dhaka, Bangladesh" />
          </div>
          <?php endif; ?>

          <?php if ($role === 'employee'): ?>
          <div class="col-12">
            <label class="form-label">Shop ID you want to join <span class="text-danger">*</span></label>
            <input class="form-control" name="shopid" required placeholder="e.g., 1" />
            <div class="form-text">Ask the shop owner for the Shop ID from their dashboard.</div>
          </div>
          <?php endif; ?>

          <div class="col-12">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input class="form-control" name="password" type="password" required />
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-success flex-grow-1" type="submit"><i class="bi bi-check2-circle me-1"></i> Create account</button>
            <a class="btn btn-outline-success" href="<?= BASE_URL ?>/index.php">Back</a>
          </div>
          <div class="col-12">
            <div class="text-muted small">Already have an account? <a href="<?= BASE_URL ?>/login.php">Login</a></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
