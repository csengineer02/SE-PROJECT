<?php
require_once __DIR__ . '/../includes/header.php';
require_login(['customer']);
$pdo = db();
$u = current_user();

// Load profile from customer table (source of address)
$st = $pdo->prepare('SELECT id,name,phone,address FROM customer WHERE id=? LIMIT 1');
$st->execute([$u['id']]);
$profile = $st->fetch();
if (!$profile) {
    flash_set('error','Profile not found.');
    redirect('/customer/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $newPass = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($name === '' || $phone === '' || $address === '') {
        flash_set('error','Name, phone and address are required.');
        redirect('/customer/profile.php');
    }
    if ($newPass !== '' && $newPass !== $confirm) {
        flash_set('error','New password and confirm password do not match.');
        redirect('/customer/profile.php');
    }

    $pdo->beginTransaction();
    try {
        // Update customer
        if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE customer SET name=?, phone=?, address=?, password=? WHERE id=?')
                ->execute([$name, $phone, $address, $hash, $u['id']]);
            // Also update unified users table if present
            $pdo->prepare('UPDATE users SET name=?, phone=?, password_hash=? WHERE id=? AND role="customer"')
                ->execute([$name, $phone, $hash, $u['id']]);
        } else {
            $pdo->prepare('UPDATE customer SET name=?, phone=?, address=? WHERE id=?')
                ->execute([$name, $phone, $address, $u['id']]);
            $pdo->prepare('UPDATE users SET name=?, phone=? WHERE id=? AND role="customer"')
                ->execute([$name, $phone, $u['id']]);
        }

        $pdo->commit();
        // Refresh session view
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = $phone;

        flash_set('success','Profile updated.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash_set('error','Failed to update profile.');
    }
    redirect('/customer/profile.php');
}

$pts = null;
try {
    $st = $pdo->prepare('SELECT points FROM reward_points WHERE user_id=?');
    $st->execute([$u['id']]);
    $pts = $st->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

$title = 'My Profile';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1">My Profile</h3>
    <div class="text-muted">Reward Points: <b><?= (int)($pts ?? 0) ?></b></div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-success" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-3"><i class="bi bi-person-circle me-1"></i>Profile Information</h6>
        <form method="post" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input class="form-control" name="name" value="<?= h($profile['name'] ?? '') ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone <span class="text-danger">*</span></label>
            <input class="form-control" name="phone" value="<?= h($profile['phone'] ?? '') ?>" required />
          </div>
          <div class="col-12">
            <label class="form-label">Address <span class="text-danger">*</span></label>
            <textarea class="form-control" name="address" rows="3" required><?= h($profile['address'] ?? '') ?></textarea>
          </div>

          <div class="col-12"><hr class="my-1" /></div>

          <div class="col-md-6">
            <label class="form-label">New Password</label>
            <input class="form-control" name="new_password" type="password" placeholder="Leave blank to keep current" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirm Password</label>
            <input class="form-control" name="confirm_password" type="password" placeholder="Re-type new password" />
          </div>

          <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Save Changes</button>
            <a class="btn btn-outline-success" href="orders.php"><i class="bi bi-receipt me-1"></i>Orders & Payments</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-2">Quick Tips</h6>
        <div class="text-muted small">
          • Your phone is used for login.<br/>
          • Address will be used automatically in checkout (you can still edit during checkout).<br/>
          • For security, passwords are stored using bcrypt hashes.
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
