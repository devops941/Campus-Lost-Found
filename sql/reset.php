<?php
/**
 * Database Reset — Campus Lost & Found Portal
 * Drops all tables, recreates schema, clears uploads.
 * Run from browser: http://localhost/lost-found/sql/reset.php
 * DELETE THIS FILE BEFORE DEPLOYING TO PRODUCTION.
 */

require_once dirname(__DIR__) . '/config/db.php';

$done    = false;
$errors  = [];
$steps   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'RESET') {

    try {
        $pdo = get_db();

        // ── 1. Drop all tables ──────────────────────────────────────
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['claims', 'matches', 'items', 'users'] as $tbl) {
            $pdo->exec("DROP TABLE IF EXISTS `$tbl`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $steps[] = 'Dropped all tables.';

        // ── 2. Recreate schema ──────────────────────────────────────
        $pdo->exec("
            CREATE TABLE users (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(100)  NOT NULL,
                email       VARCHAR(150)  NOT NULL UNIQUE,
                password    VARCHAR(255)  NOT NULL,
                phone       VARCHAR(20)   DEFAULT NULL,
                role        ENUM('user','admin') NOT NULL DEFAULT 'user',
                is_blocked  TINYINT(1)    NOT NULL DEFAULT 0,
                created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $steps[] = 'Created table: users.';

        $pdo->exec("
            CREATE TABLE items (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                user_id          INT           NOT NULL,
                type             ENUM('lost','found') NOT NULL,
                category         ENUM('id_card','electronics','books','bottle','bag','keys','other') NOT NULL,
                title            VARCHAR(200)  NOT NULL,
                description      TEXT          DEFAULT NULL,
                location         VARCHAR(200)  DEFAULT NULL,
                item_date        DATE          DEFAULT NULL,
                photo            VARCHAR(255)  DEFAULT NULL,
                status           ENUM('open','claimed','returned') NOT NULL DEFAULT 'open',
                verify_question  VARCHAR(255)  DEFAULT NULL,
                verify_answer    VARCHAR(255)  DEFAULT NULL,
                created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_type_status (type, status),
                INDEX idx_category    (category),
                INDEX idx_item_date   (item_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $steps[] = 'Created table: items.';

        $pdo->exec("
            CREATE TABLE matches (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                lost_item_id  INT           NOT NULL,
                found_item_id INT           NOT NULL,
                score         DECIMAL(4,2)  NOT NULL,
                created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lost_item_id)  REFERENCES items(id) ON DELETE CASCADE,
                FOREIGN KEY (found_item_id) REFERENCES items(id) ON DELETE CASCADE,
                UNIQUE KEY uq_match (lost_item_id, found_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $steps[] = 'Created table: matches.';

        $pdo->exec("
            CREATE TABLE claims (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                match_id     INT           NOT NULL,
                claimant_id  INT           NOT NULL,
                answer_given VARCHAR(255)  DEFAULT NULL,
                status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                reviewed_by  INT           DEFAULT NULL,
                reviewed_at  TIMESTAMP     NULL DEFAULT NULL,
                admin_note   TEXT          DEFAULT NULL,
                created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (match_id)    REFERENCES matches(id) ON DELETE CASCADE,
                FOREIGN KEY (claimant_id) REFERENCES users(id)   ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by) REFERENCES users(id)   ON DELETE SET NULL,
                UNIQUE KEY uq_pending_claim (claimant_id, match_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $steps[] = 'Created table: claims.';

        // ── 3. Delete uploaded photos ────────────────────────────────
        $uploadDir = ROOT_PATH . '/uploads/';
        $deleted   = 0;
        foreach (glob($uploadDir . 'img_*') as $file) {
            if (is_file($file)) {
                @unlink($file);
                $deleted++;
            }
        }
        $steps[] = "Deleted $deleted uploaded photo(s) from uploads/.";

        // ── 4. Clear logs ────────────────────────────────────────────
        $logDir = ROOT_PATH . '/logs/';
        foreach (['app_errors.log', 'mail_errors.log'] as $log) {
            $path = $logDir . $log;
            if (file_exists($path)) {
                file_put_contents($path, '');
            }
        }
        $steps[] = 'Cleared log files.';

        $done = true;

    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Database Reset — Lost &amp; Found</title>
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:560px;">

  <div class="text-center mb-4">
    <span class="display-5">🗑️</span>
    <h3 class="mt-2 fw-bold">Database Reset</h3>
    <p class="text-muted small">Campus Lost &amp; Found Portal — KZ-WEB-03</p>
  </div>

  <?php if ($done): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <div class="alert alert-success mb-3">
        <strong>Reset complete!</strong> Database is now empty and ready for fresh use.
      </div>
      <ul class="list-group list-group-flush mb-4">
        <?php foreach ($steps as $s): ?>
        <li class="list-group-item py-1 small">
          <span class="text-success me-1">&#10003;</span> <?= htmlspecialchars($s) ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="d-grid gap-2">
        <a href="<?= BASE_URL ?>/sql/seed.php" class="btn btn-primary">
          Seed demo data (optional)
        </a>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-success">
          Register first user (Admin)
        </a>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary">
          Go to portal home
        </a>
      </div>
    </div>
  </div>

  <?php elseif (!empty($errors)): ?>
  <div class="card border-danger border shadow-sm">
    <div class="card-body p-4">
      <div class="alert alert-danger mb-3">
        <strong>Error during reset:</strong><br>
        <?= htmlspecialchars($errors[0]) ?>
      </div>
      <?php if (!empty($steps)): ?>
      <p class="small text-muted">Completed before error:</p>
      <ul class="list-group list-group-flush mb-3">
        <?php foreach ($steps as $s): ?>
        <li class="list-group-item py-1 small">
          <span class="text-success me-1">&#10003;</span> <?= htmlspecialchars($s) ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/sql/reset.php" class="btn btn-outline-secondary btn-sm">Try again</a>
    </div>
  </div>

  <?php else: ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">

      <div class="alert alert-danger mb-3">
        <strong>Warning!</strong> This will permanently delete ALL users, items,
        matches, claims, and uploaded photos. This cannot be undone.
      </div>

      <h6 class="fw-semibold mb-2">What gets deleted:</h6>
      <ul class="small text-muted mb-4">
        <li>All user accounts (including admin)</li>
        <li>All lost &amp; found item posts</li>
        <li>All smart matches</li>
        <li>All claims</li>
        <li>All uploaded photos in <code>uploads/</code></li>
        <li>Log files cleared</li>
      </ul>

      <form method="post">
        <div class="mb-3">
          <label class="form-label fw-semibold">
            Type <code>RESET</code> to confirm:
          </label>
          <input type="text" name="confirm" class="form-control"
                 placeholder="RESET" autocomplete="off" required>
        </div>
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-danger fw-semibold">
            Clear Database Now
          </button>
          <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary">
            Cancel — Go Back
          </a>
        </div>
      </form>

    </div>
  </div>
  <?php endif; ?>

</div>
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
