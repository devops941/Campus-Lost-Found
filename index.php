<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

// Stats
$stats = $pdo->query(
    "SELECT
        SUM(status='open')     AS open_count,
        SUM(status='returned') AS returned_count,
        COUNT(*)               AS total_count
     FROM items"
)->fetch();

// Recent open items (6)
$stmt = $pdo->prepare(
    "SELECT i.*, u.name AS poster_name
     FROM items i JOIN users u ON i.user_id = u.id
     WHERE i.status = 'open'
     ORDER BY i.created_at DESC LIMIT 6"
);
$stmt->execute();
$recentItems = $stmt->fetchAll();

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Hero ───────────────────────────────────────────────────────── -->
<div class="hero-section text-white p-4 p-md-5 mb-5">
  <div class="row align-items-center g-4">

    <div class="col-lg-7">
      <span class="badge bg-white bg-opacity-25 text-white mb-3 px-3 py-2 rounded-pill">
        <i class="bi bi-stars me-1"></i>Smart Matching Enabled
      </span>
      <h1 class="display-5 fw-bold mb-3">
        Lost something?<br>Found something?
      </h1>
      <p class="lead text-white text-opacity-85 mb-4">
        Campus Lost &amp; Found connects students through intelligent keyword
        matching. Post an item and our system automatically finds the best candidates.
      </p>

      <!-- Search bar -->
      <form class="d-flex gap-2 flex-wrap" action="<?= BASE_URL ?>/search.php" method="get">
        <div class="flex-grow-1" style="min-width:200px;">
          <div class="input-group input-group-lg shadow-sm">
            <span class="input-group-text bg-white border-0">
              <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" class="form-control border-0 ps-0"
                   name="q" placeholder="Search — e.g. black calculator, blue bag…">
          </div>
        </div>
        <select class="form-select form-select-lg bg-white border-0 shadow-sm"
                name="type" style="max-width:150px;">
          <option value="">All types</option>
          <option value="lost">Lost</option>
          <option value="found">Found</option>
        </select>
        <button type="submit" class="btn btn-warning btn-lg fw-semibold shadow-sm px-4">
          Search
        </button>
      </form>
    </div>

    <!-- Hero illustration using Bootstrap Icons -->
    <div class="col-lg-5 d-none d-lg-flex justify-content-center">
      <div class="position-relative" style="width:280px;height:220px;">
        <!-- Floating icon cards -->
        <?php
        $heroItems = [
            ['bi-cpu',          'bg-primary',   'top:0;left:10px;'],
            ['bi-book',         'bg-warning',   'top:0;right:20px;'],
            ['bi-key',          'bg-danger',    'top:80px;left:0;'],
            ['bi-bag',          'bg-success',   'top:80px;right:0;'],
            ['bi-person-badge', 'bg-info',      'bottom:0;left:40px;'],
            ['bi-droplet',      'bg-secondary', 'bottom:0;right:40px;'],
        ];
        foreach ($heroItems as [$icon, $bg, $pos]):
        ?>
        <div class="position-absolute d-flex align-items-center justify-content-center
                    <?= $bg ?> bg-opacity-25 rounded-3 border border-white border-opacity-25"
             style="width:70px;height:70px;<?= $pos ?>backdrop-filter:blur(4px);">
          <i class="bi <?= $icon ?> text-white fs-2"></i>
        </div>
        <?php endforeach; ?>
        <!-- Center logo -->
        <div class="position-absolute top-50 start-50 translate-middle
                    bg-white bg-opacity-15 rounded-circle border border-white border-opacity-50
                    d-flex align-items-center justify-content-center"
             style="width:80px;height:80px;">
          <i class="bi bi-search-heart text-white" style="font-size:2.2rem;"></i>
        </div>
      </div>
    </div>

  </div><!-- /row -->
</div>

<!-- ── Stats ──────────────────────────────────────────────────────── -->
<div class="row g-3 mb-5">
  <?php
  $statDefs = [
      ['Open Items',    (int)($stats['open_count']     ?? 0), 'bi-check-circle-fill', '#22c55e', '#dcfce7'],
      ['Returned',      (int)($stats['returned_count'] ?? 0), 'bi-bag-check-fill',    '#8b5cf6', '#ede9fe'],
      ['Total Posted',  (int)($stats['total_count']    ?? 0), 'bi-box-seam-fill',     '#3b82f6', '#dbeafe'],
  ];
  foreach ($statDefs as [$label, $val, $icon, $iconColor, $bgColor]):
  ?>
  <div class="col-4">
    <div class="card card-shadow stat-card p-3 d-flex flex-row align-items-center gap-3">
      <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
           style="width:52px;height:52px;background:<?= $bgColor ?>;">
        <i class="bi <?= $icon ?> fs-4" style="color:<?= $iconColor ?>;"></i>
      </div>
      <div>
        <div class="h4 fw-bold mb-0"><?= number_format($val) ?></div>
        <div class="text-muted small"><?= $label ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Category tiles ─────────────────────────────────────────────── -->
<div class="mb-5">
  <h5 class="fw-bold mb-3">Browse by Category</h5>
  <div class="row row-cols-2 row-cols-sm-4 row-cols-lg-7 g-3">
    <?php
    $catColors = [
        'primary'   => ['#dbeafe','#1d4ed8'],
        'success'   => ['#dcfce7','#15803d'],
        'warning'   => ['#fef9c3','#a16207'],
        'info'      => ['#cffafe','#0e7490'],
        'secondary' => ['#f1f5f9','#475569'],
        'danger'    => ['#fee2e2','#b91c1c'],
        'dark'      => ['#e5e7eb','#374151'],
    ];
    foreach (CAT_META as $key => $cat):
        [$bg, $fg] = $catColors[$cat['color']];
    ?>
    <div class="col">
      <a href="<?= BASE_URL ?>/search.php?category=<?= $key ?>"
         class="cat-tile card card-shadow text-center text-decoration-none p-3 d-block h-100"
         style="background:<?= $bg ?>;">
        <i class="bi <?= $cat['icon'] ?> fs-2 mb-2" style="color:<?= $fg ?>;"></i>
        <div class="small fw-semibold" style="color:<?= $fg ?>;"><?= h($cat['label']) ?></div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Recent items ───────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Recent Open Items</h5>
  <a href="<?= BASE_URL ?>/search.php" class="btn btn-outline-primary btn-sm">
    View All <i class="bi bi-arrow-right ms-1"></i>
  </a>
</div>

<?php if (empty($recentItems)): ?>
<div class="card card-shadow text-center py-5 text-muted">
  <div><i class="bi bi-inbox fs-1 d-block mb-2"></i></div>
  <p>No items posted yet.</p>
  <?php if (is_logged_in()): ?>
    <a href="<?= BASE_URL ?>/user/post_item.php" class="btn btn-primary">Post an Item</a>
  <?php else: ?>
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary">Get Started</a>
  <?php endif; ?>
</div>
<?php else: ?>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
  <?php foreach ($recentItems as $item):
    $cat     = CAT_META[$item['category']] ?? CAT_META['other'];
    [$bg,$fg] = $catColors[$cat['color']];
  ?>
  <div class="col">
    <div class="card card-shadow item-card h-100">

      <?php if ($item['photo']): ?>
        <img src="<?= BASE_URL ?>/uploads/<?= h($item['photo']) ?>"
             class="card-img-top" alt="">
      <?php else: ?>
        <div class="card-img-placeholder" style="background:<?= $bg ?>;">
          <i class="bi <?= $cat['icon'] ?>" style="color:<?= $fg ?>;"></i>
        </div>
      <?php endif; ?>

      <div class="card-body pb-2">
        <div class="d-flex gap-1 flex-wrap mb-2">
          <span class="badge <?= $item['type']==='lost' ? 'badge-type-lost' : 'badge-type-found' ?> rounded-pill">
            <?= $item['type'] === 'lost' ? 'Lost' : 'Found' ?>
          </span>
          <span class="badge rounded-pill" style="background:<?= $bg ?>;color:<?= $fg ?>;">
            <i class="bi <?= $cat['icon'] ?> me-1"></i><?= h($cat['label']) ?>
          </span>
        </div>
        <h6 class="card-title fw-semibold mb-1"><?= h($item['title']) ?></h6>
        <p class="card-text small text-muted mb-1">
          <i class="bi bi-geo-alt me-1"></i><?= h($item['location'] ?? 'Location not specified') ?>
        </p>
        <p class="card-text small text-muted text-truncate">
          <?= h($item['description'] ?? '') ?>
        </p>
      </div>

      <div class="card-footer bg-transparent border-0 d-flex justify-content-between align-items-center pt-0">
        <small class="text-muted">
          <i class="bi bi-calendar3 me-1"></i><?= format_date($item['item_date']) ?>
        </small>
        <a href="<?= BASE_URL ?>/search.php?id=<?= (int)$item['id'] ?>"
           class="btn btn-sm rounded-pill px-3"
           style="background:<?= $bg ?>;color:<?= $fg ?>;border:none;">
          Details
        </a>
      </div>

    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── CTA (guests only) ─────────────────────────────────────────── -->
<?php if (!is_logged_in()): ?>
<div class="card card-shadow text-center p-5 mt-5"
     style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe);">
  <div class="mb-3">
    <span class="bg-primary bg-opacity-10 rounded-circle p-3 d-inline-block">
      <i class="bi bi-people-fill text-primary fs-2"></i>
    </span>
  </div>
  <h5 class="fw-bold">Join the Campus Community</h5>
  <p class="text-muted mb-4">
    Register with your college email to post items and get matched automatically.
  </p>
  <div class="d-flex gap-3 justify-content-center">
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary px-4">Register Free</a>
    <a href="<?= BASE_URL ?>/login.php"    class="btn btn-outline-primary px-4">Login</a>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
