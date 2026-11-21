<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/mailer.php';

auth_start();
$pdo = get_db();
$error = '';
$notice = '';

if (!empty($_SESSION['auth_user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif (SMTP_HOST === '') {
        $error = 'Password recovery email is not configured. Please contact your administrator.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $remoteIp = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);
        $ipHash = hash('sha256', $remoteIp !== false ? $remoteIp : 'unknown');

        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM password_reset_requests WHERE created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)');
            $pdo->exec('DELETE FROM password_reset_tokens WHERE created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)');
            $rateStmt = $pdo->prepare('SELECT COUNT(*) FROM password_reset_requests WHERE ip_hash = ? AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)');
            $rateStmt->execute([$ipHash]);
            $requests = (int)$rateStmt->fetchColumn();

            if ($requests < 5) {
                $pdo->prepare('INSERT INTO password_reset_requests (ip_hash) VALUES (?)')->execute([$ipHash]);

                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $accountStmt = $pdo->prepare('SELECT id, email FROM auth_users WHERE email = ? LIMIT 1');
                    $accountStmt->execute([$email]);
                    $account = $accountStmt->fetch();
                    if ($account) {
                        $userId = (int)$account['id'];
                        $accountRateStmt = $pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = ? AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)');
                        $accountRateStmt->execute([$userId]);
                        if ((int)$accountRateStmt->fetchColumn() < 3) {
                            $token = bin2hex(random_bytes(32));
                            $tokenHash = hash('sha256', $token);
                            $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
                            $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR))')
                                ->execute([$userId, $tokenHash]);

                            $resetUrl = APP_URL . '/reset_password.php?token=' . rawurlencode($token);
                            $body = "A password reset was requested for your EduPlan AI account.\n\n"
                                . "Use this link within one hour to choose a new password:\n"
                                . $resetUrl . "\n\n"
                                . "If you did not request this, you can ignore this message.";
                            queue_mail($pdo, $account['email'], 'EduPlan AI password reset', $body);
                        }
                    }
                }
            }

            $pdo->commit();
            $notice = 'If an account exists for that email, password reset instructions will be emailed shortly.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="no-referrer">
  <title>Reset password - EduPlan AI</title>
  <link rel="stylesheet" href="assets/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/style.css') ?>">
  <link rel="stylesheet" href="assets/tailwind-built.css?v=<?= (int)filemtime(__DIR__ . '/assets/tailwind-built.css') ?>">
</head>
<body class="flex min-h-screen items-center justify-center bg-black px-4 font-sans text-white">
  <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6">
    <h1 class="text-xl font-semibold">Reset your password</h1>
    <p class="mt-1 text-sm text-zinc-500">Enter your account email and we’ll send reset instructions if an account matches.</p>
    <?php if ($error): ?><p class="mt-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300"><?= e($error) ?></p><?php endif; ?>
    <?php if ($notice): ?><p class="mt-4 rounded-lg border border-emerald-900 bg-emerald-950 px-3 py-2 text-sm text-emerald-300"><?= e($notice) ?></p><?php endif; ?>
    <?php if (SMTP_HOST === ''): ?>
      <p class="mt-4 rounded-lg border border-amber-900 bg-amber-950 px-3 py-2 text-sm text-amber-300">Self-service password recovery is unavailable until email delivery is configured. Please contact your administrator.</p>
    <?php else: ?>
      <form method="post" class="mt-5 space-y-4">
        <?= csrf_field() ?>
        <label class="block text-xs font-medium text-zinc-400">Email
          <input type="email" name="email" required autocomplete="email" maxlength="190" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
        </label>
        <button type="submit" class="w-full rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium">Send reset instructions</button>
      </form>
    <?php endif; ?>
    <a href="login.php" class="mt-5 block text-center text-sm text-zinc-400 hover:text-zinc-200">Back to sign in</a>
  </div>
</body>
</html>
