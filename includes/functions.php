<?php
// ── General utility functions ───────────────────────────────────────

// ------------------------------------------------------------------
// Output safety
// ------------------------------------------------------------------

/** HTML-escape a value for safe output. */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ------------------------------------------------------------------
// CSRF protection
// ------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Emit a hidden CSRF input field. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/**
 * Validate the submitted CSRF token.
 * Dies with 403 on mismatch — every POST must call this.
 */
function csrf_check(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Invalid request token. Go back and try again.');
    }
}

// ------------------------------------------------------------------
// Flash messages
// ------------------------------------------------------------------

/**
 * Set a one-shot flash message.
 * @param string $type  Bootstrap alert type: success | danger | warning | info
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Return the pending flash message (array) and clear it.
 * Returns null if none queued.
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** Render a Bootstrap alert from the current flash, if any. */
function render_flash(): void
{
    $f = get_flash();
    if ($f === null) {
        return;
    }
    echo '<div class="alert alert-' . h($f['type']) . ' alert-dismissible fade show" role="alert">';
    echo h($f['message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
}

// ------------------------------------------------------------------
// Navigation helpers
// ------------------------------------------------------------------

function redirect(string $url, string $flashType = '', string $flashMsg = ''): never
{
    if ($flashType && $flashMsg) {
        flash($flashType, $flashMsg);
    }
    header('Location: ' . $url);
    exit;
}

// ------------------------------------------------------------------
// Date formatting
// ------------------------------------------------------------------

function format_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts !== false ? date('d M Y', $ts) : h($date);
}

function format_datetime(?string $dt): string
{
    if ($dt === null || $dt === '') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts !== false ? date('d M Y, H:i', $ts) : h($dt);
}

// ------------------------------------------------------------------
// Status badge
// ------------------------------------------------------------------

function status_badge(string $status): string
{
    $map = [
        'open'     => 'success',
        'claimed'  => 'warning',
        'returned' => 'secondary',
    ];
    $cls = $map[$status] ?? 'light';
    return '<span class="badge bg-' . $cls . '">' . h(ucfirst($status)) . '</span>';
}

function type_badge(string $type): string
{
    $cls = $type === 'lost' ? 'danger' : 'primary';
    return '<span class="badge bg-' . $cls . '">' . h(ucfirst($type)) . '</span>';
}

// ------------------------------------------------------------------
// Photo upload
// ------------------------------------------------------------------

/**
 * Handle a photo upload from $_FILES[$field].
 * Returns the stored filename (relative to uploads/) on success.
 * Returns null if no file was submitted.
 * Throws RuntimeException on validation failure.
 *
 * @throws RuntimeException
 */
function upload_photo(string $field): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error (code ' . (int)$file['error'] . ').');
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Photo must be under 2 MB.');
    }

    // Verify real MIME — never trust client-supplied type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        throw new RuntimeException('Only JPG, PNG, and WebP images are allowed.');
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $ext      = $extMap[$mime];
    $filename = uniqid('img_', true) . '.' . $ext;
    $dest     = ROOT_PATH . '/uploads/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded photo.');
    }

    return $filename;
}

// ------------------------------------------------------------------
// Email (PHPMailer wrapper — Stage 2 match.php calls this)
// ------------------------------------------------------------------

/**
 * Send an email via PHPMailer.  Email failure MUST NEVER break the
 * calling flow: exceptions are caught, logged, and silently swallowed.
 *
 * @param string|string[] $to  Single address or [email => name, ...]
 */
function send_mail(string $subject, string $body, $to): bool
{
    $logPath = defined('MAIL_LOG_PATH')
        ? MAIL_LOG_PATH
        : ROOT_PATH . '/logs/mail_errors.log';

    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        // Log-only mode
        $toStr = is_array($to) ? implode(', ', array_keys($to)) : $to;
        $entry = date('Y-m-d H:i:s') . " [MAIL-SKIP] To: $toStr | Subject: $subject\n";
        @file_put_contents($logPath, $entry, FILE_APPEND);
        return false;
    }

    try {
        // PHPMailer loaded via Composer autoload (require at bootstrap)
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        if (is_array($to)) {
            foreach ($to as $addr => $name) {
                $mail->addAddress($addr, $name);
            }
        } else {
            $mail->addAddress($to);
        }

        $mail->Subject = $subject;
        $mail->Body    = nl2br(h($body));
        $mail->AltBody = $body;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        $entry = date('Y-m-d H:i:s') . " [MAIL-ERR] " . $e->getMessage() . "\n";
        @file_put_contents($logPath, $entry, FILE_APPEND);
        return false;
    }
}

// ------------------------------------------------------------------
// Pagination
// ------------------------------------------------------------------

/**
 * Return [offset, limit, currentPage, totalPages].
 *
 * @param int $totalRows
 * @param int $perPage
 * @return array{offset:int, limit:int, page:int, totalPages:int}
 */
function paginate(int $totalRows, int $perPage = 10): array
{
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    return [
        'offset'     => $offset,
        'limit'      => $perPage,
        'page'       => $page,
        'totalPages' => $totalPages,
    ];
}

/**
 * Render Bootstrap 5 pagination links.
 * $queryParams: existing GET params to preserve (type, category, etc.).
 */
function render_pagination(int $page, int $totalPages, array $queryParams = []): void
{
    if ($totalPages <= 1) {
        return;
    }
    $base = '?' . http_build_query(array_merge($queryParams, ['page' => '__P__']));

    echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $url    = str_replace('__P__', $i, $base);
        $active = $i === $page ? ' active' : '';
        echo "<li class=\"page-item$active\"><a class=\"page-link\" href=\"$url\">$i</a></li>";
    }
    echo '</ul></nav>';
}

// ------------------------------------------------------------------
// Error logging
// ------------------------------------------------------------------

function log_error(string $message, string $context = ''): void
{
    $line = date('Y-m-d H:i:s') . " [ERROR] $message" . ($context ? " | $context" : '') . "\n";
    @file_put_contents(ROOT_PATH . '/logs/app_errors.log', $line, FILE_APPEND);
    error_log($message);
}

// ------------------------------------------------------------------
// Ownership guard
// ------------------------------------------------------------------

/**
 * Ensure the currently logged-in user owns the given item.
 * Admins bypass this check.
 */
function assert_owns_item(array $item): void
{
    if (is_admin()) {
        return;
    }
    if ((int)$item['user_id'] !== current_user_id()) {
        http_response_code(403);
        exit('You do not have permission to access this item.');
    }
}
