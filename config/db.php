<?php
// ── Database & application-wide constants ──────────────────────────
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT') ?: '3306');
define('DB_NAME',    getenv('DB_NAME') ?: 'lostfound');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Base URL (no trailing slash). On Render root domain, BASE_URL is set to empty string.
define('BASE_URL', getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/lost-found');

// College domain enforced during registration.
define('COLLEGE_EMAIL_DOMAIN', getenv('COLLEGE_EMAIL_DOMAIN') ?: 'college.edu');

// Absolute server path to the project root (set once, used everywhere).
define('ROOT_PATH', dirname(__DIR__));

// Composer autoloader (PHPMailer, etc.)
$_autoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($_autoload)) {
    require_once $_autoload;
}
unset($_autoload);

// Upload limits
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// ------------------------------------------------------------------
// Singleton PDO connection
// ------------------------------------------------------------------
function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn  = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(503);

        $showDebug = (getenv('DEBUG') === 'true') || isset($_GET['debug']);
        if ($showDebug) {
            echo "<div style='font-family:sans-serif;padding:30px;max-width:700px;margin:40px auto;border:1px solid #f87171;background:#fef2f2;border-radius:12px;color:#991b1b;'>";
            echo "<h2 style='margin-top:0;'>⚠️ Database Connection Error</h2>";
            echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<hr style='border:none;border-top:1px solid #fca5a5;'>";
            echo "<p><strong>Attempted Connection Settings:</strong></p>";
            echo "<ul>";
            echo "<li><strong>DB_HOST:</strong> " . htmlspecialchars(DB_HOST) . "</li>";
            echo "<li><strong>DB_PORT:</strong> " . htmlspecialchars(DB_PORT) . "</li>";
            echo "<li><strong>DB_NAME:</strong> " . htmlspecialchars(DB_NAME) . "</li>";
            echo "<li><strong>DB_USER:</strong> " . htmlspecialchars(DB_USER) . "</li>";
            echo "<li><strong>DB_PASS:</strong> " . (DB_PASS !== '' ? '•••••••• (set)' : '<span style="color:red;">(empty)</span>') . "</li>";
            echo "</ul>";
            echo "<p style='color:#7f1d1d;font-size:13px;'>Make sure these 5 variables match your database credentials in the Render Dashboard → Environment tab.</p>";
            echo "</div>";
            exit;
        }

        exit('Database unavailable. Please try again later. (Append <code>?debug=1</code> to your URL to see details)');
    }

    return $pdo;
}
