<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

auth_start();
$pdo = get_db();
$error = '';
$token = is_string($_POST['token'] ?? null) ? trim($_POST['token']) : (is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '');
$tokenHash = preg_match('/\A[a-f0-9]{64}\z/', $token) ? hash('sha256', $token) : '';
$tokenStmt = $pdo->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP LIMIT 1');
$tokenStmt->execute([$tokenHash]);
$resetToken = $tokenHash !== '' ? $tokenStmt->fetch() : false;
$completed = isset($_GET['complete']) && $_GET['complete'] === '1';

if (!empty($_SESSION['auth_user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$completed) {
    if (!csrf_validate()) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif (!$resetToken) {
        $error = 'This reset link is invalid or has expired. Request a new one.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (strlen($password) < 10) {
            $error = 'Your new password must be at least 10 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The new passwords do not match.';
        } else {
            $pdo->beginTransaction();
            try {
                $lockStmt = $pdo->prepare('SELECT id, user_id FROM password_reset_tokens WHERE id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP FOR UPDATE');
                $lockStmt->execute([(int)$resetToken['id'], $tokenHash]);
                $lockedToken = $lockStmt->fetch();
                if (!$lockedToken) {
                    $pdo->rollBack();
                    $error = 'This reset link is invalid or has expired. Request a new one.';
                } else {
                    $userId = (int)$lockedToken['user_id'];
                    $userUpdate = $pdo->prepare('UPDATE auth_users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
                    $userUpdate->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
                    if ($userUpdate->rowCount() !== 1) {
                        $pdo->rollBack();
                        $error = 'This reset link is invalid or has expired. Request a new one.';
                    } else {
                        $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$lockedToken['id']]);
                        $pdo->commit();
                        header('Location: login.php?password_reset=1');
                        exit;
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
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
  <title>Choose a new password - EduPlan AI</title>
  <link rel="stylesheet" href="assets/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/style.css') ?>">
  <link rel="stylesheet" href="assets/tailwind-built.css?v=<?= (int)filemtime(__DIR__ . '/assets/tailwind-built.css') ?>">
</head>
<body class="flex min-h-screen items-center justify-center bg-black px-4 font-sans text-white">
  <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6">
    <?php if ($completed): ?>
      <h1 class="text-xl font-semibold">Password reset complete</h1>
      <p class="mt-2 text-sm text-zinc-400">You can now sign in with your new password.</p>
      <a href="login.php" class="mt-5 block w-full rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-center text-sm font-medium">Sign in</a>
    <?php elseif (!$resetToken): ?>
      <h1 class="text-xl font-semibold">Reset link unavailable</h1>
      <p class="mt-2 text-sm text-zinc-400">This reset link is invalid or has expired. Request a new one to continue.</p>
      <a href="password_reset_request.php" class="mt-5 block w-full rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-center text-sm font-medium">Request a new link</a>
    <?php else: ?>
      <h1 class="text-xl font-semibold">Choose a new password</h1>
      <p class="mt-1 text-sm text-zinc-500">Your password must be at least 10 characters.</p>
      <?php if ($error): ?><p class="mt-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300"><?= e($error) ?></p><?php endif; ?>
      <form method="post" class="mt-5 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="block text-xs font-medium text-zinc-400">New password
          <input type="password" name="password" required minlength="10" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
        </label>
        <label class="block text-xs font-medium text-zinc-400">Confirm new password
          <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
        </label>
        <button type="submit" class="w-full rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium">Set new password</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
