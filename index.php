<?php
require_once __DIR__ . '/includes/db.php';

$heroTools = [
    ['id' => 'lesson-plan', 'label' => 'Lesson Plan', 'icon' => 'sparkles', 'badge' => 'NEW', 'desc' => 'Turn a topic into a full lesson plan.', 'gradient' => 'from-orange-600 to-amber-700'],
    ['id' => 'assessment', 'label' => 'Assessment', 'icon' => 'clipboard-check', 'desc' => 'Generate quizzes and tests in minutes.', 'gradient' => 'from-violet-600 to-indigo-700'],
    ['id' => 'forecast', 'label' => 'Forecast', 'icon' => 'calendar', 'desc' => "Map out a full term's teaching plan.", 'gradient' => 'from-blue-600 to-cyan-700'],
    ['id' => 'lesson-note', 'label' => 'Lesson Note', 'icon' => 'file-text', 'desc' => 'Write detailed, student-ready notes.', 'gradient' => 'from-emerald-500 to-green-700'],
];
$heroPills = array_merge($heroTools, [
    ['id' => 'custom', 'label' => 'Custom Request', 'icon' => 'wand-2'],
]);
$popularTools = [
    ['label' => 'Resource Library', 'icon' => 'book-open', 'href' => 'syllabus.php', 'gradient' => 'from-orange-600 to-amber-600'],
    ['label' => 'User Directory', 'icon' => 'users', 'href' => 'users.php', 'gradient' => 'from-blue-500 to-cyan-600'],
    ['label' => 'Reports', 'icon' => 'bar-chart-3', 'href' => 'reports.php', 'gradient' => 'from-emerald-500 to-green-600'],
    ['label' => 'Settings', 'icon' => 'settings', 'href' => 'settings.php', 'gradient' => 'from-amber-500 to-orange-600'],
];

$activePage = 'home';
$pageTitle = 'EduPlan AI — Home';
include __DIR__ . '/includes/header.php';
?>
<div class="p-6 sm:p-10">
  <div class="mx-auto max-w-5xl">
    <h1 class="text-center text-3xl font-extrabold tracking-tight text-zinc-300 sm:text-5xl">
      <?= e(ui_text('what_plan_today')) ?>
    </h1>
    <p class="mx-auto mt-3 max-w-xl text-center text-sm text-zinc-500">
      Upload books and syllabus to your <a href="syllabus.php" class="text-orange-400 hover:text-orange-300"><?= e(ui_text('resource_library')) ?></a> — every tool below can read from them, search the web for more, or just build from scratch.
    </p>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
      <?php foreach ($heroPills as $t): ?>
        <a href="generator.php?tab=<?= e($t['id']) ?>" class="flex items-center gap-2 rounded-full border border-zinc-800 bg-zinc-900 px-4 py-2.5 text-sm font-medium text-zinc-200 hover:border-orange-600">
          <?= icon($t['icon'], 'h-4 w-4') ?>
          <?= e($t['label']) ?>
          <?php if (!empty($t['badge'])): ?>
            <span class="rounded-full bg-emerald-500 px-1.5 py-0.5 text-xs font-bold leading-none text-black"><?= e($t['badge']) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($heroTools as $t): ?>
        <a href="generator.php?tab=<?= e($t['id']) ?>" class="group relative flex h-40 flex-col justify-end overflow-hidden rounded-2xl bg-gradient-to-br <?= $t['gradient'] ?> p-4 text-left">
          <?= icon($t['icon'], 'absolute right-3 top-3 h-5 w-5 text-white opacity-40') ?>
          <div class="text-sm font-semibold text-white"><?= e($t['label']) ?> Generator</div>
          <div class="mt-1 text-xs text-white opacity-80"><?= e($t['desc']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="mt-12">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-white"><?= e(ui_text('popular_tools')) ?></h2>
          <p class="text-sm text-zinc-500"><?= e(ui_text('jump_workflow')) ?></p>
        </div>
        <a href="reports.php" class="text-sm font-medium text-zinc-400 hover:text-zinc-200"><?= e(ui_text('all_tools')) ?></a>
      </div>
      <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?php foreach ($popularTools as $t): ?>
          <a href="<?= e($t['href']) ?>" class="flex h-28 flex-col justify-between rounded-2xl bg-gradient-to-br <?= $t['gradient'] ?> p-4 text-left">
            <?= icon($t['icon'], 'h-5 w-5 text-white') ?>
            <span class="text-sm font-semibold text-white"><?= e($t['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
