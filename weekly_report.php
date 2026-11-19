<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

$pdo = get_db();
$settings = $pdo->query('SELECT email, weekly_report, school FROM settings WHERE id = 1')->fetch();
if (empty($settings['weekly_report']) || empty($settings['email'])) {
    echo "Weekly reports are disabled or no recipient email is configured.\n";
    exit(0);
}

$today = new DateTimeImmutable('today');
if ($today->format('N') !== '1') {
    echo "No weekly report: today is not Monday.\n";
    exit(0);
}

$recent = $pdo->query("SELECT COUNT(*) AS c FROM generated_items WHERE created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)")
    ->fetch()['c'];
$resources = $pdo->query("SELECT COUNT(*) AS c FROM resources WHERE created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)")
    ->fetch()['c'];
$users = $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
$school = trim($settings['school'] ?: 'Your school');
$body = "$school weekly curriculum report\n\n"
    . "Generated items in the last 7 days: $recent\n"
    . "Resources uploaded in the last 7 days: $resources\n"
    . "Directory users: $users\n";

queue_mail($pdo, $settings['email'], "$school — weekly EduPlan report", $body);
echo "Queued weekly report for {$settings['email']}.\n";
