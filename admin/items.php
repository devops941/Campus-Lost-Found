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

    $action = $_POST['action'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ? LIMIT 1');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if ($item) {
        if ($action === 'delete') {
            if ($item['photo']) {
                $path = ROOT_PATH . '/uploads/' . $item['photo'];
                if (file_exists($path)) @unlink($path);
            }
            $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$itemId]);
            redirect(BASE_URL . '/admin/items.php', 'success', 'Item deleted.');

        } elseif ($action === 'set_status') {
            $newStatus = $_POST['new_status'] ?? '';
            if (in_array($newStatus, ['open','claimed','returned'], true)) {
                $pdo->prepare('UPDATE items SET status = ? WHERE id = ?')
                    ->execute([$newStatus, $itemId]);
                redirect(BASE_URL . '/admin/items.php', 'success', 'Status updated.');
            }
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────
$fType   = in_array($_GET['type'] ?? '', ['','lost','found'], true) ? ($_GET['type'] ?? '') : '';
$fStatus = in_array($_GET['status'] ?? '', ['','open','claimed','returned'], true) ? ($_GET['status'] ?? '') : '';
$fQ      = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($fType !== '') {
    $where[]  = 'i.type = ?';
    $params[] = $fType;
}
if ($fStatus !== '') {
    $where[]  = 'i.status = ?';
    $params[] = $fStatus;
}
if ($fQ !== '') {
    $where[]  = '(i.title LIKE ? OR i.description LIKE ?)';
    $params[] = "%$fQ%";
    $params[] = "%$fQ%";
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM items i $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$pg = paginate($total, 20);

$stmt = $pdo->prepare(
    "SELECT i.*, u.name AS poster_name, u.email AS poster_email
     FROM items i
     JOIN users u ON i.user_id = u.id
     $whereSQL
     ORDER BY i.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$pg['limit'], $pg['offset']]));
$items = $stmt->fetchAll();

$pageTitle = 'Manage Items';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="bi bi-box-seam me-1"></i> Manage Items</h4>
  <span class="text-muted small"><?= number_format($total) ?> total</span>
</div>

<!-- Filter bar -->
<div class="card shadow-sm mb-4">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($fQ) ?>" placeholder="Search title…">
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="type">
          <option value="">All types</option>
          <option value="lost"  <?= $fType === 'lost'  ? 'selected' : '' ?>>Lost</option>
          <option value="found" <?= $fType === 'found' ? 'selected' : '' ?>>Found</option>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="status">
          <option value="">All statuses</option>
          <option value="open"     <?= $fStatus === 'open'     ? 'selected' : '' ?>>Open</option>
          <option value="claimed"  <?= $fStatus === 'claimed'  ? 'selected' : '' ?>>Claimed</option>
          <option value="returned" <?= $fStatus === 'returned' ? 'selected' : '' ?>>Returned</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>/admin/items.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php if (empty($items)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-inbox fs-1"></i>
  <p class="mt-2">No items found.</p>
</div>
<?php else: ?>

<div class="table-responsive">
  <table class="table table-hover align-middle bg-white rounded shadow-sm">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Item</th>
        <th>Category</th>
        <th>Posted by</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td class="text-muted small"><?= (int)$item['id'] ?></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <?php if ($item['photo']): ?>
              <img src="<?= BASE_URL ?>/uploads/<?= h($item['photo']) ?>"
                   width="40" height="40"
                   style="object-fit:cover;border-radius:4px;" alt="">
            <?php endif; ?>
            <div>
              <?= type_badge($item['type']) ?>
              <div class="small fw-semibold"><?= h(mb_substr($item['title'], 0, 50)) ?></div>
              <div class="text-muted" style="font-size:.7rem;"><?= h($item['location'] ?? '') ?></div>
            </div>
          </div>
        </td>
        <td><?= h(ucwords(str_replace('_', ' ', $item['category']))) ?></td>
        <td>
          <div class="small"><?= h($item['poster_name']) ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?= h($item['poster_email']) ?></div>
        </td>
        <td class="text-nowrap small"><?= format_date($item['item_date']) ?></td>
        <td>
          <?= status_badge($item['status']) ?>
        </td>
        <td>
          <div class="d-flex gap-1 flex-wrap">
            <!-- Quick status change -->
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="action"  value="set_status">
              <select name="new_status" class="form-select form-select-sm d-inline-block w-auto"
                      onchange="this.form.submit()" title="Change status">
                <?php foreach (['open','claimed','returned'] as $s): ?>
                <option value="<?= $s ?>" <?= $item['status'] === $s ? 'selected' : '' ?>>
                  <?= ucfirst($s) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </form>

            <!-- Delete -->
            <form method="post" class="d-inline"
                  onsubmit="return confirm('Delete item #<?= (int)$item['id'] ?>? This cannot be undone.')">
              <?= csrf_field() ?>
              <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="action"  value="delete">
              <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php render_pagination(
    $pg['page'],
    $pg['totalPages'],
    array_filter(['type' => $fType, 'status' => $fStatus, 'q' => $fQ])
); ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
