<?php
// ── PHPMailer / SMTP configuration ─────────────────────────────────
// Set MAIL_ENABLED to true and fill in your Gmail app-password to
// enable email notifications.  When false, all mail is logged only.

define('MAIL_ENABLED',   filter_var(getenv('MAIL_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN));
define('MAIL_HOST',      getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME',  getenv('MAIL_USERNAME') ?: 'your_email@gmail.com');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD') ?: 'your_app_password_here');
define('MAIL_FROM',      getenv('MAIL_FROM') ?: 'your_email@gmail.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Campus Lost & Found');

// Path to the mail error log (relative to project root).
define('MAIL_LOG_PATH',  ROOT_PATH . '/logs/mail_errors.log');
