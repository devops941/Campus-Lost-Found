<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) { redirect(BASE_URL . '/index.php'); }

$errors = [];
$input  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $input['name']   = trim($_POST['name']   ?? '');
    $input['email']  = strtolower(trim($_POST['email']  ?? ''));
    $input['phone']  = trim($_POST['phone']  ?? '');
    $password        = $_POST['password']         ?? '';
    $confirm         = $_POST['confirm_password'] ?? '';

    if ($input['name'] === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($input['name']) > 100) {
        $errors[] = 'Name must be under 100 characters.';
    }
    if ($input['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (!str_ends_with($input['email'], '@' . COLLEGE_EMAIL_DOMAIN)) {
        $errors[] = 'Only @' . COLLEGE_EMAIL_DOMAIN . ' addresses are accepted.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($input['phone'] !== '' && !preg_match('/^\+?[0-9]{7,15}$/', $input['phone'])) {
        $errors[] = 'Enter a valid phone number.';
    }

    if (empty($errors)) {
        $pdo  = get_db();
        $chk  = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $chk->execute([$input['email']]);
        if ($chk->fetch()) {
            $errors[] = 'That email address is already registered.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare(
            'INSERT INTO users (name, email, password, phone) VALUES (?,?,?,?)'
        )->execute([
            $input['name'], $input['email'], $hash,
            $input['phone'] !== '' ? $input['phone'] : null,
        ]);
        redirect(BASE_URL . '/login.php', 'success', 'Account created! You can now log in.');
    }
}

$pageTitle = 'Register';

// Background image URL:
$bgImageUrl = BASE_URL . '/assets/images/pexels-pixabay-159490.jpg';

require_once __DIR__ . '/includes/header.php';
?>

<style>
  body {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.72), rgba(30, 58, 138, 0.68)),
                url('<?= $bgImageUrl ?>') center center / cover no-repeat fixed !important;
  }
  .register-card-wrapper .card {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.28);
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
  }
  .register-logo-circle {
    width: 68px;
    height: 68px;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  }
</style>

<div class="row justify-content-center my-auto py-4 register-card-wrapper">
  <div class="col-md-6 col-lg-5">

    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center
                  rounded-circle register-logo-circle mb-3">
        <i class="bi bi-person-plus text-success fs-2"></i>
      </div>
      <h3 class="fw-bold mb-1 text-white">Create Account</h3>
      <p class="text-white text-opacity-80 small mb-0">
        Use your <strong><?= h(COLLEGE_EMAIL_DOMAIN) ?></strong> email address
      </p>
    </div>

    <div class="card card-shadow">
      <div class="card-body p-4">

        <?php if ($errors): ?>
        <div class="alert alert-danger border-0 rounded-3 py-2">
          <?php foreach ($errors as $e): ?>
            <div><i class="bi bi-exclamation-circle me-1"></i><?= h($e) ?></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>

          <div class="mb-3">
            <label for="name" class="form-label fw-semibold small">Full Name</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-person text-muted"></i>
              </span>
              <input type="text" class="form-control border-start-0 bg-light"
                     id="name" name="name"
                     value="<?= h($input['name'] ?? '') ?>"
                     placeholder="Your full name" maxlength="100" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label for="email" class="form-label fw-semibold small">College Email</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-envelope text-muted"></i>
              </span>
              <input type="email" class="form-control border-start-0 bg-light"
                     id="email" name="email"
                     value="<?= h($input['email'] ?? '') ?>"
                     placeholder="you@<?= h(COLLEGE_EMAIL_DOMAIN) ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="phone" class="form-label fw-semibold small">
              Phone <span class="text-muted fw-normal">(optional)</span>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-telephone text-muted"></i>
              </span>
              <input type="tel" class="form-control border-start-0 bg-light"
                     id="phone" name="phone"
                     value="<?= h($input['phone'] ?? '') ?>"
                     placeholder="10-digit mobile" maxlength="15">
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-6">
              <label for="password" class="form-label fw-semibold small">Password</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                  <i class="bi bi-lock text-muted"></i>
                </span>
                <input type="password" class="form-control border-start-0 bg-light"
                       id="password" name="password"
                       placeholder="Min 8 chars" required>
              </div>
            </div>
            <div class="col-6">
              <label for="confirm_password" class="form-label fw-semibold small">Confirm</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                  <i class="bi bi-lock-fill text-muted"></i>
                </span>
                <input type="password" class="form-control border-start-0 bg-light"
                       id="confirm_password" name="confirm_password"
                       placeholder="Repeat" required>
              </div>
            </div>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-success fw-semibold py-2">
              <i class="bi bi-person-check me-1"></i>Create Account
            </button>
          </div>
        </form>

        <hr class="my-3">
        <p class="text-center text-muted small mb-0">
          Already have an account?
          <a href="<?= BASE_URL ?>/login.php" class="fw-semibold text-decoration-none">
            Sign in
          </a>
        </p>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
