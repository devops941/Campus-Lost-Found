<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) { redirect(BASE_URL . '/index.php'); }

$errors = [];

if (isset($_GET['msg']) && $_GET['msg'] === 'loggedout') {
    flash('info', 'You have been logged out successfully.');
}
if (isset($_GET['err']) && $_GET['err'] === 'blocked') {
    flash('danger', 'Your account has been blocked. Please contact the administrator.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '')    { $errors[] = 'Email is required.'; }
    if ($password === '') { $errors[] = 'Password is required.'; }

    if (empty($errors)) {
        $pdo  = get_db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } elseif ((int)$user['is_blocked'] === 1) {
            $errors[] = 'Your account has been blocked. Contact the administrator.';
        } else {
            login_user($user);
            redirect(BASE_URL . '/index.php', 'success', 'Welcome back, ' . $user['name'] . '!');
        }
    }
}

$pageTitle = 'Login';

// Configure background image URL here (can be a local path or external URL):
$bgImageUrl = BASE_URL . '/assets/images/campus-bg.jpg';

require_once __DIR__ . '/includes/header.php';
?>

<style>
  body {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.72), rgba(30, 58, 138, 0.68)),
                url('<?= $bgImageUrl ?>') center center / cover no-repeat fixed !important;
  }
  .login-card-wrapper .card {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.28);
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
  }
  .login-logo-circle {
    width: 68px;
    height: 68px;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  }
</style>

<div class="row justify-content-center my-auto py-4 login-card-wrapper">
  <div class="col-md-5 col-lg-4">

    <!-- Logo mark -->
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center
                  rounded-circle login-logo-circle mb-3">
        <img src="../assets/img/Campuslogo.png" alt="Logo" style="max-height:42px;max-width:42px;object-fit:contain;" onerror="this.remove()">
        <i class="bi bi-search-heart text-primary fs-2"></i>
      </div>
      <h3 class="fw-bold mb-1 text-white">Welcome back</h3>
      <p class="text-white text-opacity-80 small mb-0">Sign in to your campus account</p>
    </div>

    <div class="card card-shadow">
      <div class="card-body p-4">

        <?php if ($errors): ?>
        <div class="alert alert-danger border-0 rounded-3 py-2">
          <i class="bi bi-exclamation-circle me-1"></i>
          <?= h($errors[0]) ?>
        </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label for="email" class="form-label fw-semibold small">College Email</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-envelope text-muted"></i>
              </span>
              <input type="email" class="form-control border-start-0 bg-light"
                     id="email" name="email"
                     value="<?= h($_POST['email'] ?? '') ?>"
                     placeholder="you@college.edu" required autofocus>
            </div>
          </div>
          <div class="mb-4">
            <label for="password" class="form-label fw-semibold small">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-lock text-muted"></i>
              </span>
              <input type="password" class="form-control border-start-0 bg-light"
                     id="password" name="password"
                     placeholder="Your password" required>
            </div>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary fw-semibold py-2">
              <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
            </button>
          </div>
        </form>

        <hr class="my-3">
        <p class="text-center text-muted small mb-0">
          Don't have an account?
          <a href="<?= BASE_URL ?>/register.php" class="fw-semibold text-decoration-none">
            Register here
          </a>
        </p>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
