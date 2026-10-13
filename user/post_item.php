<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mail.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/match.php';

require_login();

$categories = [
    'id_card'     => 'ID Card',
    'electronics' => 'Electronics',
    'books'       => 'Books / Notes',
    'bottle'      => 'Water Bottle',
    'bag'         => 'Bag / Backpack',
    'keys'        => 'Keys',
    'other'       => 'Other',
];

$errors = [];
$input  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $input['type']            = $_POST['type']            ?? '';
    $input['category']        = $_POST['category']        ?? '';
    $input['title']           = trim($_POST['title']       ?? '');
    $input['description']     = trim($_POST['description'] ?? '');
    $input['location']        = trim($_POST['location']    ?? '');
    $input['item_date']       = $_POST['item_date']        ?? '';
    $input['verify_question'] = trim($_POST['verify_question'] ?? '');
    $input['verify_answer']   = trim($_POST['verify_answer']   ?? '');

    // ── Validation ────────────────────────────────────────────────
    if (!in_array($input['type'], ['lost', 'found'], true)) {
        $errors[] = 'Please select a type (Lost or Found).';
    }
    if (!array_key_exists($input['category'], $categories)) {
        $errors[] = 'Please select a valid category.';
    }
    if ($input['title'] === '') {
        $errors[] = 'Title is required.';
    } elseif (mb_strlen($input['title']) > 200) {
        $errors[] = 'Title must be under 200 characters.';
    }
    if ($input['item_date'] !== '' && strtotime($input['item_date']) === false) {
        $errors[] = 'Enter a valid date.';
    }
    if ($input['item_date'] !== '' && $input['item_date'] > date('Y-m-d')) {
        $errors[] = 'Date cannot be in the future.';
    }
    if ($input['type'] === 'found') {
        if ($input['verify_question'] === '') {
            $errors[] = 'A verification question is required for found items.';
        }
        if ($input['verify_answer'] === '') {
            $errors[] = 'A verification answer is required for found items.';
        }
    }

    // ── Photo upload ──────────────────────────────────────────────
    $photo = null;
    if (empty($errors)) {
        try {
            $photo = upload_photo('photo');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    // ── Insert ────────────────────────────────────────────────────
    if (empty($errors)) {
        $pdo  = get_db();
        $stmt = $pdo->prepare(
            "INSERT INTO items
               (user_id, type, category, title, description,
                location, item_date, photo, verify_question, verify_answer)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            current_user_id(),
            $input['type'],
            $input['category'],
            $input['title'],
            $input['description'] ?: null,
            $input['location']    ?: null,
            $input['item_date']   ?: null,
            $photo,
            $input['type'] === 'found' ? $input['verify_question'] : null,
            $input['type'] === 'found' ? $input['verify_answer']   : null,
        ]);
        $newItemId = (int)$pdo->lastInsertId();

        // Run smart matching — email failure must never block the user
        try {
            run_matching($newItemId);
        } catch (Throwable $e) {
            log_error('Matching failed for item #' . $newItemId . ': ' . $e->getMessage());
        }

        redirect(
            BASE_URL . '/user/my_items.php',
            'success',
            'Item posted successfully! We will notify you if a match is found.'
        );
    }
}

$pageTitle = 'Post an Item';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">

    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="card-title mb-1">
          <i class="bi bi-plus-circle me-1"></i> Post an Item
        </h4>
        <p class="text-muted small mb-4">
          Fill in as much detail as possible to improve matching accuracy.
        </p>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?>
            <div><?= h($e) ?></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>

          <!-- Type -->
          <div class="mb-4">
            <label class="form-label fw-semibold">Item Type *</label>
            <div class="d-flex gap-4">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="type"
                       id="typeLost" value="lost"
                       <?= ($input['type'] ?? '') === 'lost' ? 'checked' : '' ?> required>
                <label class="form-check-label" for="typeLost">
                  <span class="badge bg-danger me-1">Lost</span> I lost this item
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="type"
                       id="typeFound" value="found"
                       <?= ($input['type'] ?? '') === 'found' ? 'checked' : '' ?>>
                <label class="form-check-label" for="typeFound">
                  <span class="badge bg-primary me-1">Found</span> I found this item
                </label>
              </div>
            </div>
          </div>

          <!-- Category + Date -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="category" class="form-label fw-semibold">Category *</label>
              <select class="form-select" id="category" name="category" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $val => $label): ?>
                <option value="<?= h($val) ?>"
                  <?= ($input['category'] ?? '') === $val ? 'selected' : '' ?>>
                  <?= h($label) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="item_date" class="form-label fw-semibold">Date Lost / Found</label>
              <input type="date" class="form-control" id="item_date" name="item_date"
                     value="<?= h($input['item_date'] ?? '') ?>"
                     max="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <!-- Title -->
          <div class="mb-3">
            <label for="title" class="form-label fw-semibold">Title *</label>
            <input type="text" class="form-control" id="title" name="title"
                   value="<?= h($input['title'] ?? '') ?>"
                   placeholder="e.g. Black Casio scientific calculator"
                   maxlength="200" required>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="description" name="description"
                      rows="3"
                      placeholder="Colour, brand, distinctive markings, where exactly…"><?= h($input['description'] ?? '') ?></textarea>
            <div class="form-text">More detail = better matches.</div>
          </div>

          <!-- Location -->
          <div class="mb-3">
            <label for="location" class="form-label fw-semibold">Location</label>
            <input type="text" class="form-control" id="location" name="location"
                   value="<?= h($input['location'] ?? '') ?>"
                   placeholder="e.g. Exam Hall Block B, Library 2nd floor">
          </div>

          <!-- Photo -->
          <div class="mb-3">
            <label for="photo" class="form-label fw-semibold">
              Photo <span class="text-muted fw-normal">(optional, JPG/PNG/WebP, max 2 MB)</span>
            </label>
            <input type="file" class="form-control" id="photo" name="photo"
                   accept="image/jpeg,image/png,image/webp">
          </div>

          <!-- Verification (found items only) -->
          <div id="verifySection" class="border rounded p-3 bg-light mt-3
            <?= ($input['type'] ?? '') === 'found' ? '' : 'd-none' ?>">
            <h6 class="text-primary mb-2">
              <i class="bi bi-shield-check me-1"></i> Ownership Verification
              <span class="badge bg-warning text-dark ms-1 fw-normal">Required for Found</span>
            </h6>
            <p class="text-muted small mb-3">
              Set a question whose answer only the true owner would know.
              Your answer is kept confidential — the admin uses it to verify claims.
            </p>
            <div class="mb-2">
              <label for="verify_question" class="form-label fw-semibold">Question *</label>
              <input type="text" class="form-control" id="verify_question"
                     name="verify_question"
                     value="<?= h($input['verify_question'] ?? '') ?>"
                     placeholder="e.g. What is written on the back sticker?">
            </div>
            <div>
              <label for="verify_answer" class="form-label fw-semibold">Answer *</label>
              <input type="text" class="form-control" id="verify_answer"
                     name="verify_answer"
                     value="<?= h($input['verify_answer'] ?? '') ?>"
                     placeholder="The correct answer">
            </div>
          </div>

          <div class="d-grid mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="bi bi-send me-1"></i> Post Item
            </button>
          </div>
        </form>

      </div>
    </div><!-- /card -->

  </div>
</div>

<script>
(function () {
  const radios  = document.querySelectorAll('input[name="type"]');
  const section = document.getElementById('verifySection');
  const vq      = document.getElementById('verify_question');
  const va      = document.getElementById('verify_answer');

  function toggle() {
    const isFound = document.querySelector('input[name="type"]:checked')?.value === 'found';
    section.classList.toggle('d-none', !isFound);
    vq.required = isFound;
    va.required = isFound;
  }

  radios.forEach(r => r.addEventListener('change', toggle));
  toggle();
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
