<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$title = "Payment Methods";
include __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h3 class="mb-1"><?= h($title) ?></h3>
    <div class="text-muted">Configure COD/Online payments and QR collections.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-soft" href="#"><i class="bi bi-life-preserver me-1"></i>Help</a>
    <a class="btn btn-primary" href="#"><i class="bi bi-plus-lg me-1"></i>New</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="badge badge-soft mb-2"><i class="bi bi-sparkles me-1"></i>Grocer 360</div>
            <h5 class="mb-1">This module UI is ready</h5>
            <div class="text-muted">Backend wiring can be connected to your existing database tables.</div>
          </div>
          <div class="app-illus"><i class="bi bi-leaf"></i></div>
        </div>

        <hr class="my-4" />

        <div class="row g-3">
          <div class="col-md-6">
            <div class="mini-tile">
              <div class="mini-tile__icon"><i class="bi bi-sliders2"></i></div>
              <div>
                <div class="fw-semibold">Modern form + table layout</div>
                <div class="text-muted small">Cards, inputs, and action bars follow the demo theme.</div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mini-tile">
              <div class="mini-tile__icon"><i class="bi bi-bell"></i></div>
              <div>
                <div class="fw-semibold">Notifications-ready design</div>
                <div class="text-muted small">Toast/alerts + status chips included.</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="card app-card mt-3">
      <div class="card-body">
        <h6 class="mb-3">Quick actions</h6>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-success" href="#"><i class="bi bi-download me-1"></i>Export</a>
          <a class="btn btn-outline-success" href="#"><i class="bi bi-upload me-1"></i>Import</a>
          <a class="btn btn-outline-success" href="#"><i class="bi bi-printer me-1"></i>Print</a>
          <a class="btn btn-outline-success" href="#"><i class="bi bi-gear me-1"></i>Configure</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-body">
        <h6 class="mb-2">Status</h6>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted">Module</span>
          <span class="chip chip-success">Enabled</span>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="text-muted">Access</span>
          <span class="chip chip-info">Role-based</span>
        </div>
        <div class="p-3 rounded-4 bg-soft-green">
          <div class="fw-semibold">Tip</div>
          <div class="text-muted small">Connect this page to DB tables when you implement the feature logic.</div>
        </div>
      </div>
    </div>

    <div class="card app-card mt-3">
      <div class="card-body">
        <h6 class="mb-3">Recent activity</h6>
        <div class="activity">
          <div class="activity__dot"></div>
          <div>
            <div class="fw-semibold">UI theme applied</div>
            <div class="text-muted small">Green demo layout across dashboards.</div>
          </div>
        </div>
        <div class="activity">
          <div class="activity__dot"></div>
          <div>
            <div class="fw-semibold">Pages added</div>
            <div class="text-muted small">All modules have matching screens.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
