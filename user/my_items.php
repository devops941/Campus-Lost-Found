<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mail.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_login();

$pdo = get_db();
$uid = current_user_id();

// ── DELETE action ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    csrf_check();
    $itemId = (int)($_POST['delete_item'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ? LIMIT 1');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if ($item) {
        assert_owns_item($item); // 403 if not owner
        // Remove photo file
        if ($item['photo']) {
            $photoPath = ROOT_PATH . '/uploads/' . $item['photo'];
            if (file_exists($photoPath)) {
                @unlink($photoPath);
            }
        }
        $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$itemId]);
        redirect(BASE_URL . '/user/my_items.php', 'success', 'Item deleted.');
    }
}

// ── Fetch user's items ────────────────────────────────────────────
$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE user_id = ?');
$totalStmt->execute([$uid]);
$total = (int)$totalStmt->fetchColumn();

$pg     = paginate($total, 10);
$stmt   = $pdo->prepare(
    "SELECT i.*,
            (SELECT COUNT(*) FROM matches m
              WHERE (m.lost_item_id = i.id OR m.found_item_id = i.id)) AS match_count
     FROM items i
     WHERE i.user_id = ?
     ORDER BY i.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$uid, $pg['limit'], $pg['offset']]);
$items = $stmt->fetchAll();

$pageTitle = 'My Items';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="bi bi-list-ul me-1"></i> My Items</h4>
  <a href="<?= BASE_URL ?>/user/post_item.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i> Post New Item
  </a>
</div>

<?php if (empty($items)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-inbox fs-1"></i>
  <p class="mt-2">You haven't posted any items yet.</p>
  <a href="<?= BASE_URL ?>/user/post_item.php" class="btn btn-primary">Post an Item</a>
</div>
<?php else: ?>

<div class="table-responsive">
  <table class="table table-hover align-middle bg-white rounded shadow-sm">
    <thead class="table-light">
      <tr>
        <th>Item</th>
        <th>Category</th>
        <th>Date</th>
        <th>Status</th>
        <th>Matches</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <?php if ($item['photo']): ?>
              <img src="<?= BASE_URL ?>/uploads/<?= h($item['photo']) ?>"
                   width="48" height="48"
                   style="object-fit:cover;border-radius:4px;"
                   alt="">
            <?php else: ?>
              <div class="bg-light rounded d-flex align-items-center justify-content-center"
                   style="width:48px;height:48px;">
                <i class="bi bi-image text-muted"></i>
              </div>
            <?php endif; ?>
            <div>
              <?= type_badge($item['type']) ?>
              <div class="fw-semibold small"><?= h($item['title']) ?></div>
              <div class="text-muted" style="font-size:.75rem;">
                <?= h($item['location'] ?? '—') ?>
              </div>
            </div>
          </div>
        </td>
        <td><?= h(ucwords(str_replace('_', ' ', $item['category']))) ?></td>
        <td class="text-nowrap"><?= format_date($item['item_date']) ?></td>
        <td><?= status_badge($item['status']) ?></td>
        <td>
          <?php if ((int)$item['match_count'] > 0): ?>
            <a href="<?= BASE_URL ?>/user/matches.php?item=<?= (int)$item['id'] ?>"
               class="badge bg-success text-decoration-none">
              <?= (int)$item['match_count'] ?> match<?= (int)$item['match_count'] !== 1 ? 'es' : '' ?>
            </a>
          <?php else: ?>
            <span class="text-muted small">None yet</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($item['status'] !== 'returned'): ?>
          <form method="post" class="d-inline"
                onsubmit="return confirm('Delete this item? This cannot be undone.')">
            <?= csrf_field() ?>
            <input type="hidden" name="delete_item" value="<?= (int)$item['id'] ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">
              <i class="bi bi-trash"></i>
            </button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php render_pagination($pg['page'], $pg['totalPages'], []); ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
