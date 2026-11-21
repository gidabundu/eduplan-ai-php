<?php
require_once __DIR__ . '/db.php';
$pdo = get_db();
$sidebarSettings = $pdo->query('SELECT school FROM settings WHERE id = 1')->fetch();
$schoolName = trim($sidebarSettings['school'] ?? '') !== '' ? $sidebarSettings['school'] : 'No school name set';

$createItems = [
    ['id' => 'lesson-plan', 'label' => 'Lesson Plan', 'icon' => 'sparkles'],
    ['id' => 'assessment', 'label' => 'Assessment', 'icon' => 'clipboard-check'],
    ['id' => 'forecast', 'label' => 'Forecast', 'icon' => 'calendar'],
    ['id' => 'lesson-note', 'label' => 'Lesson Note', 'icon' => 'file-text'],
    ['id' => 'custom', 'label' => 'Custom Request', 'icon' => 'wand-2'],
];
$assetItems = [
    ['id' => 'syllabus', 'label' => ui_text('resource_library'), 'href' => 'syllabus.php', 'icon' => 'book-open'],
    ['id' => 'users', 'label' => 'User Directory', 'href' => 'users.php', 'icon' => 'users'],
];
$toolItems = [
    ['id' => 'teaching-sessions', 'label' => 'Teaching Sessions', 'href' => 'teaching_sessions.php', 'icon' => 'clipboard-check'],
    ['id' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'bar-chart-3'],
    ['id' => 'settings', 'label' => ui_text('settings'), 'href' => 'settings.php', 'icon' => 'settings'],
];
$siteLanguage = get_user_language();
$authUser = current_user();
if (($authUser['role'] ?? '') === 'Admin') {
    array_unshift($toolItems, ['id' => 'teacher-activity', 'label' => 'Teacher Activity', 'href' => 'teacher_activity.php', 'icon' => 'activity']);
}
$authName = trim($authUser['name'] ?? '') !== '' ? $authUser['name'] : 'Account';
?>
<aside id="sidebar" class="sticky top-0 flex h-screen w-60 flex-shrink-0 flex-col self-start overflow-y-auto border-r border-zinc-900 bg-black">
  <div class="flex items-center gap-2 px-5 py-5">
    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-orange-500 to-amber-600">
      <?= icon('graduation-cap', 'h-5 w-5 text-white') ?>
    </div>
    <div class="text-sm font-semibold text-white">EduPlan AI</div>
  </div>

  <div class="relative mx-3 mb-3">
    <button type="button" onclick="EduPlan.toggleWorkspaceMenu()" class="flex w-full items-center justify-between rounded-lg border border-zinc-800 px-3 py-2 text-left text-xs font-medium text-zinc-300 hover:border-zinc-700">
      <span class="truncate"><?= e($schoolName) ?></span>
      <?= icon('chevron-down', 'h-3.5 w-3.5 flex-shrink-0 text-zinc-500') ?>
    </button>
    <div id="workspaceMenu" class="hidden absolute left-0 right-0 top-full z-10 mt-1 rounded-lg border border-zinc-800 bg-zinc-900 p-1 shadow-lg">
      <a href="settings.php" class="block rounded-md px-2.5 py-2 text-xs text-zinc-300 hover:bg-zinc-800"><?= e(ui_text('school_name_hint')) ?></a>
    </div>
  </div>

  <nav class="flex-1 space-y-4 px-3 pb-4">
    <a href="index.php" class="flex w-full items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors <?= $activePage === 'home' ? 'border-orange-600 bg-zinc-900 text-white' : 'border-transparent text-zinc-500 hover:bg-zinc-900 hover:text-zinc-200' ?>">
      <?= icon('layout-dashboard', 'h-4 w-4 flex-shrink-0 ' . ($activePage === 'home' ? 'text-orange-500' : '')) ?>
      <span class="truncate"><?= e(ui_text('home')) ?></span>
    </a>

    <div>
      <div class="px-3 pb-1.5 text-xs font-semibold uppercase tracking-wider text-zinc-600"><?= e(ui_text('create')) ?></div>
      <div class="grid grid-cols-2 gap-1.5">
        <?php foreach ($createItems as $item): $active = $activePage === 'generator' && $activeTab === $item['id']; ?>
          <a href="generator.php?tab=<?= e($item['id']) ?>" class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-xs transition-colors <?= $active ? 'border-orange-600 bg-zinc-900 text-white' : 'border-transparent text-zinc-400 hover:bg-zinc-900 hover:text-zinc-200' ?>">
            <?= icon($item['icon'], 'h-3.5 w-3.5 flex-shrink-0 ' . ($active ? 'text-orange-500' : '')) ?>
            <span class="truncate"><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="px-3 pb-1.5 text-xs font-semibold uppercase tracking-wider text-zinc-600"><?= e(ui_text('assets')) ?></div>
      <div class="space-y-0.5">
        <?php foreach ($assetItems as $item): $active = $activePage === $item['id']; ?>
          <a href="<?= e($item['href']) ?>" class="flex w-full items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors <?= $active ? 'border-orange-600 bg-zinc-900 text-white' : 'border-transparent text-zinc-500 hover:bg-zinc-900 hover:text-zinc-200' ?>">
            <?= icon($item['icon'], 'h-4 w-4 flex-shrink-0 ' . ($active ? 'text-orange-500' : '')) ?>
            <span class="truncate"><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="px-3 pb-1.5 text-xs font-semibold uppercase tracking-wider text-zinc-600"><?= e(ui_text('tools')) ?></div>
      <div class="space-y-0.5">
        <?php foreach ($toolItems as $item): $active = $activePage === $item['id']; ?>
          <a href="<?= e($item['href']) ?>" class="flex w-full items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors <?= $active ? 'border-orange-600 bg-zinc-900 text-white' : 'border-transparent text-zinc-500 hover:bg-zinc-900 hover:text-zinc-200' ?>">
            <?= icon($item['icon'], 'h-4 w-4 flex-shrink-0 ' . ($active ? 'text-orange-500' : '')) ?>
            <span class="truncate"><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </nav>

  <div class="space-y-3 border-t border-zinc-900 px-3 py-4">
    <div class="flex items-center justify-between px-1">
      <span class="text-xs text-zinc-500"><?= e(ui_text('appearance')) ?></span>
      <button type="button" onclick="EduPlan.toggleTheme()" title="Toggle light / dark" class="flex h-7 w-7 items-center justify-center rounded-full border border-zinc-800 text-zinc-400 hover:border-orange-600 hover:text-orange-400">
        <?= icon('sun', 'h-3.5 w-3.5') ?>
      </button>
    </div>
    <div class="truncate px-1 text-xs text-zinc-400"><?= e($authName) ?></div>
    <form method="post" action="logout.php"><?= csrf_field() ?>
      <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-full border border-zinc-700 py-2 text-sm font-medium text-zinc-200 hover:bg-zinc-900">
        Sign out
      </button>
    </form>
  </div>
</aside>
