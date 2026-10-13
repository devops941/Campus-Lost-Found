<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mail.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_admin();

$pdo = get_db();

// ── POST actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action  = $_POST['action']   ?? '';
    $claimId = (int)($_POST['claim_id'] ?? 0);
    $note    = trim($_POST['admin_note'] ?? '');

    // Load the claim with its match and items
    $stmt = $pdo->prepare(
        "SELECT c.*,
                m.lost_item_id, m.found_item_id, m.score,
                li.title AS li_title, li.user_id AS li_user_id,
                fi.title AS fi_title, fi.user_id AS fi_user_id,
                fi.verify_answer
         FROM claims c
         JOIN matches m ON c.match_id     = m.id
         JOIN items li  ON m.lost_item_id  = li.id
         JOIN items fi  ON m.found_item_id = fi.id
         WHERE c.id = ? LIMIT 1"
    );
    $stmt->execute([$claimId]);
    $claim = $stmt->fetch();

    if ($claim) {
        $now = date('Y-m-d H:i:s');

        if ($action === 'approve') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE claims SET status='approved', reviewed_by=?, reviewed_at=?, admin_note=?
                     WHERE id=?"
                )->execute([current_user_id(), $now, $note, $claimId]);

                // Mark lost item as returned
                $pdo->prepare("UPDATE items SET status='returned' WHERE id=?")
                    ->execute([$claim['lost_item_id']]);
                $pdo->prepare("UPDATE items SET status='returned' WHERE id=?")
                    ->execute([$claim['found_item_id']]);

                $pdo->commit();

                // Reveal contact details to both parties
                _send_approval_emails($pdo, $claim, $note);
                redirect(BASE_URL . '/admin/claims.php', 'success', 'Claim approved and both parties notified.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                log_error('Claim approve failed: ' . $e->getMessage());
                redirect(BASE_URL . '/admin/claims.php', 'danger', 'Error approving claim.');
            }

        } elseif ($action === 'reject') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE claims SET status='rejected', reviewed_by=?, reviewed_at=?, admin_note=?
                     WHERE id=?"
                )->execute([current_user_id(), $now, $note, $claimId]);

                // Return lost item to open
                $pdo->prepare("UPDATE items SET status='open' WHERE id=?")
                    ->execute([$claim['lost_item_id']]);

                $pdo->commit();
                _send_rejection_email($pdo, $claim, $note);
                redirect(BASE_URL . '/admin/claims.php', 'success', 'Claim rejected and user notified.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                log_error('Claim reject failed: ' . $e->getMessage());
                redirect(BASE_URL . '/admin/claims.php', 'danger', 'Error rejecting claim.');
            }

        } elseif ($action === 'mark_returned') {
            // Admin manually marks as returned (post-approval follow-up)
            $pdo->prepare(
                "UPDATE items SET status='returned' WHERE id IN (?,?)"
            )->execute([$claim['lost_item_id'], $claim['found_item_id']]);
            redirect(BASE_URL . '/admin/claims.php', 'success', 'Items marked as returned.');
        }
    }
}

// ── Filter ───────────────────────────────────────────────────────
$filterStatus = in_array($_GET['status'] ?? '', ['', 'pending', 'approved', 'rejected'], true)
    ? ($_GET['status'] ?? '') : '';

$focusId = (int)($_GET['id'] ?? 0); // Jump-to claim from dashboard

$whereSQL = $filterStatus ? "WHERE c.status = ?" : '';
$params   = $filterStatus ? [$filterStatus] : [];

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM claims c $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$pg = paginate($total, 15);

$sql = "
    SELECT c.*,
           u.name  AS claimant_name, u.email AS claimant_email, u.phone AS claimant_phone,
           m.score AS match_score,
           li.id AS li_id, li.title AS li_title, li.user_id AS li_user_id,
           lu.name  AS li_owner_name, lu.email AS li_owner_email, lu.phone AS li_owner_phone,
           fi.id AS fi_id, fi.title AS fi_title,
           fi.verify_answer, c.answer_given,
           rv.name AS reviewer_name
    FROM claims c
    JOIN users   u  ON c.claimant_id  = u.id
    JOIN matches m  ON c.match_id     = m.id
    JOIN items   li ON m.lost_item_id  = li.id
    JOIN users   lu ON li.user_id     = lu.id
    JOIN items   fi ON m.found_item_id = fi.id
    LEFT JOIN users rv ON c.reviewed_by = rv.id
    $whereSQL
    ORDER BY
        (c.status = 'pending') DESC,
        c.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$pg['limit'], $pg['offset']]));
$claims = $stmt->fetchAll();

$pageTitle = 'Manage Claims';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="bi bi-hand-index-thumb me-1"></i> Manage Claims</h4>
  <div class="d-flex gap-2">
    <?php foreach ([''=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l): ?>
    <a href="?status=<?= $v ?>"
       class="btn btn-sm <?= $filterStatus === $v ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <?= $l ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($claims)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-inbox fs-1"></i>
  <p class="mt-2">No claims to show.</p>
</div>
<?php else: ?>

<?php foreach ($claims as $c):
    // Answer comparison (case-insensitive trim)
    $answerMatch = $c['verify_answer'] !== null
        ? (strtolower(trim($c['answer_given'] ?? '')) === strtolower(trim($c['verify_answer'])))
        : null;
?>
<div class="card shadow-sm mb-3 <?= $focusId === (int)$c['id'] ? 'border-warning' : '' ?>"
     id="claim-<?= (int)$c['id'] ?>">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>
      Claim #<?= (int)$c['id'] ?> &nbsp;
      <?php
      $cBadge = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$c['status']] ?? 'secondary';
      ?>
      <span class="badge bg-<?= $cBadge ?>"><?= h(ucfirst($c['status'])) ?></span>
      &nbsp;
      <span class="badge bg-light text-dark border">
        Match score: <?= (int)round((float)$c['match_score'] * 100) ?>%
      </span>
    </span>
    <small class="text-muted"><?= format_datetime($c['created_at']) ?></small>
  </div>

  <div class="card-body">
    <div class="row g-3 mb-3">
      <!-- Lost item + owner -->
      <div class="col-md-4">
        <div class="small fw-semibold text-danger mb-1">Lost Item</div>
        <div><?= h($c['li_title']) ?></div>
        <div class="text-muted small mt-1">Owner: <strong><?= h($c['li_owner_name']) ?></strong></div>
        <div class="text-muted small"><?= h($c['li_owner_email']) ?></div>
        <?php if ($c['li_owner_phone']): ?>
        <div class="text-muted small"><?= h($c['li_owner_phone']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Found item -->
      <div class="col-md-4">
        <div class="small fw-semibold text-primary mb-1">Found Item</div>
        <div><?= h($c['fi_title']) ?></div>
        <div class="text-muted small mt-1">Claimant: <strong><?= h($c['claimant_name']) ?></strong></div>
        <div class="text-muted small"><?= h($c['claimant_email']) ?></div>
        <?php if ($c['claimant_phone']): ?>
        <div class="text-muted small"><?= h($c['claimant_phone']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Verification — ADMIN ONLY view -->
      <div class="col-md-4">
        <div class="small fw-semibold text-warning mb-1">
          <i class="bi bi-shield-lock me-1"></i> Verification (admin only)
        </div>
        <?php if ($c['verify_answer'] !== null): ?>
        <div class="small"><strong>Expected:</strong> <?= h($c['verify_answer']) ?></div>
        <div class="small"><strong>Given:</strong>    <?= h($c['answer_given'] ?? '—') ?></div>
        <div class="mt-1">
          <?php if ($answerMatch === true): ?>
            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Match</span>
          <?php elseif ($answerMatch === false): ?>
            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Mismatch</span>
          <?php endif; ?>
        </div>
        <?php else: ?>
          <span class="text-muted small">No verification question set.</span>
        <?php endif; ?>
      </div>
    </div><!-- /row -->

    <?php if ($c['admin_note']): ?>
    <div class="alert alert-light py-2 small mb-2">
      <strong>Admin note:</strong> <?= h($c['admin_note']) ?>
      <?php if ($c['reviewer_name']): ?>
        — <?= h($c['reviewer_name']) ?> @ <?= format_datetime($c['reviewed_at']) ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Action buttons (pending only) -->
    <?php if ($c['status'] === 'pending'): ?>
    <div class="row g-2 mt-1">
      <!-- Approve -->
      <div class="col-md-6">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
          <input type="hidden" name="action" value="approve">
          <div class="input-group input-group-sm">
            <input type="text" class="form-control" name="admin_note"
                   placeholder="Optional note (visible to users)">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('Approve this claim? Both parties will be notified with contact details.')">
              <i class="bi bi-check-lg me-1"></i> Approve
            </button>
          </div>
        </form>
      </div>
      <!-- Reject -->
      <div class="col-md-6">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
          <input type="hidden" name="action" value="reject">
          <div class="input-group input-group-sm">
            <input type="text" class="form-control" name="admin_note"
                   placeholder="Reason for rejection">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Reject this claim?')">
              <i class="bi bi-x-lg me-1"></i> Reject
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php elseif ($c['status'] === 'approved'): ?>
    <form method="post" class="d-inline mt-2">
      <?= csrf_field() ?>
      <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
      <input type="hidden" name="action"   value="mark_returned">
      <button type="submit" class="btn btn-sm btn-secondary">
        <i class="bi bi-bag-check me-1"></i> Mark as Returned
      </button>
    </form>
    <?php endif; ?>

  </div><!-- /card-body -->
</div>
<?php endforeach; ?>

<?php render_pagination($pg['page'], $pg['totalPages'], $filterStatus ? ['status' => $filterStatus] : []); ?>
<?php endif; ?>

<?php
// ── Email helpers (local to this page) ──────────────────────────
function _send_approval_emails(PDO $pdo, array $claim, string $note): void
{
    try {
        $lu  = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $lu->execute([$claim['li_user_id']]);
        $lostUser  = $lu->fetch();

        $fu  = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $fu->execute([$claim['fi_user_id']]);
        $foundUser = $fu->fetch();

        if (!$lostUser || !$foundUser) return;

        $subject = 'Your claim has been APPROVED — Campus Lost & Found';

        $msgLost = "Hello {$lostUser['name']},\n\n"
            . "Great news! Your ownership claim has been APPROVED by the admin.\n\n"
            . "Item: {$claim['li_title']}\n\n"
            . "Contact the finder to arrange return:\n"
            . "  Name : {$foundUser['name']}\n"
            . "  Email: {$foundUser['email']}\n"
            . ($foundUser['phone'] ? "  Phone: {$foundUser['phone']}\n" : '')
            . ($note ? "\nAdmin note: $note\n" : '')
            . "\n— Campus Lost & Found";

        $msgFound = "Hello {$foundUser['name']},\n\n"
            . "An ownership claim on an item you reported has been APPROVED.\n\n"
            . "Item: {$claim['fi_title']}\n\n"
            . "The owner's contact details:\n"
            . "  Name : {$lostUser['name']}\n"
            . "  Email: {$lostUser['email']}\n"
            . ($lostUser['phone'] ? "  Phone: {$lostUser['phone']}\n" : '')
            . ($note ? "\nAdmin note: $note\n" : '')
            . "\n— Campus Lost & Found";

        send_mail($subject, $msgLost,  [$lostUser['email']  => $lostUser['name']]);
        send_mail($subject, $msgFound, [$foundUser['email'] => $foundUser['name']]);
    } catch (Throwable $e) {
        log_error('Approval email failed: ' . $e->getMessage());
    }
}

function _send_rejection_email(PDO $pdo, array $claim, string $note): void
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$claim['claimant_id']]);
        $user = $stmt->fetch();
        if (!$user) return;

        $subject = 'Your claim was not approved — Campus Lost & Found';
        $msg     = "Hello {$user['name']},\n\n"
            . "Unfortunately your claim on \"{$claim['li_title']}\" was not approved.\n"
            . ($note ? "Reason: $note\n" : '')
            . "\nYou may check the portal for other matches.\n\n"
            . "— Campus Lost & Found";

        send_mail($subject, $msg, [$user['email'] => $user['name']]);
    } catch (Throwable $e) {
        log_error('Rejection email failed: ' . $e->getMessage());
    }
}
?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
