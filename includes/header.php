<?php
require_once __DIR__ . '/helpers.php';
require_login();

if (!isset($activePage)) $activePage = 'home';
if (!isset($activeTab)) $activeTab = 'lesson-plan';
if (!isset($pageTitle)) $pageTitle = 'EduPlan AI';
$flash = flash_get();
$siteLanguage = get_user_language();
$settingsRow = get_db()->query('SELECT ai_suggestions FROM settings WHERE id = 1')->fetch();
$aiSuggestions = !empty($settingsRow['ai_suggestions']);
?>
<!DOCTYPE html>
<html lang="<?= $siteLanguage === 'Krio' ? 'kr' : 'en' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?></title>
<script>
  (function () {
    var saved = localStorage.getItem('eduplan-theme');
    if (saved === 'light') document.documentElement.setAttribute('data-theme', 'light');
  })();
</script>
<link rel="stylesheet" href="assets/tailwind-built.css?v=<?= (int)filemtime(__DIR__ . '/../assets/tailwind-built.css') ?>">
<link rel="stylesheet" href="assets/style.css?v=<?= (int)filemtime(__DIR__ . '/../assets/style.css') ?>">
<script src="assets/vendor/lucide.min.js"></script>
</head>
<body data-ai-suggestions="<?= $aiSuggestions ? '1' : '0' ?>" class="h-screen overflow-hidden bg-black font-sans text-white">
<div class="flex h-screen w-full overflow-hidden">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
    <?php include __DIR__ . '/topbar.php'; ?>
    <?php if ($flash): ?>
      <div class="border-b border-zinc-900 bg-zinc-900 px-6 py-2 text-center text-sm text-emerald-400"><?= e($flash) ?></div>
    <?php endif; ?>
    <main class="min-h-0 flex-1 overflow-y-auto">
