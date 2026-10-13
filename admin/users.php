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
    $userId = (int)($_POST['user_id'] ?? 0);

    // Prevent admin from blocking themselves
    if ($userId === current_user_id()) {
        redirect(BASE_URL . '/admin/users.php', 'warning', 'You cannot modify your own account.');
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();

    if ($targetUser) {
        if ($action === 'block') {
            $pdo->prepare('UPDATE users SET is_blocked = 1 WHERE id = ?')->execute([$userId]);
            redirect(BASE_URL . '/admin/users.php', 'success', "User {$targetUser['name']} has been blocked.");
        } elseif ($action === 'unblock') {
            $pdo->prepare('UPDATE users SET is_blocked = 0 WHERE id = ?')->execute([$userId]);
            redirect(BASE_URL . '/admin/users.php', 'success', "User {$targetUser['name']} has been unblocked.");
        } elseif ($action === 'delete') {
            if ($targetUser['role'] === 'admin') {
                redirect(BASE_URL . '/admin/users.php', 'danger', 'Cannot delete an admin account.');
            }
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            redirect(BASE_URL . '/admin/users.php', 'success', 'User account deleted.');
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────
$fQ       = trim($_GET['q'] ?? '');
$fRole    = in_array($_GET['role'] ?? '', ['', 'user', 'admin'], true) ? ($_GET['role'] ?? '') : '';
$fBlocked = in_array($_GET['blocked'] ?? '', ['', '0', '1'], true)    ? ($_GET['blocked'] ?? '') : '';

$where  = [];
$params = [];

if ($fQ !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ?)';
    $params[] = "%$fQ%";
    $params[] = "%$fQ%";
}
if ($fRole !== '') {
    $where[]  = 'role = ?';
    $params[] = $fRole;
}
if ($fBlocked !== '') {
    $where[]  = 'is_blocked = ?';
    $params[] = (int)$fBlocked;
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$pg = paginate($total, 20);

$stmt = $pdo->prepare(
    "SELECT u.*,
            (SELECT COUNT(*) FROM items WHERE user_id = u.id) AS item_count,
            (SELECT COUNT(*) FROM claims WHERE claimant_id = u.id) AS claim_count
     FROM users u
     $whereSQL
     ORDER BY u.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$pg['limit'], $pg['offset']]));
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="bi bi-people me-1"></i> Manage Users</h4>
  <span class="text-muted small"><?= number_format($total) ?> total</span>
</div>

<!-- Filter bar -->
<div class="card shadow-sm mb-4">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($fQ) ?>" placeholder="Search name or email…">
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="role">
          <option value="">All roles</option>
          <option value="user"  <?= $fRole === 'user'  ? 'selected' : '' ?>>User</option>
          <option value="admin" <?= $fRole === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="blocked">
          <option value="">All</option>
          <option value="0" <?= $fBlocked === '0' ? 'selected' : '' ?>>Active</option>
          <option value="1" <?= $fBlocked === '1' ? 'selected' : '' ?>>Blocked</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php if (empty($users)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-people fs-1"></i>
  <p class="mt-2">No users found.</p>
</div>
<?php else: ?>

<div class="table-responsive">
  <table class="table table-hover align-middle bg-white rounded shadow-sm">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Name / Email</th>
        <th>Phone</th>
        <th>Role</th>
        <th>Items</th>
        <th>Claims</th>
        <th>Joined</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr class="<?= $u['is_blocked'] ? 'table-danger' : '' ?>">
        <td class="text-muted small"><?= (int)$u['id'] ?></td>
        <td>
          <div class="fw-semibold"><?= h($u['name']) ?></div>
          <div class="text-muted small"><?= h($u['email']) ?></div>
        </td>
        <td class="small"><?= h($u['phone'] ?? '—') ?></td>
        <td>
          <span class="badge <?= $u['role'] === 'admin' ? 'bg-dark' : 'bg-light text-dark border' ?>">
            <?= h(ucfirst($u['role'])) ?>
          </span>
        </td>
        <td>
          <a href="<?= BASE_URL ?>/admin/items.php?q=<?= urlencode($u['email']) ?>"
             class="badge bg-light text-dark border text-decoration-none">
            <?= (int)$u['item_count'] ?>
          </a>
        </td>
        <td class="small"><?= (int)$u['claim_count'] ?></td>
        <td class="text-nowrap small"><?= format_date($u['created_at']) ?></td>
        <td>
          <?php if ($u['is_blocked']): ?>
            <span class="badge bg-danger">Blocked</span>
          <?php else: ?>
            <span class="badge bg-success">Active</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="d-flex gap-1">
            <?php if ((int)$u['id'] !== current_user_id()): ?>

            <!-- Block / Unblock -->
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <?php if ($u['is_blocked']): ?>
                <input type="hidden" name="action" value="unblock">
                <button type="submit" class="btn btn-outline-success btn-sm" title="Unblock">
                  <i class="bi bi-person-check"></i>
                </button>
              <?php else: ?>
                <input type="hidden" name="action" value="block">
                <button type="submit" class="btn btn-outline-warning btn-sm"
                        onclick="return confirm('Block <?= h(addslashes($u['name'])) ?>?')"
                        title="Block">
                  <i class="bi bi-person-slash"></i>
                </button>
              <?php endif; ?>
            </form>

            <!-- Delete (non-admin users only) -->
            <?php if ($u['role'] !== 'admin'): ?>
            <form method="post" class="d-inline"
                  onsubmit="return confirm('Delete <?= h(addslashes($u['name'])) ?>? All their items and claims will also be removed.')">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="action"  value="delete">
              <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>

            <?php else: ?>
            <span class="text-muted small">You</span>
            <?php endif; ?>
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
    array_filter(['q' => $fQ, 'role' => $fRole, 'blocked' => $fBlocked])
); ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
