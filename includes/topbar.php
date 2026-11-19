<header class="flex items-center justify-between border-b border-zinc-900 bg-black px-6 py-4">
  <button type="button" onclick="EduPlan.toggleSidebar()" title="Show/hide the sidebar" class="flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-800 text-zinc-400 hover:bg-zinc-900">
    <?= icon('menu', 'h-4 w-4') ?>
  </button>
  <div class="flex items-center gap-5">
    <a href="help.php#help" class="hidden text-sm text-zinc-400 hover:text-zinc-200 sm:inline"><?= e(ui_text('help')) ?></a>
    <a href="help.php#tips" class="hidden text-sm text-zinc-400 hover:text-zinc-200 sm:inline"><?= e(ui_text('tips')) ?></a>
    <button type="button" onclick="EduPlan.toggleTheme()" title="Toggle light / dark" class="flex h-8 w-8 items-center justify-center rounded-full border border-zinc-800 text-zinc-400 hover:border-orange-600 hover:text-orange-400">
      <?= icon('sun', 'h-4 w-4') ?>
    </button>
    <a href="settings.php" class="rounded-full border border-zinc-700 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-900"><?= e(ui_text('login')) ?></a>
    <a href="generator.php" class="rounded-full bg-gradient-to-r from-orange-500 to-amber-600 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
      <?= e(ui_text('start_free')) ?>
    </a>
  </div>
</header>
<div class="promo-banner border-b border-zinc-900 bg-gradient-to-r from-orange-950 via-amber-950 to-orange-950 px-6 py-2.5 text-center text-sm">
  <span class="font-medium text-white">EduPlan AI</span>
  <span class="text-zinc-400"> — AI-assisted curriculum planning </span>
  <a href="help.php" class="text-zinc-500 underline hover:text-zinc-300">· PHP Edition, <?= e(ui_text('more')) ?></a>
</div>
