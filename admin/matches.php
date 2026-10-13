<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mail.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/match.php';

require_admin();

$pdo = get_db();

// ── POST: admin can delete a false-positive match ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action']   ?? '';
    $matchId = (int)($_POST['match_id'] ?? 0);

    if ($action === 'delete' && $matchId > 0) {
        // Only delete if no claim exists on this match
        $hasClaim = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE match_id = ?');
        $hasClaim->execute([$matchId]);
        if ((int)$hasClaim->fetchColumn() === 0) {
            $pdo->prepare('DELETE FROM matches WHERE id = ?')->execute([$matchId]);
            redirect(BASE_URL . '/admin/matches.php', 'success', 'Match deleted.');
        } else {
            redirect(BASE_URL . '/admin/matches.php', 'warning', 'Cannot delete — a claim exists on this match.');
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────
$fCategory = trim($_GET['category'] ?? '');
$fMinScore = (float)($_GET['min_score'] ?? 0);
$fStatus   = trim($_GET['claim_status'] ?? ''); // pending/approved/rejected/none

$where  = [];
$params = [];

if ($fCategory !== '') {
    $where[]  = 'li.category = ?';
    $params[] = $fCategory;
}
if ($fMinScore > 0) {
    $where[]  = 'm.score >= ?';
    $params[] = $fMinScore;
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$countSql = "
    SELECT COUNT(*)
    FROM matches m
    JOIN items li ON m.lost_item_id  = li.id
    JOIN items fi ON m.found_item_id = fi.id
    $whereSQL
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pg = paginate($total, 20);

// Main query
$sql = "
    SELECT
        m.id           AS match_id,
        m.score,
        m.created_at   AS matched_at,
        -- lost item
        li.id          AS li_id,
        li.title       AS li_title,
        li.category    AS li_category,
        li.location    AS li_location,
        li.item_date   AS li_date,
        li.status      AS li_status,
        lu.name        AS li_owner,
        lu.email       AS li_email,
        -- found item
        fi.id          AS fi_id,
        fi.title       AS fi_title,
        fi.location    AS fi_location,
        fi.item_date   AS fi_date,
        fi.status      AS fi_status,
        fu.name        AS fi_owner,
        fu.email       AS fi_email,
        -- claim summary
        (SELECT status FROM claims WHERE match_id = m.id ORDER BY created_at DESC LIMIT 1) AS claim_status,
        (SELECT COUNT(*) FROM claims WHERE match_id = m.id) AS claim_count
    FROM matches m
    JOIN items li ON m.lost_item_id  = li.id
    JOIN items fi ON m.found_item_id = fi.id
    JOIN users lu ON li.user_id = lu.id
    JOIN users fu ON fi.user_id = fu.id
    $whereSQL
    ORDER BY m.score DESC, m.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$pg['limit'], $pg['offset']]));
$matches = $stmt->fetchAll();

// Distinct categories for filter dropdown
$cats = $pdo->query(
    "SELECT DISTINCT li.category FROM matches m
     JOIN items li ON m.lost_item_id = li.id ORDER BY li.category"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Manage Matches';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="bi bi-diagram-2 me-1"></i> Smart Matches
    <span class="badge bg-secondary fw-normal fs-6 ms-2"><?= number_format($total) ?> total</span>
  </h4>
  <span class="text-muted small">Threshold: &ge;<?= (int)(MATCH_THRESHOLD * 100) ?>%</span>
</div>

<!-- Filter bar -->
<div class="card shadow-sm mb-4">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label form-label-sm mb-1">Category</label>
        <select class="form-select form-select-sm" name="category">
          <option value="">All categories</option>
          <?php foreach ($cats as $c): ?>
          <option value="<?= h($c) ?>" <?= $fCategory === $c ? 'selected' : '' ?>>
            <?= h(ucwords(str_replace('_', ' ', $c))) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label form-label-sm mb-1">Min Score (%)</label>
        <input type="number" class="form-control form-control-sm" name="min_score"
               value="<?= $fMinScore > 0 ? (int)($fMinScore * 100) : '' ?>"
               min="0" max="100" placeholder="e.g. 70">
      </div>
      <div class="col-md-3 d-flex gap-1 align-items-end">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>/admin/matches.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php if (empty($matches)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-diagram-2 fs-1"></i>
  <p class="mt-2">No matches found.</p>
  <p class="small">Matches are created automatically when a new item's score &ge;
    <?= (int)(MATCH_THRESHOLD * 100) ?>%.</p>
</div>
<?php else: ?>

<div class="table-responsive">
  <table class="table table-hover align-middle bg-white rounded shadow-sm">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Score</th>
        <th>Lost Item</th>
        <th>Found Item</th>
        <th>Category</th>
        <th>Claim</th>
        <th>Date</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($matches as $m):
          $pct = (int)round((float)$m['score'] * 100);
          $barColor = $pct >= 80 ? 'success' : ($pct >= 60 ? 'warning' : 'secondary');
      ?>
      <tr>
        <td class="text-muted small"><?= (int)$m['match_id'] ?></td>

        <!-- Score -->
        <td style="min-width:90px;">
          <div class="fw-semibold text-<?= $barColor ?>"><?= $pct ?>%</div>
          <div class="progress" style="height:4px;">
            <div class="progress-bar bg-<?= $barColor ?>" style="width:<?= $pct ?>%"></div>
          </div>
        </td>

        <!-- Lost item -->
        <td>
          <span class="badge bg-danger mb-1">Lost</span>
          <div class="small fw-semibold"><?= h(mb_substr($m['li_title'], 0, 45)) ?></div>
          <div class="text-muted" style="font-size:.72rem;">
            <?= h($m['li_owner']) ?> &middot; <?= status_badge($m['li_status']) ?>
          </div>
          <div class="text-muted" style="font-size:.72rem;">
            <i class="bi bi-geo-alt"></i> <?= h($m['li_location'] ?? '—') ?>
          </div>
        </td>

        <!-- Found item -->
        <td>
          <span class="badge bg-primary mb-1">Found</span>
          <div class="small fw-semibold"><?= h(mb_substr($m['fi_title'], 0, 45)) ?></div>
          <div class="text-muted" style="font-size:.72rem;">
            <?= h($m['fi_owner']) ?> &middot; <?= status_badge($m['fi_status']) ?>
          </div>
          <div class="text-muted" style="font-size:.72rem;">
            <i class="bi bi-geo-alt"></i> <?= h($m['fi_location'] ?? '—') ?>
          </div>
        </td>

        <!-- Category -->
        <td class="small"><?= h(ucwords(str_replace('_', ' ', $m['li_category']))) ?></td>

        <!-- Claim status -->
        <td>
          <?php if ((int)$m['claim_count'] > 0): ?>
            <?php
            $cs     = $m['claim_status'];
            $cColor = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$cs] ?? 'secondary';
            $cIcon  = ['pending'=>'bi-hourglass-split','approved'=>'bi-check-circle-fill','rejected'=>'bi-x-circle-fill'][$cs] ?? 'bi-circle';
            ?>
            <div class="d-flex align-items-center gap-1 mb-1">
              <span class="badge bg-<?= $cColor ?> d-flex align-items-center gap-1">
                <i class="bi <?= $cIcon ?>"></i><?= h(ucfirst($cs)) ?>
              </span>
              <?php if ((int)$m['claim_count'] > 1): ?>
                <span class="text-muted" style="font-size:.72rem;"><?= (int)$m['claim_count'] ?>&times;</span>
              <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/admin/claims.php" class="btn btn-outline-primary btn-sm py-0" style="font-size:.75rem;">
              <i class="bi bi-arrow-right me-1"></i>Review
            </a>
          <?php else: ?>
            <span class="text-muted small fst-italic">No claim yet</span>
          <?php endif; ?>
        </td>

        <!-- Date -->
        <td class="text-nowrap small text-muted"><?= format_datetime($m['matched_at']) ?></td>

        <!-- Actions -->
        <td>
          <div class="d-flex flex-column gap-1">
            <div class="d-flex gap-1">
              <a href="<?= BASE_URL ?>/search.php?id=<?= (int)$m['li_id'] ?>"
                 class="btn btn-danger btn-sm py-0 px-2" style="font-size:.75rem;" title="View lost item">
                <i class="bi bi-eye me-1"></i>Lost
              </a>
              <a href="<?= BASE_URL ?>/search.php?id=<?= (int)$m['fi_id'] ?>"
                 class="btn btn-primary btn-sm py-0 px-2" style="font-size:.75rem;" title="View found item">
                <i class="bi bi-eye me-1"></i>Found
              </a>
            </div>
            <?php if ((int)$m['claim_count'] === 0): ?>
            <form method="post"
                  onsubmit="return confirm('Remove this match? The items remain open and can re-match.')">
              <?= csrf_field() ?>
              <input type="hidden" name="match_id" value="<?= (int)$m['match_id'] ?>">
              <input type="hidden" name="action"   value="delete">
              <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2 w-100"
                      style="font-size:.75rem;">
                <i class="bi bi-trash me-1"></i>Remove
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php render_pagination($pg['page'], $pg['totalPages'],
    array_filter(['category' => $fCategory, 'min_score' => $fMinScore > 0 ? (int)($fMinScore*100) : ''])
); ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
