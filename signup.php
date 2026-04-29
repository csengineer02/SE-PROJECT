<?php
require_once __DIR__ . '/../includes/public_header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/rider_auth.php';

if (rider_logged_in()) {
    redirect('/delivery_rider/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $active = 'Active';

    if ($name === '' || $phone === '' || $password === '') {
        flash_set('error', 'Name, phone and password are required.');
        redirect('/delivery_rider/signup.php');
    }

    try {
        $pdo = db();
        // Prevent duplicate phone
        $st = $pdo->prepare('SELECT rider_id FROM deliveryrider WHERE phone=? LIMIT 1');
        $st->execute([$phone]);
        if ($st->fetchColumn()) {
            flash_set('error', 'Phone already registered.');
            redirect('/delivery_rider/signup.php');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $pdo->prepare('INSERT INTO deliveryrider (name, phone, active_status, password) VALUES (?,?,?,?)');
        $st->execute([$name, $phone, $active, $hash]);

        // IMPORTANT: Signup should create the account only.
        // Rider must login to access the rider dashboard (consistent with other roles).
        flash_set('success', 'Signup successful. Please login to continue.');
        redirect('/delivery_rider/login.php');
    } catch (Throwable $e) {
        flash_set('error', 'Signup failed. Please try again.');
        redirect('/delivery_rider/signup.php');
    }
}

$title = "Delivery Rider Signup";
?>

<div class="container py-4" style="max-width:520px">
  <div class="card">
    <div class="card-body">
      <h3 class="mb-1">Delivery Rider Signup</h3>
      <p class="text-muted mb-3">Create your rider account.</p>

      <?php if ($msg = flash_get('error')): ?>
        <div class="alert alert-danger"><?= h($msg) ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($msg) ?></div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label">Full Name</label>
          <input class="form-control" name="name" required>
        </div>
        <div class="col-12">
          <label class="form-label">Phone</label>
          <input class="form-control" name="phone" required placeholder="01XXXXXXXXX">
        </div>
        <div class="col-12">
          <label class="form-label">Password</label>
          <input class="form-control" name="password" type="password" required>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-success" type="submit">Create account</button>
        </div>
      </form>
      <div class="mt-3 text-center">
        <a href="/delivery_rider/login.php">Already have an account? Login</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>
