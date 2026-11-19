<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

$pdo = get_db();
if (SMTP_HOST === '') {
    echo "SMTP is not configured. Set SMTP_HOST and SMTP credentials.\n";
    exit(0);
}

$stmt = $pdo->prepare('SELECT * FROM mail_queue WHERE delivered_at IS NULL AND attempts < 5 ORDER BY id ASC LIMIT 20');
$stmt->execute();
$sent = 0;
foreach ($stmt->fetchAll() as $mail) {
    $updateAttempts = $pdo->prepare('UPDATE mail_queue SET attempts = attempts + 1, error_message = NULL WHERE id = ?');
    $updateAttempts->execute([(int)$mail['id']]);
    try {
        smtp_send($mail['recipient'], $mail['subject'], $mail['body']);
        $pdo->prepare('UPDATE mail_queue SET delivered_at = CURRENT_TIMESTAMP, error_message = NULL WHERE id = ?')
            ->execute([(int)$mail['id']]);
        $sent++;
    } catch (Throwable $e) {
        $pdo->prepare('UPDATE mail_queue SET error_message = ? WHERE id = ?')
            ->execute([substr($e->getMessage(), 0, 255), (int)$mail['id']]);
        fwrite(STDERR, 'Mail #' . $mail['id'] . ': ' . $e->getMessage() . "\n");
    }
}
echo "Processed mail queue: $sent delivered.\n";
