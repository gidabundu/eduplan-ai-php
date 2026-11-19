<?php
require_once __DIR__ . '/includes/db.php';
$pdo = get_db();

$allowedTabs = ['lesson-plan', 'assessment', 'forecast', 'lesson-note', 'custom'];
$activeTab = $_GET['tab'] ?? 'lesson-plan';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'lesson-plan';
}

$tabLabels = [
    'lesson-plan' => 'Lesson plan',
    'assessment' => 'Assessment',
    'forecast' => 'Forecast',
    'lesson-note' => 'Lesson note',
    'custom' => 'Custom request',
];

$resourceRows = $pdo->query("SELECT id, title, type, subject FROM resources WHERE extracted_text IS NOT NULL ORDER BY created_at DESC")->fetchAll();
function resource_options(array $rows): string
{
    $html = '<option value="">None</option>';
    foreach ($rows as $r) {
        $kind = $r['type'] === 'book' ? 'Book' : 'Syllabus';
        $label = $r['title'] . ' (' . $kind . ($r['subject'] ? ' · ' . $r['subject'] : '') . ')';
        $html .= '<option value="' . (int)$r['id'] . '">' . e($label) . '</option>';
    }
    return $html;
}

$activePage = 'generator';
$pageTitle = 'EduPlan AI — ' . $tabLabels[$activeTab];
include __DIR__ . '/includes/header.php';
?>
<div class="flex h-full flex-col p-6">
  <div class="mb-4 inline-flex w-fit flex-wrap gap-1 rounded-lg border border-zinc-800 bg-zinc-900 p-1">
    <?php foreach ($tabLabels as $id => $label): ?>
      <a href="generator.php?tab=<?= e($id) ?>" class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors <?= $activeTab === $id ? 'bg-white text-black' : 'text-zinc-400 hover:text-zinc-200' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($activeTab === 'lesson-plan'): ?>
    <div class="flex flex-1 flex-col gap-4 lg:flex-row">
      <div class="generator-parameters h-fit w-full flex-shrink-0 rounded-xl border border-zinc-800 bg-zinc-900 p-5 lg:w-80">
        <h2 class="mb-4 text-sm font-semibold text-white">Plan parameters</h2>
        <div class="space-y-3">
          <div>
            <label class="text-xs font-medium text-zinc-400">Class level</label>
            <input id="lp-classLevel" placeholder="Enter class level" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
          <div>
            <label class="text-xs font-medium text-zinc-400">Subject</label>
            <input id="lp-subject" placeholder="Enter subject" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
          <div>
            <label class="text-xs font-medium text-zinc-400">Term</label>
            <input id="lp-term" placeholder="Enter term" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
          <div>
            <label class="text-xs font-medium text-zinc-400">Topic focus</label>
            <input id="lp-topic" placeholder="Enter topic" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
          <div>
            <label class="text-xs font-medium text-zinc-400">Reference material</label>
            <select id="lp-resource" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"><?= resource_options($resourceRows) ?></select>
          </div>
          <label class="flex items-center gap-2 text-xs text-zinc-400">
            <input type="checkbox" id="lp-websearch" class="h-4 w-4 rounded border-zinc-700 bg-zinc-900"> Also search the web for extra context
          </label>
        </div>
        <button id="lp-generate" onclick="EduPlan.submitLessonPlan()" class="mt-5 flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium text-white hover:opacity-90">
          <?= icon('sparkles', 'h-4 w-4') ?> Generate lesson plan
        </button>
      </div>
      <div class="flex-1 rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <div id="lp-result"><?php include __DIR__ . '/includes/empty_state.php'; ?></div>
      </div>
    </div>

  <?php elseif ($activeTab === 'assessment'): ?>
    <div class="flex flex-1 flex-col gap-4 lg:flex-row">
      <div class="generator-parameters h-fit w-full flex-shrink-0 space-y-4 rounded-xl border border-zinc-800 bg-zinc-900 p-5 lg:w-80">
        <h2 class="text-sm font-semibold text-white">Assessment parameters</h2>
        <div>
          <label class="text-xs font-medium text-zinc-400">Class level</label>
          <input id="as-classLevel" placeholder="Enter class level" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Subject</label>
          <input id="as-subject" placeholder="Enter subject" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Topic</label>
          <input id="as-topic" placeholder="Enter topic" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Assessment type</label>
          <div class="mt-1 grid grid-cols-2 gap-2 text-xs text-zinc-400">
            <label class="flex items-center gap-1.5"><input type="radio" name="as-type" value="Quiz" class="h-3.5 w-3.5"> Quiz</label>
            <label class="flex items-center gap-1.5"><input type="radio" name="as-type" value="Test" checked class="h-3.5 w-3.5"> Test</label>
            <label class="flex items-center gap-1.5"><input type="radio" name="as-type" value="Assignment" class="h-3.5 w-3.5"> Assignment</label>
            <label class="flex items-center gap-1.5"><input type="radio" name="as-type" value="Exercise" class="h-3.5 w-3.5"> Exercise</label>
          </div>
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Question types</label>
          <div class="mt-1 space-y-2">
            <div class="flex items-center justify-between text-xs text-zinc-400">
              <span>Multiple choice</span>
              <div class="flex items-center gap-2">
                <button type="button" onclick="EduPlan.step('as-mcq',-1,0,20)" class="rounded border border-zinc-700 px-1.5">−</button>
                <span id="as-mcq-display" class="w-5 text-center text-zinc-200">4</span>
                <input type="hidden" id="as-mcq" value="4">
                <button type="button" onclick="EduPlan.step('as-mcq',1,0,20)" class="rounded border border-zinc-700 px-1.5">+</button>
              </div>
            </div>
            <div class="flex items-center justify-between text-xs text-zinc-400">
              <span>Essay / open ended</span>
              <div class="flex items-center gap-2">
                <button type="button" onclick="EduPlan.step('as-essay',-1,0,10)" class="rounded border border-zinc-700 px-1.5">−</button>
                <span id="as-essay-display" class="w-5 text-center text-zinc-200">2</span>
                <input type="hidden" id="as-essay" value="2">
                <button type="button" onclick="EduPlan.step('as-essay',1,0,10)" class="rounded border border-zinc-700 px-1.5">+</button>
              </div>
            </div>
          </div>
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Difficulty level — <span id="as-difficulty-label">Standard</span></label>
          <input type="range" id="as-difficulty" min="0" max="100" value="50" oninput="EduPlan.updateDifficultyLabel(this.value)" class="mt-1 w-full">
          <div class="flex justify-between text-xs text-zinc-600"><span>Foundational</span><span>Advanced</span></div>
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Reference material</label>
          <select id="as-resource" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"><?= resource_options($resourceRows) ?></select>
        </div>
        <label class="flex items-center gap-2 text-xs text-zinc-400">
          <input type="checkbox" id="as-websearch" class="h-4 w-4 rounded border-zinc-700 bg-zinc-900"> Also search the web for extra context
        </label>
        <button id="as-generate" onclick="EduPlan.submitAssessment()" class="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium text-white hover:opacity-90">
          <?= icon('sparkles', 'h-4 w-4') ?> Generate assessment
        </button>
      </div>
      <div class="flex-1 rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <div id="as-result"><?php include __DIR__ . '/includes/empty_state.php'; ?></div>
      </div>
    </div>

  <?php elseif ($activeTab === 'forecast'): ?>
    <div class="flex flex-1 flex-col gap-4 lg:flex-row">
      <div class="generator-parameters h-fit w-full flex-shrink-0 space-y-3 rounded-xl border border-zinc-800 bg-zinc-900 p-5 lg:w-80">
        <h2 class="text-sm font-semibold text-white">Forecast parameters</h2>
        <div>
          <label class="text-xs font-medium text-zinc-400">Academic year</label>
          <input id="fc-year" placeholder="Enter academic year" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Term</label>
          <input id="fc-term" placeholder="Enter term" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Class</label>
          <input id="fc-className" placeholder="Enter class" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Subject</label>
          <input id="fc-subject" placeholder="Enter subject" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Syllabus source</label>
          <input id="fc-source" placeholder="Enter syllabus source" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="text-xs font-medium text-zinc-400">Duration (weeks)</label>
            <input type="number" id="fc-weeks" value="4" min="2" max="6" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
          <div>
            <label class="text-xs font-medium text-zinc-400">Lessons/week</label>
            <input type="number" id="fc-lessonsPerWeek" value="3" min="1" max="5" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Start date</label>
          <input type="date" id="fc-startDate" value="<?= date('Y-m-d') ?>" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Reference material</label>
          <select id="fc-resource" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"><?= resource_options($resourceRows) ?></select>
        </div>
        <label class="flex items-center gap-2 text-xs text-zinc-400">
          <input type="checkbox" id="fc-websearch" class="h-4 w-4 rounded border-zinc-700 bg-zinc-900"> Also search the web for extra context
        </label>
        <button id="fc-generate" onclick="EduPlan.submitForecast()" class="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium text-white hover:opacity-90">
          <?= icon('sparkles', 'h-4 w-4') ?> Generate forecast
        </button>
      </div>
      <div class="flex-1 rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <div id="fc-result"><?php include __DIR__ . '/includes/empty_state.php'; ?></div>
      </div>
    </div>

  <?php elseif ($activeTab === 'lesson-note'): ?>
    <div class="flex flex-1 flex-col gap-4 lg:flex-row">
      <div class="generator-parameters h-fit w-full flex-shrink-0 space-y-3 rounded-xl border border-zinc-800 bg-zinc-900 p-5 lg:w-80">
        <h2 class="text-sm font-semibold text-white">Generation parameters</h2>
        <div>
          <label class="text-xs font-medium text-zinc-400">Class level</label>
          <input id="ln-classLevel" placeholder="Enter class level" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Subject</label>
          <input id="ln-subject" placeholder="Enter subject" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Topic focus</label>
          <input id="ln-topic" placeholder="Enter topic" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Sub-topic / objective</label>
          <textarea id="ln-subtopic" rows="2" placeholder="Enter sub-topic or objective" class="mt-1 w-full resize-none rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="text-xs font-medium text-zinc-400">Duration</label>
            <input id="ln-duration" placeholder="Enter duration" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
          <div>
            <label class="text-xs font-medium text-zinc-400">Tone</label>
            <input id="ln-tone" placeholder="Enter tone" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Reference material</label>
          <select id="ln-resource" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"><?= resource_options($resourceRows) ?></select>
        </div>
        <label class="flex items-center gap-2 text-xs text-zinc-400">
          <input type="checkbox" id="ln-websearch" class="h-4 w-4 rounded border-zinc-700 bg-zinc-900"> Also search the web for extra context
        </label>
        <button id="ln-generate" onclick="EduPlan.submitLessonNote()" class="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium text-white hover:opacity-90">
          <?= icon('sparkles', 'h-4 w-4') ?> Generate lesson note
        </button>
      </div>
      <div class="flex-1 rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <div id="ln-result"><?php include __DIR__ . '/includes/empty_state.php'; ?></div>
      </div>
    </div>

  <?php else: ?>
    <div class="flex flex-1 flex-col gap-4 lg:flex-row">
      <div class="generator-parameters h-fit w-full flex-shrink-0 space-y-3 rounded-xl border border-zinc-800 bg-zinc-900 p-5 lg:w-80">
        <h2 class="text-sm font-semibold text-white">Ask the AI for anything</h2>
        <p class="text-xs text-zinc-500">Not covered by the other tools? Describe it here — a rubric, a parent letter, a seating chart idea, a revision checklist, anything.</p>
        <div>
          <label class="text-xs font-medium text-zinc-400">What do you want the AI to do?</label>
          <textarea id="cu-request" rows="6" placeholder="Describe the task you want the AI to perform." class="mt-1 w-full resize-none rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"></textarea>
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Reference material</label>
          <select id="cu-resource" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"><?= resource_options($resourceRows) ?></select>
        </div>
        <label class="flex items-center gap-2 text-xs text-zinc-400">
          <input type="checkbox" id="cu-websearch" class="h-4 w-4 rounded border-zinc-700 bg-zinc-900"> Also search the web for extra context
        </label>
        <button id="cu-generate" onclick="EduPlan.submitCustom()" class="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium text-white hover:opacity-90">
          <?= icon('sparkles', 'h-4 w-4') ?> Generate
        </button>
      </div>
      <div class="flex-1 rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <div id="cu-result"><?php include __DIR__ . '/includes/empty_state.php'; ?></div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
