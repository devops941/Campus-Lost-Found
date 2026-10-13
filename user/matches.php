<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mail.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/match.php';

require_login();

$pdo = get_db();
$uid = current_user_id();

// Optional: filter by a specific item owned by the user
$filterItemId = isset($_GET['item']) ? (int)$_GET['item'] : 0;

// ── Fetch matches where the user owns either the lost or found item ─
// NEVER expose: finder phone, finder email, verify_answer
$whereExtra = $filterItemId ? 'AND (li.id = ? OR fi.id = ?)' : '';
$params = [$uid, $uid];
if ($filterItemId) {
    $params[] = $filterItemId;
    $params[] = $filterItemId;
}

$totalSql = "
    SELECT COUNT(*)
    FROM matches m
    JOIN items li ON m.lost_item_id  = li.id
    JOIN items fi ON m.found_item_id = fi.id
    WHERE (li.user_id = ? OR fi.user_id = ?)
    $whereExtra
";
$totalStmt = $pdo->prepare($totalSql);
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$pg = paginate($total, 10);

// Safe columns — explicitly exclude verify_answer and contact details of the OTHER party
$sql = "
    SELECT
        m.id            AS match_id,
        m.score,
        m.created_at    AS matched_at,
        -- lost item (safe)
        li.id           AS li_id,
        li.title        AS li_title,
        li.category     AS li_category,
        li.description  AS li_description,
        li.location     AS li_location,
        li.item_date    AS li_date,
        li.photo        AS li_photo,
        li.status       AS li_status,
        li.user_id      AS li_user_id,
        lu.name         AS li_owner_name,
        -- found item (safe — no email/phone/verify_answer)
        fi.id           AS fi_id,
        fi.title        AS fi_title,
        fi.category     AS fi_category,
        fi.description  AS fi_description,
        fi.location     AS fi_location,
        fi.item_date    AS fi_date,
        fi.photo        AS fi_photo,
        fi.status       AS fi_status,
        fi.user_id      AS fi_user_id,
        fi.verify_question AS fi_verify_question,
        fu.name         AS fi_owner_name,
        -- does current user already have a claim on this match?
        (SELECT id FROM claims
          WHERE match_id = m.id AND claimant_id = $uid
          LIMIT 1) AS my_claim_id,
        (SELECT status FROM claims
          WHERE match_id = m.id AND claimant_id = $uid
          LIMIT 1) AS my_claim_status
    FROM matches m
    JOIN items li ON m.lost_item_id  = li.id
    JOIN items fi ON m.found_item_id = fi.id
    JOIN users lu ON li.user_id = lu.id
    JOIN users fu ON fi.user_id = fu.id
    WHERE (li.user_id = ? OR fi.user_id = ?)
    $whereExtra
    ORDER BY m.score DESC, m.created_at DESC
    LIMIT ? OFFSET ?
";
$params2 = array_merge($params, [$pg['limit'], $pg['offset']]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params2);
$matches = $stmt->fetchAll();

$pageTitle = 'My Matches';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="bi bi-diagram-2 me-1"></i> My Matches</h4>
  <?php if ($filterItemId): ?>
    <a href="<?= BASE_URL ?>/user/matches.php" class="btn btn-outline-secondary btn-sm">
      Show All Matches
    </a>
  <?php endif; ?>
</div>

<?php if (empty($matches)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-search fs-1"></i>
  <p class="mt-2">No matches found yet.</p>
  <p class="small">Matches appear automatically when a new item is posted that scores
     &ge; <?= MATCH_THRESHOLD * 100 ?>%.</p>
</div>
<?php else: ?>

<?php foreach ($matches as $m):
    $iAmLostOwner  = (int)$m['li_user_id'] === $uid;
    $iAmFoundOwner = (int)$m['fi_user_id'] === $uid;
    $pct           = (int)round((float)$m['score'] * 100);
    $canClaim      = $iAmLostOwner
                  && $m['li_status'] === 'open'
                  && $m['fi_status'] === 'open'
                  && $m['my_claim_id'] === null;
?>
<div class="card shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>
      Match #<?= (int)$m['match_id'] ?> &nbsp;
      <span class="badge bg-<?= $pct >= 80 ? 'success' : ($pct >= 60 ? 'warning' : 'secondary') ?>">
        <?= $pct ?>% Match
      </span>
    </span>
    <small class="text-muted"><?= format_datetime($m['matched_at']) ?></small>
  </div>

  <!-- Score bar -->
  <div class="progress score-bar mx-3 mt-2" style="height:6px;">
    <div class="progress-bar bg-<?= $pct >= 80 ? 'success' : ($pct >= 60 ? 'warning' : 'secondary') ?>"
         style="width:<?= $pct ?>%"></div>
  </div>

  <div class="card-body">
    <div class="row g-3">

      <!-- Lost item -->
      <div class="col-md-6">
        <div class="p-3 rounded bg-danger bg-opacity-10 h-100">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-danger">Lost</span>
            <?= status_badge($m['li_status']) ?>
            <?php if ($iAmLostOwner): ?><span class="badge bg-light text-dark border">You</span><?php endif; ?>
          </div>
          <?php if ($m['li_photo']): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= h($m['li_photo']) ?>"
                 class="img-fluid rounded mb-2" style="max-height:120px;object-fit:cover;">
          <?php endif; ?>
          <div class="fw-semibold"><?= h($m['li_title']) ?></div>
          <div class="text-muted small mb-1"><?= h(ucwords(str_replace('_','',$m['li_category']))) ?></div>
          <div class="small"><?= h(mb_substr($m['li_description'] ?? '', 0, 120)) ?></div>
          <div class="small text-muted mt-1">
            <i class="bi bi-geo-alt"></i> <?= h($m['li_location'] ?? '—') ?> &nbsp;
            <i class="bi bi-calendar"></i> <?= format_date($m['li_date']) ?>
          </div>
          <?php if (!$iAmLostOwner): ?>
          <div class="small text-muted mt-1">Posted by: <?= h($m['li_owner_name']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Found item (contact info withheld) -->
      <div class="col-md-6">
        <div class="p-3 rounded bg-primary bg-opacity-10 h-100">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-primary">Found</span>
            <?= status_badge($m['fi_status']) ?>
            <?php if ($iAmFoundOwner): ?><span class="badge bg-light text-dark border">You</span><?php endif; ?>
          </div>
          <?php if ($m['fi_photo']): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= h($m['fi_photo']) ?>"
                 class="img-fluid rounded mb-2" style="max-height:120px;object-fit:cover;">
          <?php endif; ?>
          <div class="fw-semibold"><?= h($m['fi_title']) ?></div>
          <div class="text-muted small mb-1"><?= h(ucwords(str_replace('_','',$m['fi_category']))) ?></div>
          <div class="small"><?= h(mb_substr($m['fi_description'] ?? '', 0, 120)) ?></div>
          <div class="small text-muted mt-1">
            <i class="bi bi-geo-alt"></i> <?= h($m['fi_location'] ?? '—') ?> &nbsp;
            <i class="bi bi-calendar"></i> <?= format_date($m['fi_date']) ?>
          </div>
          <!-- finder name only — no email/phone/verify_answer exposed -->
          <?php if (!$iAmFoundOwner): ?>
          <div class="small text-muted mt-1">Reported by: <?= h($m['fi_owner_name']) ?></div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /row -->

    <!-- Claim section -->
    <div class="mt-3 pt-3 border-top d-flex align-items-center justify-content-between">
      <div class="small text-muted">
        <?php if ($m['fi_verify_question']): ?>
          <i class="bi bi-shield-check me-1 text-warning"></i>
          Verification question set by the finder.
        <?php endif; ?>
      </div>

      <div class="d-flex align-items-center gap-2">
        <?php if ($m['my_claim_id']): ?>
          <?php
            $cs    = $m['my_claim_status'];
            $cIcon = $cs === 'approved' ? 'bi-check-circle-fill' : ($cs === 'rejected' ? 'bi-x-circle-fill' : 'bi-hourglass-split');
            $cCol  = $cs === 'approved' ? 'success' : ($cs === 'rejected' ? 'danger' : 'warning');
          ?>
          <span class="badge bg-<?= $cCol ?> d-flex align-items-center gap-1 px-3 py-2">
            <i class="bi <?= $cIcon ?>"></i>
            Claim <?= ucfirst($cs) ?>
          </span>
          <?php if ($cs === 'approved'): ?>
            <span class="text-muted small">Admin will share contact details by email.</span>
          <?php elseif ($cs === 'rejected'): ?>
            <span class="text-muted small">Claim was not approved.</span>
          <?php else: ?>
            <span class="text-muted small">Awaiting admin review.</span>
          <?php endif; ?>
        <?php elseif ($canClaim): ?>
          <a href="<?= BASE_URL ?>/user/claim.php?match=<?= (int)$m['match_id'] ?>"
             class="btn btn-success btn-sm fw-semibold">
            <i class="bi bi-hand-index-thumb me-1"></i>Raise a Claim
          </a>
        <?php elseif ($m['li_status'] !== 'open' || $m['fi_status'] !== 'open'): ?>
          <span class="badge bg-secondary">Item no longer available</span>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /card-body -->
</div>
<?php endforeach; ?>

<?php render_pagination($pg['page'], $pg['totalPages'], $filterItemId ? ['item' => $filterItemId] : []); ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
