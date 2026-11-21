<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$pdo = get_db();
$user = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $stmt = $pdo->prepare('SELECT password_hash FROM auth_users WHERE id = ?');
        $stmt->execute([(int)$user['id']]);
        $account = $stmt->fetch();

        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            $error = 'The current password is incorrect.';
        } elseif (strlen($newPassword) < 10) {
            $error = 'The new password must be at least 10 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'The new passwords do not match.';
        } else {
            $stmt = $pdo->prepare('UPDATE auth_users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
            $_SESSION['auth_user']['must_change_password'] = false;
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change password - EduPlan AI</title>
  <link rel="stylesheet" href="assets/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/style.css') ?>">
  <link rel="stylesheet" href="assets/tailwind-built.css?v=<?= (int)filemtime(__DIR__ . '/assets/tailwind-built.css') ?>">
</head>
<body class="flex min-h-screen items-center justify-center bg-black px-4 font-sans text-white">
  <form method="post" class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6">
    <?= csrf_field() ?>
    <h1 class="text-xl font-semibold">Change your password</h1>
    <p class="mt-1 text-sm text-zinc-500">Set a private password before continuing.</p>
    <?php if ($error): ?><p class="mt-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300"><?= e($error) ?></p><?php endif; ?>
    <label class="mt-5 block text-xs font-medium text-zinc-400">Current temporary password
      <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
    </label>
    <label class="mt-4 block text-xs font-medium text-zinc-400">New password
      <input type="password" name="new_password" required minlength="10" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
    </label>
    <label class="mt-4 block text-xs font-medium text-zinc-400">Confirm new password
      <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
    </label>
    <button type="submit" class="mt-5 w-full rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium">Change password</button>
    <button type="submit" form="logout-form" class="mt-3 w-full text-center text-xs text-zinc-500 hover:text-zinc-300">Sign out</button>
  </form>
  <form id="logout-form" method="post" action="logout.php" hidden><?= csrf_field() ?></form>
</body>
</html>
