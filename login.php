<?php
require_once __DIR__ . '/../includes/public_header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/rider_auth.php';

if (rider_logged_in()) {
    redirect('/delivery_rider/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($phone === '' || $password === '') {
        flash_set('error', 'Phone and password are required.');
        redirect('/delivery_rider/login.php');
    }

    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT rider_id,name,phone,active_status,password FROM deliveryrider WHERE phone=? LIMIT 1');
        $st->execute([$phone]);
        $r = $st->fetch();
        $stored = (string)($r['password'] ?? '');
        $ok = false;
        if ($r) {
            // bcrypt preferred, fallback to legacy plaintext
            if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$')) {
                $ok = password_verify($password, $stored);
            } else {
                $ok = hash_equals($stored, $password);
            }
        }
        if (!$r || !$ok) {
            flash_set('error', 'Invalid login.');
            redirect('/delivery_rider/login.php');
        }

        // If legacy plaintext matched, upgrade to bcrypt
        if ($ok && $stored !== '' && !(str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$'))) {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE deliveryrider SET password=? WHERE rider_id=?')->execute([$hash, (int)$r['rider_id']]);
            } catch (Throwable $e) {
                // ignore upgrade failures
            }
        }
        $_SESSION['rider'] = [
            'rider_id'=>(int)$r['rider_id'],
            'name'=>$r['name'],
            'phone'=>$r['phone'],
            'active_status'=>$r['active_status'],
        ];
        redirect('/delivery_rider/dashboard.php');
    } catch (Throwable $e) {
        flash_set('error', 'Login failed.');
        redirect('/delivery_rider/login.php');
    }
}

$title = "Rider Login";
?>

<div class="container py-4" style="max-width:480px">
  <div class="card">
    <div class="card-body">
      <h3 class="mb-1">Delivery Rider Login</h3>
      <p class="text-muted mb-3">Use your phone and password.</p>

      <?php if ($msg = flash_get('error')): ?>
        <div class="alert alert-danger"><?= h($msg) ?></div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label">Phone</label>
          <input class="form-control" name="phone" required placeholder="01XXXXXXXXX">
        </div>
        <div class="col-12">
          <label class="form-label">Password</label>
          <input class="form-control" name="password" type="password" required>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-success" type="submit">Login</button>
        </div>
      </form>

      <div class="mt-3 text-center">
        <a href="/delivery_rider/signup.php">Create a new rider account</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>
