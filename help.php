<?php
require_once __DIR__ . '/includes/db.php';

$faqs = [
    ['q' => 'How do I get the AI Generator working?', 'a' => 'Set GOOGLE_API_KEY from Google AI Studio. Gemini is the primary provider and works without any other provider keys. If both Gemini and Anthropic keys are configured, Gemini is preferred. Set AI_PROVIDER=anthropic only if you want to use Anthropic instead. AI keys are managed by the server administrator in config.php, environment variables, or the Git-ignored config.local.php file.'],
    ['q' => 'How does the AI use my uploaded books and syllabi?', 'a' => 'Upload a file (or paste text) in the Resource Library. Its text is extracted automatically and stored. In any Generator tool, pick it from the "Reference material" dropdown and that text is sent to the AI as grounding material.'],
    ['q' => 'Why didn\'t a PDF extract any text?', 'a' => 'Extraction works best on simple, text-based PDFs. When Poppler or Tesseract are installed, EduPlan can also extract text from more PDFs and scanned pages. If a PDF still cannot be read, paste the text directly instead for a reliable result.'],
    ['q' => 'What does "Search the web" do?', 'a' => 'It turns on web search for that one request when the selected AI provider supports it, so the AI can pull in current information beyond what\'s in your Resource Library.'],
    ['q' => 'Is there a login system?', 'a' => 'Yes. EduPlan AI requires you to sign in. On first run, the login page creates the initial administrator account. Administrators can create or reset teacher logins from the User Directory by assigning a temporary password to a teacher account.'],
    ['q' => 'Can teachers sign in?', 'a' => 'Yes. Teachers sign in on the same login page using their User Directory email address and the temporary password provided by an administrator. They must change that password the first time they sign in.'],
    ['q' => 'What can teachers do after signing in?', 'a' => 'Teachers can use the AI Generator, view and manage resources and saved generations, view and manage the User Directory, and edit their own profile. School-wide settings and AI credentials remain managed by the administrator/server configuration.'],
    ['q' => 'How do I track a forecast?', 'a' => 'Generate a Forecast and click “Save to Progress Tracker”. Teachers can update their own weeks to Planned, In progress, or Taught in Reports. Administrators can review every teacher’s class progress and the time of each status update. The coverage summary counts weeks marked Taught. The regular Save button still saves the forecast as a saved generation.'],
    ['q' => 'How do I reset my password?', 'a' => 'Choose “Forgot your password?” on the sign-in page and submit your account email. If it matches an account, EduPlan queues a reset email with a single-use link that expires after one hour. Email delivery must be configured; otherwise contact your administrator.'],
];

$tips = [
    'Use the Custom Request tab for anything the four structured tools don\'t cover — rubrics, letters, checklists, anything.',
    'Click the hamburger icon top-left to collapse the sidebar on a smaller screen.',
    'The Appearance toggle (sun icon) switches the whole interface between dark and light.',
    'Resources only show in the "Reference material" picker once their text has been extracted — check the Status column in the Resource Library.',
    'If a teacher cannot sign in, make sure an administrator has created a login for that teacher in the User Directory and that the teacher is using the correct email address.',
    'Save a generated Forecast to the Progress Tracker. Update your own weekly status in Reports; administrators can review all teachers’ progress and status-update times.',
    'Password reset emails use the mail queue; the configured mail worker must run to deliver queued messages.',
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
