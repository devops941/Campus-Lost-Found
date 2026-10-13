<?php
/**
 * Shared header — include after config/db.php, auth.php, functions.php.
 * Set $pageTitle before including.
 */
$pageTitle = $pageTitle ?? 'Campus Lost & Found';
abort_if_blocked();

if (!defined('CAT_META')) {
    define('CAT_META', [
        'id_card'     => ['icon' => 'bi-person-badge', 'color' => 'primary',   'label' => 'ID Card'],
        'electronics' => ['icon' => 'bi-cpu',           'color' => 'success',   'label' => 'Electronics'],
        'books'       => ['icon' => 'bi-book',          'color' => 'warning',   'label' => 'Books'],
        'bottle'      => ['icon' => 'bi-droplet',       'color' => 'info',      'label' => 'Bottle'],
        'bag'         => ['icon' => 'bi-bag',           'color' => 'secondary', 'label' => 'Bag'],
        'keys'        => ['icon' => 'bi-key',           'color' => 'danger',    'label' => 'Keys'],
        'other'       => ['icon' => 'bi-three-dots',    'color' => 'dark',      'label' => 'Other'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle) ?> — Campus Lost &amp; Found</title>

  <!-- Local Bootstrap 5.3 CSS (no CDN dependency) -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
  <!-- Local Bootstrap Icons -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --brand-primary : #1a56db;
      --brand-dark    : #1e3a8a;
      --bg-page       : #f0f4f8;
    }
    html, body    { height: 100%; }
    body          { background: var(--bg-page); font-family: 'Segoe UI', sans-serif; }

    /* ── Navbar ──────────────────────────────────────────────────── */
    .navbar       { background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand-primary) 100%);
                    box-shadow: 0 2px 12px rgba(0,0,0,.25); }
    .navbar-brand { font-weight: 800; font-size: 1.2rem; }
    .nav-link     { font-weight: 500; font-size: .9rem; transition: opacity .15s; white-space: nowrap; }
    .nav-link:hover { opacity: .75; }

    /* Admin sub-bar — always visible strip below main navbar */
    .admin-bar    { background: #0f2356; border-top: 2px solid rgba(255,255,255,.15);
                    box-shadow: 0 3px 8px rgba(0,0,0,.3); }
    .admin-bar .nav-link {
      font-size: .82rem; padding: .35rem .9rem;
      color: rgba(255,255,255,.9) !important;
      border-radius: .35rem;
      transition: background .15s;
    }
    .admin-bar .nav-link:hover {
      background: rgba(255,255,255,.15);
      color: #fff !important;
      opacity: 1;
    }

    /* ── Cards ───────────────────────────────────────────────────── */
    .card         { border: none; border-radius: .75rem; }
    .card-shadow  { box-shadow: 0 4px 16px rgba(0,0,0,.08); }

    /* ── Hero ────────────────────────────────────────────────────── */
    .hero-section { background: linear-gradient(135deg, var(--brand-dark) 0%, #2563eb 60%, #3b82f6 100%);
                    border-radius: 1rem; }

    /* ── Category tiles ─────────────────────────────────────────── */
    .cat-tile     { border-radius: .65rem; transition: transform .18s, box-shadow .18s; }
    .cat-tile:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,.12); }

    /* ── Item cards ──────────────────────────────────────────────── */
    .item-card .card-img-top,
    .item-card .card-img-placeholder {
      height: 175px; object-fit: cover; border-radius: .75rem .75rem 0 0;
    }
    .item-card .card-img-placeholder {
      display: flex; align-items: center; justify-content: center;
      font-size: 3rem;
    }

    /* ── Misc ────────────────────────────────────────────────────── */
    .badge-type-lost  { background: #fee2e2; color: #b91c1c; }
    .badge-type-found { background: #dbeafe; color: #1d4ed8; }
    .score-bar        { height: 6px; border-radius: 3px; }
    .table-responsive .table { border-radius: .75rem; overflow: hidden; }
    footer            { font-size: .85rem; }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- ════════════════════════ MAIN NAVBAR ════════════════════════════ -->
<nav class="navbar navbar-expand-lg navbar-dark py-2">
  <div class="container-fluid px-4">

    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center gap-2"
       href="<?= BASE_URL ?>/index.php">
      <span class="bg-white bg-opacity-25 rounded-circle p-1 lh-1">
        <i class="bi bi-search-heart fs-5"></i>
      </span>
      Lost &amp; Found
    </a>

    <!-- Mobile toggle (works because Bootstrap JS is now local) -->
    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">

      <!-- Left nav links -->
      <ul class="navbar-nav me-auto gap-1 align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/index.php">
            <i class="bi bi-house me-1"></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/search.php">
            <i class="bi bi-grid me-1"></i>Browse
          </a>
        </li>
        <?php if (is_logged_in()): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/user/post_item.php">
            <i class="bi bi-plus-circle me-1"></i>Post Item
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/user/my_items.php">
            <i class="bi bi-list-ul me-1"></i>My Items
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/user/matches.php">
            <i class="bi bi-diagram-2 me-1"></i>Matches
          </a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- Right: user + logout -->
      <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
        <?php if (is_logged_in()): ?>
          <span class="text-white text-opacity-75 small d-none d-lg-inline">
            <i class="bi bi-person-circle me-1"></i><?= h($_SESSION['name'] ?? '') ?>
          </span>
          <a href="<?= BASE_URL ?>/logout.php"
             class="btn btn-sm btn-light text-primary fw-semibold px-3"
             onclick="return confirm('Log out?')">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
          </a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/login.php"  class="btn btn-sm btn-outline-light px-3">Login</a>
          <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-light text-primary fw-semibold px-3">Register</a>
        <?php endif; ?>
      </div>

    </div><!-- /collapse -->
  </div><!-- /container-fluid -->
</nav>

<?php if (is_admin()): ?>
<!-- ══════════════ ADMIN SUB-BAR (always visible, no JS needed) ════ -->
<nav class="navbar navbar-dark admin-bar py-0">
  <div class="container-fluid px-4">
    <span class="text-white text-opacity-50 small me-3 d-none d-md-inline">
      <i class="bi bi-shield-check me-1"></i>Admin Panel:
    </span>
    <ul class="navbar-nav flex-row gap-1 flex-wrap">
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">
          <i class="bi bi-speedometer2 me-1"></i>Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/items.php">
          <i class="bi bi-box-seam me-1"></i>Items
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/matches.php">
          <i class="bi bi-diagram-2 me-1"></i>Matches
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/claims.php">
          <i class="bi bi-hand-index-thumb me-1"></i>Claims
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/users.php">
          <i class="bi bi-people me-1"></i>Users
        </a>
      </li>
    </ul>
  </div>
</nav>
<?php endif; ?>

<!-- ════════════════════════ PAGE BODY ══════════════════════════════ -->
<div class="container py-4 flex-grow-1 d-flex flex-column">
<?php render_flash(); ?>
