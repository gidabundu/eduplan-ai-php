<?php
require_once __DIR__ . '/includes/db.php';

$faqs = [
    ['q' => 'How do I get the AI Generator working?', 'a' => 'Set GOOGLE_API_KEY from Google AI Studio. Gemini is the primary provider and works without any other provider keys. If both Gemini and Anthropic keys are configured, Gemini is preferred. Set AI_PROVIDER=anthropic only if you want to use Anthropic instead.'],
    ['q' => 'How does the AI use my uploaded books and syllabi?', 'a' => 'Upload a file (or paste text) in the Resource Library. Its text is extracted automatically and stored. In any Generator tool, pick it from the "Reference material" dropdown and that text is sent to the AI as grounding material.'],
    ['q' => 'Why didn\'t a PDF extract any text?', 'a' => 'Extraction is dependency-free and works best on simple, text-based PDFs. Scanned or image-only PDFs can\'t be read this way — paste the text directly instead for a reliable result.'],
    ['q' => 'What does "Search the web" do?', 'a' => 'It turns on Anthropic\'s server-side web search tool for that one request, so the AI can pull in current information beyond what\'s in your Resource Library.'],
    ['q' => 'Is there a login system?', 'a' => 'No — this build has no authentication. Every page is open. The Login/Start for Free/Create account/Sign in controls route to Settings, the Generator, and User Directory respectively.'],
];

$tips = [
    'Use the Custom Request tab for anything the four structured tools don\'t cover — rubrics, letters, checklists, anything.',
    'Click the hamburger icon top-left to collapse the sidebar on a smaller screen.',
    'The Appearance toggle (sun icon) switches the whole interface between dark and light.',
    'Resources only show in the "Reference material" picker once their text has been extracted — check the Status column in the Resource Library.',
];

$activePage = 'help';
$pageTitle = 'EduPlan AI — Help';
include __DIR__ . '/includes/header.php';
?>
<div class="mx-auto max-w-2xl space-y-8 p-6">
  <div>
    <h1 class="text-lg font-semibold text-white">Help</h1>
    <p class="text-sm text-zinc-500">Quick answers for setting up and using EduPlan AI.</p>
  </div>

  <div id="help" class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="mb-4 text-sm font-semibold text-white">Frequently asked questions</h2>
    <div class="space-y-4">
      <?php foreach ($faqs as $f): ?>
        <div>
          <div class="text-sm font-medium text-zinc-100"><?= e($f['q']) ?></div>
          <p class="mt-1 text-sm text-zinc-400"><?= e($f['a']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div id="tips" class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="mb-4 text-sm font-semibold text-white">Tips</h2>
    <ul class="space-y-2">
      <?php foreach ($tips as $t): ?>
        <li class="flex gap-2 text-sm text-zinc-400"><?= icon('check-circle-2', 'h-4 w-4 flex-shrink-0 text-orange-500') ?> <?= e($t) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
