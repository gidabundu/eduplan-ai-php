<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

auth_start();
$pdo = get_db();
$error = '';
$passwordResetComplete = isset($_GET['password_reset']) && $_GET['password_reset'] === '1';
$setupMode = (int)$pdo->query('SELECT COUNT(*) AS c FROM auth_users')->fetch()['c'] === 0;

if (!empty($_SESSION['auth_user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Enter a valid email and password.';
        } elseif ($setupMode) {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                $error = 'Enter a name for the administrator.';
            } elseif (strlen($password) < 10) {
                $error = 'The administrator password must be at least 10 characters.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO auth_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $email, $hash, 'Admin']);
                $id = (int)$pdo->lastInsertId();
                $_SESSION['auth_user_id'] = $id;
                $_SESSION['auth_user'] = [
                    'id' => $id,
                    'name' => $name,
                    'email' => $email,
                    'role' => 'Admin',
                    'directory_user_id' => null,
                    'must_change_password' => false,
                ];
                session_regenerate_id(true);
                header('Location: index.php');
                exit;
            }
        } else {
            $stmt = $pdo->prepare('SELECT * FROM auth_users WHERE email = ?');
            $stmt->execute([$email]);
            $account = $stmt->fetch();
            if (!$account || !password_verify($password, $account['password_hash'])) {
                $error = 'Invalid email or password.';
            } else {
                $_SESSION['auth_user_id'] = (int)$account['id'];
                $_SESSION['auth_user'] = [
                    'id' => (int)$account['id'],
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                    'directory_user_id' => $account['directory_user_id'] !== null ? (int)$account['directory_user_id'] : null,
                    'must_change_password' => (bool)($account['must_change_password'] ?? false),
                ];
                session_regenerate_id(true);
                record_teacher_activity($pdo, $_SESSION['auth_user'], 'sign_in', 'Signed in');
                header('Location: ' . (!empty($account['must_change_password']) ? 'change_password.php' : 'index.php'));
                exit;
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
  <title>EduPlan AI - Sign in</title>
  <link rel="stylesheet" href="assets/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/style.css') ?>">
  <link rel="stylesheet" href="assets/tailwind-built.css?v=<?= (int)filemtime(__DIR__ . '/assets/tailwind-built.css') ?>">
</head>
<body class="flex min-h-screen items-center justify-center bg-black px-4 font-sans text-white">
  <form method="post" class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6">
    <?= csrf_field() ?>
    <div class="mb-6 flex flex-col items-center text-center">
      <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-amber-600">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7 text-white">
          <path d="m2 10 10-5 10 5-10 5-10-5Z"/>
          <path d="M6 12v5c3.5 2.7 8.5 2.7 12 0v-5"/>
          <path d="M22 10v6"/>
        </svg>
      </div>
      <p class="text-sm font-semibold tracking-wide text-white">EduPlan AI</p>
    </div>
    <h1 class="text-center text-xl font-semibold"><?= $setupMode ? 'Set up administrator' : 'Sign in' ?></h1>
    <p class="mt-1 text-center text-sm text-zinc-500"><?= $setupMode ? 'Create the first secure administrator account.' : 'Use your EduPlan account.' ?></p>
    <?php if ($passwordResetComplete): ?><p class="mt-4 rounded-lg border border-emerald-900 bg-emerald-950 px-3 py-2 text-sm text-emerald-300">Password reset complete. Sign in with your new password.</p><?php endif; ?>
    <?php if ($error): ?><p class="mt-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300"><?= e($error) ?></p><?php endif; ?>
    <?php if ($setupMode): ?>
      <label class="mt-5 block text-xs font-medium text-zinc-400">Name
        <input name="name" required autocomplete="name" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
      </label>
    <?php endif; ?>
    <label class="mt-4 block text-xs font-medium text-zinc-400">Email
      <input type="email" name="email" required autocomplete="username" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
    </label>
    <label class="mt-4 block text-xs font-medium text-zinc-400">Password
      <input type="password" name="password" required minlength="<?= $setupMode ? '10' : '1' ?>" autocomplete="<?= $setupMode ? 'new-password' : 'current-password' ?>" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
    </label>
    <button type="submit" class="mt-5 w-full rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium"><?= $setupMode ? 'Create administrator' : 'Sign in' ?></button>
    <?php if (!$setupMode): ?><a href="password_reset_request.php" class="mt-4 block text-center text-xs text-zinc-400 hover:text-zinc-200">Forgot your password?</a><?php endif; ?>
  </form>
</body>
</html>
