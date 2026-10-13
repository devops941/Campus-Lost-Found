<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

$categories = [
    ''            => 'All Categories',
    'id_card'     => 'ID Card',
    'electronics' => 'Electronics',
    'books'       => 'Books / Notes',
    'bottle'      => 'Water Bottle',
    'bag'         => 'Bag / Backpack',
    'keys'        => 'Keys',
    'other'       => 'Other',
];

// ── Filters (all sanitised) ───────────────────────────────────────
$f = [
    'type'      => in_array($_GET['type']     ?? '', ['', 'lost', 'found'], true) ? ($_GET['type'] ?? '') : '',
    'category'  => array_key_exists($_GET['category'] ?? '', $categories)         ? ($_GET['category'] ?? '') : '',
    'location'  => trim($_GET['location']  ?? ''),
    'q'         => trim($_GET['q']         ?? ''),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to'   => trim($_GET['date_to']   ?? ''),
    'status'    => in_array($_GET['status'] ?? '', ['', 'open', 'claimed', 'returned'], true)
                    ? ($_GET['status'] ?? '') : '',
];

// Single-item detail view
$detailId   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detailItem = null;
if ($detailId > 0) {
    $stmt = $pdo->prepare(
        "SELECT i.*, u.name AS poster_name FROM items i
          JOIN users u ON i.user_id = u.id
         WHERE i.id = ? LIMIT 1"
    );
    $stmt->execute([$detailId]);
    $detailItem = $stmt->fetch() ?: null;
}

// ── Build WHERE clause ────────────────────────────────────────────
$where  = ["i.status != 'returned'"]; // default: exclude returned (overridden below)
$params = [];

// Status filter
if ($f['status'] !== '') {
    $where  = ["i.status = ?"];
    $params = [$f['status']];
} else {
    $where  = ["i.status = 'open'"]; // default: open only
    $params = [];
}

if ($f['type'] !== '') {
    $where[]  = 'i.type = ?';
    $params[] = $f['type'];
}
if ($f['category'] !== '') {
    $where[]  = 'i.category = ?';
    $params[] = $f['category'];
}
if ($f['location'] !== '') {
    $where[]  = 'i.location LIKE ?';
    $params[] = '%' . $f['location'] . '%';
}
if ($f['date_from'] !== '') {
    $where[]  = 'i.item_date >= ?';
    $params[] = $f['date_from'];
}
if ($f['date_to'] !== '') {
    $where[]  = 'i.item_date <= ?';
    $params[] = $f['date_to'];
}
if ($f['q'] !== '') {
    $where[]  = '(i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)';
    $like     = '%' . $f['q'] . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM items i $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pg = paginate($total, 10);

// Fetch
$stmt = $pdo->prepare(
    "SELECT i.*, u.name AS poster_name
     FROM items i
     JOIN users u ON i.user_id = u.id
     $whereSQL
     ORDER BY i.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$pg['limit'], $pg['offset']]));
$items = $stmt->fetchAll();

$pageTitle = 'Browse Items';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Single item detail panel ──────────────────────────────────── -->
<?php if ($detailItem): ?>
<div class="card shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <div class="d-flex gap-2 mb-2">
          <?= type_badge($detailItem['type']) ?>
          <?= status_badge($detailItem['status']) ?>
          <span class="badge bg-light text-dark border">
            <?= h(ucwords(str_replace('_', ' ', $detailItem['category']))) ?>
          </span>
        </div>
        <h4 class="mb-0"><?= h($detailItem['title']) ?></h4>
      </div>
      <a href="<?= BASE_URL ?>/search.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
      </a>
    </div>

    <div class="row g-4">
      <?php if ($detailItem['photo']): ?>
      <div class="col-md-4">
        <img src="<?= BASE_URL ?>/uploads/<?= h($detailItem['photo']) ?>"
             class="img-fluid rounded shadow-sm" alt="Item photo">
      </div>
      <?php endif; ?>
      <div class="col">
        <?php if ($detailItem['description']): ?>
        <p><?= nl2br(h($detailItem['description'])) ?></p>
        <?php endif; ?>
        <table class="table table-sm table-borderless">
          <tr><th>Location</th><td><?= h($detailItem['location'] ?? '—') ?></td></tr>
          <tr><th>Date</th>    <td><?= format_date($detailItem['item_date']) ?></td></tr>
          <tr><th>Posted by</th><td><?= h($detailItem['poster_name']) ?></td></tr>
          <tr><th>Posted on</th><td><?= format_datetime($detailItem['created_at']) ?></td></tr>
        </table>
        <?php if ($detailItem['verify_question'] && $detailItem['type'] === 'found'): ?>
        <div class="alert alert-info py-2">
          <i class="bi bi-shield-check me-1"></i>
          This item has a verification question. Log in to raise a claim.
        </div>
        <?php endif; ?>
        <?php if (is_logged_in()
              && $detailItem['status'] === 'open'
              && (int)$detailItem['user_id'] !== current_user_id()): ?>
          <?php
          // Check if there's a match between this item and any of the user's items
          $mStmt = $pdo->prepare(
              "SELECT m.id FROM matches m
               JOIN items mi ON (mi.id = m.lost_item_id OR mi.id = m.found_item_id)
               WHERE mi.user_id = ?
                 AND (m.lost_item_id = ? OR m.found_item_id = ?)
               LIMIT 1"
          );
          $mStmt->execute([current_user_id(), $detailId, $detailId]);
          $matchRow = $mStmt->fetch();
          if ($matchRow): ?>
          <a href="<?= BASE_URL ?>/user/matches.php" class="btn btn-success">
            <i class="bi bi-diagram-2 me-1"></i> View Match & Claim
          </a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Filter form ────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="get" action="" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Keyword</label>
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($f['q']) ?>" placeholder="Search title, description…">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Type</label>
        <select class="form-select form-select-sm" name="type">
          <option value="">All</option>
          <option value="lost"  <?= $f['type'] === 'lost'  ? 'selected' : '' ?>>Lost</option>
          <option value="found" <?= $f['type'] === 'found' ? 'selected' : '' ?>>Found</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Category</label>
        <select class="form-select form-select-sm" name="category">
          <?php foreach ($categories as $val => $label): ?>
          <option value="<?= h($val) ?>" <?= $f['category'] === $val ? 'selected' : '' ?>>
            <?= h($label) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Location</label>
        <input type="text" class="form-control form-control-sm" name="location"
               value="<?= h($f['location']) ?>" placeholder="e.g. Library">
      </div>
      <div class="col-md-1">
        <label class="form-label small fw-semibold">From</label>
        <input type="date" class="form-control form-control-sm" name="date_from"
               value="<?= h($f['date_from']) ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label small fw-semibold">To</label>
        <input type="date" class="form-control form-control-sm" name="date_to"
               value="<?= h($f['date_to']) ?>">
      </div>
      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="bi bi-search"></i>
        </button>
        <a href="<?= BASE_URL ?>/search.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-x"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- ── Results ───────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="text-muted small">
    <?= number_format($total) ?> item<?= $total !== 1 ? 's' : '' ?> found
    <?= array_filter(array_values($f)) ? '(filtered)' : '' ?>
  </span>
  <?php if (is_logged_in()): ?>
  <a href="<?= BASE_URL ?>/user/post_item.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i> Post Item
  </a>
  <?php endif; ?>
</div>

<?php if (empty($items)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-search fs-1"></i>
  <p class="mt-2">No items match your filters.</p>
  <a href="<?= BASE_URL ?>/search.php" class="btn btn-outline-primary btn-sm">Clear Filters</a>
</div>
<?php else: ?>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
  <?php foreach ($items as $item): ?>
  <div class="col">
    <div class="card h-100 shadow-sm">

      <?php if ($item['photo']): ?>
        <img src="<?= BASE_URL ?>/uploads/<?= h($item['photo']) ?>"
             class="card-img-top" style="height:170px;object-fit:cover;" alt="">
      <?php else: ?>
        <div class="bg-light d-flex align-items-center justify-content-center"
             style="height:170px;">
          <i class="bi bi-image text-muted fs-1"></i>
        </div>
      <?php endif; ?>

      <div class="card-body pb-2">
        <div class="d-flex gap-1 flex-wrap mb-2">
          <?= type_badge($item['type']) ?>
          <?= status_badge($item['status']) ?>
          <span class="badge bg-light text-dark border">
            <?= h(ucwords(str_replace('_', ' ', $item['category']))) ?>
          </span>
        </div>
        <h6 class="card-title mb-1"><?= h($item['title']) ?></h6>
        <p class="card-text small text-muted mb-1">
          <i class="bi bi-geo-alt me-1"></i><?= h($item['location'] ?? '—') ?>
        </p>
        <p class="card-text small text-muted text-truncate">
          <?= h($item['description'] ?? '') ?>
        </p>
      </div>

      <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
        <small class="text-muted">
          <i class="bi bi-calendar me-1"></i><?= format_date($item['item_date']) ?>
        </small>
        <a href="<?= BASE_URL ?>/search.php?id=<?= (int)$item['id'] ?>"
           class="btn btn-outline-primary btn-sm">Details</a>
      </div>

    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php render_pagination($pg['page'], $pg['totalPages'], array_filter($f)); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
