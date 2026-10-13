<?php
// ── Session bootstrap ───────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // set true in production (HTTPS)
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function current_role(): string
{
    return $_SESSION['role'] ?? '';
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

/**
 * Redirect to login if not authenticated.
 * Optionally require a specific role ('admin').
 */
function require_login(string $role = ''): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if ($role === 'admin' && !is_admin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function require_admin(): void
{
    require_login('admin');
}

/**
 * Fetch the full user row for the currently logged-in user.
 * Returns null if not logged in or user no longer exists.
 */
function get_auth_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([current_user_id()]);
    return $stmt->fetch() ?: null;
}

/**
 * Check if the currently logged-in user is blocked.
 * Log out blocked users on every request.
 */
function abort_if_blocked(): void
{
    if (!is_logged_in()) {
        return;
    }
    $user = get_auth_user();
    if ($user && (int)$user['is_blocked'] === 1) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?err=blocked');
        exit;
    }
}

/**
 * Log a user in: set session, regenerate ID.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];
    // Rotate CSRF token on login
    unset($_SESSION['csrf_token']);
}

/**
 * Destroy the session and redirect to login.
 */
function logout(): void
{
    session_unset();
    session_destroy();

    // Kill cookie
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    header('Location: ' . BASE_URL . '/login.php?msg=loggedout');
    exit;
}
