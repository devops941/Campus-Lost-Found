<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mail.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_admin();

$pdo = get_db();

// ── Stats ────────────────────────────────────────────────────────
$stats = $pdo->query(
    "SELECT
        COUNT(*)                                  AS total_items,
        SUM(status = 'open')                      AS open_items,
        SUM(status = 'claimed')                   AS claimed_items,
        SUM(status = 'returned')                  AS returned_items
     FROM items"
)->fetch();

$matchCount   = (int)$pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$pendingClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'pending'")->fetchColumn();
$userCount    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// ── Last 7 days — items posted per day ───────────────────────────
$dailyRows = $pdo->query(
    "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
     FROM items
     WHERE created_at >= CURDATE() - INTERVAL 6 DAY
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
)->fetchAll();

// Fill gaps so Chart.js has all 7 days
$chartLabels = [];
$chartData   = [];
$dailyMap    = array_column($dailyRows, 'cnt', 'day');
for ($i = 6; $i >= 0; $i--) {
    $day           = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d M', strtotime($day));
    $chartData[]   = (int)($dailyMap[$day] ?? 0);
}

// ── Category breakdown ────────────────────────────────────────────
$catRows = $pdo->query(
    "SELECT category, COUNT(*) AS cnt FROM items GROUP BY category ORDER BY cnt DESC"
)->fetchAll();

// ── Recent pending claims ─────────────────────────────────────────
$recentClaims = $pdo->query(
    "SELECT c.id, c.status, c.created_at,
            u.name AS claimant_name,
            li.title AS lost_title
     FROM claims c
     JOIN users u  ON c.claimant_id   = u.id
     JOIN matches m ON c.match_id      = m.id
     JOIN items li  ON m.lost_item_id  = li.id
     WHERE c.status = 'pending'
     ORDER BY c.created_at DESC
     LIMIT 5"
)->fetchAll();

// ── Recently reported items ───────────────────────────────────────
$recentItems = $pdo->query(
    "SELECT i.id, i.type, i.title, i.category, i.status, i.created_at, u.name AS poster
     FROM items i JOIN users u ON i.user_id = u.id
     ORDER BY i.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle    = 'Admin Dashboard';
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="bi bi-speedometer2 me-1"></i> Admin Dashboard</h4>
  <span class="text-muted small"><?= date('d M Y, H:i') ?></span>
</div>

<!-- ── Stat cards ─────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <?php
  $statCards = [
      ['Total Items',     (int)$stats['total_items'],   'primary',   'bi-box-seam'],
      ['Open',            (int)$stats['open_items'],    'success',   'bi-check-circle'],
      ['Claimed',         (int)$stats['claimed_items'], 'warning',   'bi-hand-index-thumb'],
      ['Returned',        (int)$stats['returned_items'],'secondary', 'bi-bag-check'],
      ['Matches',         $matchCount,                  'info',      'bi-diagram-2'],
      ['Pending Claims',  $pendingClaims,               'danger',    'bi-hourglass-split'],
      ['Registered Users',$userCount,                   'dark',      'bi-people'],
  ];
  foreach ($statCards as [$label, $val, $color, $icon]):
  ?>
  <div class="col-6 col-md-3 col-lg-auto flex-grow-1">
    <div class="card text-white bg-<?= $color ?> shadow-sm h-100">
      <div class="card-body py-3 d-flex align-items-center gap-3">
        <i class="bi <?= $icon ?> fs-2 opacity-75"></i>
        <div>
          <div class="h4 mb-0 fw-bold"><?= number_format($val) ?></div>
          <div style="font-size:.75rem;"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Charts ────────────────────────────────────────────────────── -->
<div class="row g-4 mb-4">

  <div class="col-md-8">
    <div class="card shadow-sm h-100">
      <div class="card-header">Items Posted — Last 7 Days</div>
      <div class="card-body">
        <canvas id="dailyChart" height="100"></canvas>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card shadow-sm h-100">
      <div class="card-header">Items by Category</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="catChart"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- ── Pending claims ─────────────────────────────────────────────── -->
<div class="row g-4">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Pending Claims</span>
        <a href="<?= BASE_URL ?>/admin/claims.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentClaims)): ?>
          <p class="text-muted p-3 mb-0">No pending claims.</p>
        <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($recentClaims as $c): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <span class="fw-semibold small"><?= h($c['claimant_name']) ?></span>
              <div class="text-muted" style="font-size:.75rem;">
                on &ldquo;<?= h(mb_substr($c['lost_title'], 0, 40)) ?>&rdquo;
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <small class="text-muted"><?= format_datetime($c['created_at']) ?></small>
              <a href="<?= BASE_URL ?>/admin/claims.php?id=<?= (int)$c['id'] ?>"
                 class="btn btn-sm btn-warning">Review</a>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent Items</span>
        <a href="<?= BASE_URL ?>/admin/items.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($recentItems as $it): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <?= type_badge($it['type']) ?>
              <span class="small ms-1"><?= h(mb_substr($it['title'], 0, 35)) ?></span>
              <div class="text-muted" style="font-size:.75rem;"><?= h($it['poster']) ?></div>
            </div>
            <?= status_badge($it['status']) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
const dailyCtx = document.getElementById('dailyChart');
new Chart(dailyCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Items posted',
      data:  <?= json_encode($chartData) ?>,
      backgroundColor: 'rgba(13,110,253,.7)',
      borderRadius: 4,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales:  { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});

const catCtx = document.getElementById('catChart');
new Chart(catCtx, {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($catRows, 'category')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($catRows, 'cnt')) ?>,
      backgroundColor: [
        '#0d6efd','#198754','#ffc107','#dc3545',
        '#0dcaf0','#6c757d','#fd7e14'
      ],
    }]
  },
  options: {
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
  }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
