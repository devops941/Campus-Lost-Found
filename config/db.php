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
        // Never expose credentials or raw SQL errors to the browser.
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Database unavailable. Please try again later.');
    }

    return $pdo;
}
