<?php
require_once __DIR__ . '/includes/db.php';
$pdo = get_db();
$authUser = current_user();
$isAdmin = ($authUser['role'] ?? '') === 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAdmin) {
        require_admin();
    } else {
        require_permission('edit_profile');
    }
    if (!csrf_validate()) {
        flash_set('Could not save settings: invalid session.');
        header('Location: settings.php');
        exit;
    }
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('Could not save profile: enter a name and valid email.');
    } else {
        $emailCheck = $pdo->prepare('SELECT id FROM auth_users WHERE email = ? AND id <> ?');
        $emailCheck->execute([$email, $authUser['id']]);
        if ($emailCheck->fetch()) {
            flash_set('That email is already used by another login account.');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE auth_users SET name = ?, email = ? WHERE id = ?')
                ->execute([$name, $email, $authUser['id']]);
            if ($isAdmin) {
                $school = trim($_POST['school'] ?? '');
                $language = in_array($_POST['language'] ?? 'English', ['English', 'Krio'], true) ? $_POST['language'] : 'English';
                $aiSuggestions = isset($_POST['ai_suggestions']) ? 1 : 0;
                $notifications = isset($_POST['notifications']) ? 1 : 0;
                $weeklyReport = isset($_POST['weekly_report']) ? 1 : 0;
                $stmt = $pdo->prepare('UPDATE settings SET name = ?, email = ?, school = ?, language = ?, ai_suggestions = ?, notifications = ?, weekly_report = ? WHERE id = 1');
                $stmt->execute([$name, $email, $school, $language, $aiSuggestions, $notifications, $weeklyReport]);
            } else {
                $directoryUser = $pdo->prepare('SELECT directory_user_id FROM auth_users WHERE id = ?');
                $directoryUser->execute([$authUser['id']]);
                $directoryUserId = $directoryUser->fetchColumn();
                if ($directoryUserId !== false && $directoryUserId !== null) {
                    $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                        ->execute([$name, $email, (int)$directoryUserId]);
                }
            }
            $pdo->commit();
            $_SESSION['auth_user']['name'] = $name;
            $_SESSION['auth_user']['email'] = $email;
            flash_set('Profile saved.');
        }
    }
    header('Location: settings.php');
    exit;
}

$settings = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
$profileName = $authUser['name'] ?? '';
$profileEmail = $authUser['email'] ?? '';

$activePage = 'settings';
$pageTitle = 'EduPlan AI — Settings';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-xl space-y-6 p-6">
  <div>
    <h1 class="text-lg font-semibold text-white"><?= e(ui_text('settings')) ?></h1>
    <p class="text-sm text-zinc-500"><?= e(ui_text('manage_profile')) ?></p>
  </div>

  <form method="post" class="space-y-6">
    <?= csrf_field() ?>
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
      <h2 class="text-sm font-semibold text-white"><?= e(ui_text('profile')) ?></h2>
      <div class="mt-3 grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-medium text-zinc-400"><?= e(ui_text('name')) ?></label>
          <input name="name" value="<?= e($profileName) ?>" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400"><?= e(ui_text('email')) ?></label>
          <input type="email" name="email" value="<?= e($profileEmail) ?>" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400"><?= e(ui_text('role')) ?></label>
          <input value="<?= $isAdmin ? 'Administrator' : 'Teacher' ?>" disabled class="mt-1 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-600">
        </div>
        <?php if ($isAdmin): ?>
        <div>
          <label class="text-xs font-medium text-zinc-400"><?= e(ui_text('school')) ?></label>
          <input name="school" value="<?= e($settings['school']) ?>" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div class="col-span-2">
          <label class="text-xs font-medium text-zinc-400"><?= e(ui_text('language')) ?></label>
          <select name="language" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
            <option value="English" <?= (($settings['language'] ?? 'English') === 'English' ? 'selected' : '') ?>>English</option>
            <option value="Krio" <?= (($settings['language'] ?? 'English') === 'Krio' ? 'selected' : '') ?>>Krio</option>
          </select>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
      <h2 class="text-sm font-semibold text-white"><?= e(ui_text('preferences')) ?></h2>
      <div class="mt-3 space-y-4">
        <label class="flex items-center justify-between gap-4">
          <span><span class="block text-sm text-zinc-200">Email notifications</span><span class="block text-xs text-zinc-500">Queue notices about resource uploads and approvals.</span></span>
          <input type="checkbox" name="notifications" <?= $settings['notifications'] ? 'checked' : '' ?> class="h-5 w-5 rounded border-zinc-700 bg-zinc-900">
        </label>
        <label class="flex items-center justify-between gap-4">
          <span>
            <span class="block text-sm text-zinc-200">AI auto-suggestions</span>
            <span class="block text-xs text-zinc-500">Let EduPlan AI suggest topics while planning lessons.</span>
          </span>
          <input type="checkbox" name="ai_suggestions" <?= $settings['ai_suggestions'] ? 'checked' : '' ?> class="h-5 w-5 rounded border-zinc-700 bg-zinc-900">
        </label>
        <label class="flex items-center justify-between gap-4">
          <span><span class="block text-sm text-zinc-200">Weekly summary report</span><span class="block text-xs text-zinc-500">Queue a curriculum digest every Monday.</span></span>
          <input type="checkbox" name="weekly_report" <?= $settings['weekly_report'] ? 'checked' : '' ?> class="h-5 w-5 rounded border-zinc-700 bg-zinc-900">
        </label>
      </div>
    </div>
    <?php endif; ?>

    <button type="submit" class="flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-black hover:bg-zinc-200">
      <?= icon('save', 'h-4 w-4') ?> <?= e(ui_text('save_changes')) ?>
    </button>
  </form>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="text-sm font-semibold text-white"><?= e(ui_text('ai_configuration')) ?></h2>
    <p class="mt-1 text-xs text-zinc-500">
      Provider and model settings are in <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">config.php</code> and the server's local configuration.
      Gemini is preferred when its key is configured; Anthropic is optional.
      Set <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">GOOGLE_API_KEY</code> or
      <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">ANTHROPIC_API_KEY</code> as an environment variable
      or paste it into the matching field in the Git-ignored local file
      <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">config.local.php</code>.
      API credentials are managed by the server administrator.
      Status: <?php
        $anthropicConfigured = defined('ANTHROPIC_API_KEY') && !str_contains(ANTHROPIC_API_KEY, 'REPLACE-ME') && ANTHROPIC_API_KEY !== '';
        $googleConfigured = defined('GOOGLE_API_KEY') && !str_contains(GOOGLE_API_KEY, 'REPLACE-ME') && GOOGLE_API_KEY !== '';
        $aiProvider = strtolower(trim((string)(defined('AI_PROVIDER') ? AI_PROVIDER : '')));
        if (!in_array($aiProvider, ['google', 'anthropic'], true)) {
            $aiProvider = $googleConfigured ? 'google' : ($anthropicConfigured ? 'anthropic' : 'google');
        }
        if ($aiProvider === 'google' && $googleConfigured) {
            echo '<span class="text-emerald-400">Gemini key configured (primary)</span>';
        } elseif ($aiProvider === 'anthropic' && $anthropicConfigured) {
            echo '<span class="text-emerald-400">Anthropic key configured</span>';
        } else {
            echo '<span class="text-amber-400">No key configured for the selected provider</span>';
        }
      ?>
    </p>
  </div>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="text-sm font-semibold text-white">Email configuration</h2>
    <p class="mt-1 text-xs text-zinc-500">
      SMTP is configured through environment variables:
      <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">SMTP_HOST</code>,
      <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">SMTP_PORT</code>,
      <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">SMTP_USER</code>,
      <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">SMTP_PASS</code>,
      <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">SMTP_ENCRYPTION</code>,
      <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">SMTP_FROM_EMAIL</code>.
      Run <code class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-300">run_notifications.ps1</code> manually or schedule it daily.
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
