<?php
require dirname(__DIR__) . '/config/db.php';
require dirname(__DIR__) . '/config/mail.php';

$pdo = get_db();

$r1 = $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch();
echo "Users  : " . $r1['c'] . PHP_EOL;

$r2 = $pdo->query('SELECT COUNT(*) AS c FROM items')->fetch();
echo "Items  : " . $r2['c'] . PHP_EOL;

$r3 = $pdo->query('SELECT COUNT(*) AS c FROM matches')->fetch();
echo "Matches: " . $r3['c'] . PHP_EOL;

$r4 = $pdo->query('SELECT COUNT(*) AS c FROM claims')->fetch();
echo "Claims : " . $r4['c'] . PHP_EOL;

echo "PHPMailer : " . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'OK' : 'MISSING') . PHP_EOL;
echo "BASE_URL  : " . BASE_URL . PHP_EOL;
echo "SMTP mode : " . (MAIL_ENABLED ? 'live' : 'log-only') . PHP_EOL;
