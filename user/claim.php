<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mail.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_login();

$pdo     = get_db();
$uid     = current_user_id();
$matchId = isset($_GET['match']) ? (int)$_GET['match'] : 0;

if ($matchId <= 0) {
    redirect(BASE_URL . '/user/matches.php', 'danger', 'Invalid match.');
}

// Load the match with both items
$stmt = $pdo->prepare(
    "SELECT m.*,
            li.id AS li_id, li.title AS li_title, li.category AS li_category,
            li.description AS li_description, li.location AS li_location,
            li.item_date AS li_date, li.photo AS li_photo,
            li.status AS li_status, li.user_id AS li_user_id,
            fi.id AS fi_id, fi.title AS fi_title, fi.category AS fi_category,
            fi.description AS fi_description, fi.location AS fi_location,
            fi.item_date AS fi_date, fi.photo AS fi_photo,
            fi.status AS fi_status, fi.user_id AS fi_user_id,
            fi.verify_question AS verify_question
     FROM matches m
     JOIN items li ON m.lost_item_id  = li.id
     JOIN items fi ON m.found_item_id = fi.id
     WHERE m.id = ? LIMIT 1"
);
$stmt->execute([$matchId]);
$match = $stmt->fetch();

if (!$match) {
    redirect(BASE_URL . '/user/matches.php', 'danger', 'Match not found.');
}

// Only the lost-item owner can raise a claim
if ((int)$match['li_user_id'] !== $uid) {
    http_response_code(403);
    exit('Only the person who lost the item may raise a claim on this match.');
}

// Guard: both items must still be open
if ($match['li_status'] !== 'open' || $match['fi_status'] !== 'open') {
    redirect(BASE_URL . '/user/matches.php', 'warning', 'This item is no longer available for claiming.');
}

// Guard: user must not already have a pending/approved claim on this match
$existStmt = $pdo->prepare(
    "SELECT id, status FROM claims WHERE match_id = ? AND claimant_id = ? LIMIT 1"
);
$existStmt->execute([$matchId, $uid]);
$existingClaim = $existStmt->fetch();

if ($existingClaim) {
    redirect(
        BASE_URL . '/user/matches.php',
        'info',
        'You already have a ' . $existingClaim['status'] . ' claim on this match.'
    );
}

// Guard: prevent a user from having two pending claims on the same item
$dupStmt = $pdo->prepare(
    "SELECT c.id FROM claims c
     JOIN matches m ON c.match_id = m.id
     WHERE c.claimant_id = ?
       AND c.status = 'pending'
       AND (m.lost_item_id = ? OR m.found_item_id = ?)
     LIMIT 1"
);
$dupStmt->execute([$uid, $match['li_id'], $match['fi_id']]);
if ($dupStmt->fetch()) {
    redirect(BASE_URL . '/user/matches.php', 'warning', 'You already have a pending claim on this item.');
}

$errors = [];
$input  = [];

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $input['answer'] = trim($_POST['answer'] ?? '');

    if ($input['answer'] === '') {
        $errors[] = 'Please provide an answer to the verification question.';
    }

    if (empty($errors)) {
        // Insert claim — mark item as claimed
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                "INSERT INTO claims (match_id, claimant_id, answer_given, status)
                 VALUES (?, ?, ?, 'pending')"
            );
            $ins->execute([$matchId, $uid, $input['answer']]);

            // Set lost item to "claimed" while pending
            $pdo->prepare("UPDATE items SET status = 'claimed' WHERE id = ?")
                ->execute([$match['li_id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            log_error('Claim insert failed: ' . $e->getMessage());
            $errors[] = 'Could not submit claim. Please try again.';
        }

        if (empty($errors)) {
            redirect(
                BASE_URL . '/user/matches.php',
                'success',
                'Claim submitted! An admin will review it and notify you by email.'
            );
        }
    }
}

$pageTitle = 'Raise a Claim';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">

    <!-- Match summary -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-success text-white">
        <strong><i class="bi bi-diagram-2 me-1"></i>Match #<?= $matchId ?></strong>
        &mdash;
        <?= (int)round((float)$match['score'] * 100) ?>% confidence
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-6">
            <div class="p-2 rounded bg-danger bg-opacity-10">
              <span class="badge bg-danger mb-1">Your Lost Item</span>
              <div class="fw-semibold small"><?= h($match['li_title']) ?></div>
              <div class="text-muted" style="font-size:.75rem;">
                <?= h($match['li_location'] ?? '') ?> &middot; <?= format_date($match['li_date']) ?>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 rounded bg-primary bg-opacity-10">
              <span class="badge bg-primary mb-1">Found Item</span>
              <div class="fw-semibold small"><?= h($match['fi_title']) ?></div>
              <div class="text-muted" style="font-size:.75rem;">
                <?= h($match['fi_location'] ?? '') ?> &middot; <?= format_date($match['fi_date']) ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Claim form -->
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h5 class="card-title mb-1">
          <i class="bi bi-shield-check me-1 text-warning"></i> Verify Ownership
        </h5>
        <p class="text-muted small mb-4">
          The person who found this item set a question to confirm ownership.
          Answer it correctly and an admin will approve your claim.
        </p>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>

          <?php if ($match['verify_question']): ?>
          <div class="mb-4">
            <label class="form-label fw-semibold">Verification Question</label>
            <div class="alert alert-secondary py-2 mb-2">
              <i class="bi bi-question-circle me-1"></i>
              <?= h($match['verify_question']) ?>
            </div>
            <input type="text" class="form-control" name="answer"
                   value="<?= h($input['answer'] ?? '') ?>"
                   placeholder="Your answer" required autofocus>
            <div class="form-text">
              Answer is case-insensitive. Checked privately by the admin.
            </div>
          </div>
          <?php else: ?>
          <div class="alert alert-info">
            No verification question was set. Submit to raise a standard claim.
          </div>
          <input type="hidden" name="answer" value="">
          <?php endif; ?>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-send me-1"></i> Submit Claim
            </button>
            <a href="<?= BASE_URL ?>/user/matches.php" class="btn btn-outline-secondary">
              Cancel
            </a>
          </div>
        </form>

      </div>
    </div><!-- /card -->

  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
