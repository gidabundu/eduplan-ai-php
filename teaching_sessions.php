<?php
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_db();
$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'Admin';
$isTeacher = ($user['role'] ?? '') === 'Teacher';
if (!$isAdmin && !$isTeacher) {
    http_response_code(403);
    exit('Forbidden.');
}

function teaching_session_post_text(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function teaching_session_valid_date(string $value): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
        return false;
    }
    return checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]);
}

function teaching_session_valid_time(string $value): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
}

function teaching_session_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

$formValues = [
    'class_name' => '',
    'subject' => '',
    'topic' => '',
    'lesson_date' => date('Y-m-d'),
    'start_time' => '',
    'end_time' => '',
    'outcome' => 'taught',
    'teacher_notes' => '',
];
$editingId = 0;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        flash_set('Could not save the teaching session: invalid session.');
        header('Location: teaching_sessions.php');
        exit;
    }

    $action = teaching_session_post_text('action');
    if ($action === 'submit_session' || $action === 'update_session') {
        if (!$isTeacher) {
            http_response_code(403);
            exit('Only teachers can submit or edit teaching sessions.');
        }

        $formValues = [
            'class_name' => teaching_session_post_text('class_name'),
            'subject' => teaching_session_post_text('subject'),
            'topic' => teaching_session_post_text('topic'),
            'lesson_date' => teaching_session_post_text('lesson_date'),
            'start_time' => teaching_session_post_text('start_time'),
            'end_time' => teaching_session_post_text('end_time'),
            'outcome' => teaching_session_post_text('outcome'),
            'teacher_notes' => teaching_session_post_text('teacher_notes'),
        ];
        $editingId = $action === 'update_session' ? (int)($_POST['id'] ?? 0) : 0;

        $lengths = [
            'class_name' => 190,
            'subject' => 190,
            'topic' => 255,
            'teacher_notes' => 2000,
        ];
        $valid = true;
        foreach (['class_name', 'subject', 'topic'] as $requiredField) {
            if ($formValues[$requiredField] === '') {
                $valid = false;
            }
        }
        foreach ($lengths as $field => $maxLength) {
            if (teaching_session_length($formValues[$field]) > $maxLength) {
                $valid = false;
            }
        }
        if (!teaching_session_valid_date($formValues['lesson_date'])
            || !teaching_session_valid_time($formValues['start_time'])
            || !teaching_session_valid_time($formValues['end_time'])
            || $formValues['end_time'] <= $formValues['start_time']
            || !in_array($formValues['outcome'], ['taught', 'partially_taught', 'not_taught'], true)) {
            $valid = false;
        }

        if ($action === 'update_session' && $editingId < 1) {
            $valid = false;
        }
        if ($valid && $action === 'update_session') {
            $editable = $pdo->prepare("SELECT id FROM teaching_sessions
                WHERE id = ? AND auth_user_id = ? AND review_status IN ('pending', 'changes_requested')");
            $editable->execute([$editingId, (int)$user['id']]);
            if (!$editable->fetchColumn()) {
                http_response_code(404);
                exit('Teaching session not found or no longer editable.');
            }
        }

        if ($valid) {
            $sessionData = [
                $formValues['class_name'],
                $formValues['subject'],
                $formValues['topic'],
                $formValues['lesson_date'],
                $formValues['start_time'] . ':00',
                $formValues['end_time'] . ':00',
                $formValues['outcome'],
                $formValues['teacher_notes'] !== '' ? $formValues['teacher_notes'] : null,
            ];
            if ($action === 'submit_session') {
                $stmt = $pdo->prepare("INSERT INTO teaching_sessions
                    (auth_user_id, teacher_name, class_name, subject, topic, lesson_date, start_time, end_time, outcome, teacher_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute(array_merge([(int)$user['id'], (string)$user['name']], $sessionData));
                flash_set('Teaching session submitted for admin review.');
            } else {
                $stmt = $pdo->prepare("UPDATE teaching_sessions
                    SET class_name = ?, subject = ?, topic = ?, lesson_date = ?, start_time = ?, end_time = ?, outcome = ?, teacher_notes = ?,
                        review_status = 'pending', admin_note = NULL, reviewed_by = NULL, reviewed_at = NULL
                    WHERE id = ? AND auth_user_id = ? AND review_status IN ('pending', 'changes_requested')");
                $stmt->execute(array_merge($sessionData, [$editingId, (int)$user['id']]));
                flash_set('Teaching session updated and resubmitted for review.');
            }
            header('Location: teaching_sessions.php');
            exit;
        }

        $error = 'Check that all required fields are filled, the end time is after the start time, and text stays within the stated limits.';
    } elseif ($action === 'review_session') {
        if (!$isAdmin) {
            http_response_code(403);
            exit('Only administrators can review teaching sessions.');
        }

        $id = (int)($_POST['id'] ?? 0);
        $reviewStatus = teaching_session_post_text('review_status');
        $adminNote = teaching_session_post_text('admin_note');
        $noteLength = teaching_session_length($adminNote);
        if ($id < 1 || !in_array($reviewStatus, ['confirmed', 'changes_requested'], true)
            || $noteLength > 2000 || ($reviewStatus === 'changes_requested' && $adminNote === '')) {
            flash_set('Choose a valid review decision and include a note when requesting changes.');
        } else {
            $stmt = $pdo->prepare('UPDATE teaching_sessions
                SET review_status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                WHERE id = ?');
            $stmt->execute([$reviewStatus, $adminNote !== '' ? $adminNote : null, (int)$user['id'], $id]);
            if ($stmt->rowCount() > 0) {
                flash_set($reviewStatus === 'confirmed' ? 'Teaching session confirmed.' : 'Changes requested for the teaching session.');
            } else {
                $exists = $pdo->prepare('SELECT id FROM teaching_sessions WHERE id = ?');
                $exists->execute([$id]);
                flash_set($exists->fetchColumn() ? 'No review changes were saved.' : 'Teaching session not found.');
            }
        }
        header('Location: teaching_sessions.php');
        exit;
    } else {
        http_response_code(400);
        exit('Invalid teaching session action.');
    }
}

$reviewStatus = $_GET['status'] ?? 'all';
if (!is_string($reviewStatus) || !in_array($reviewStatus, ['all', 'pending', 'confirmed', 'changes_requested'], true)) {
    $reviewStatus = 'all';
}
$pageInput = $_GET['page'] ?? '1';
$page = is_string($pageInput) ? filter_var($pageInput, FILTER_VALIDATE_INT) : false;
$page = $page !== false && $page > 0 ? $page : 1;
$pageSize = 25;

$where = [];
$params = [];
if (!$isAdmin) {
    $where[] = 's.auth_user_id = ?';
    $params[] = (int)$user['id'];
}
if ($reviewStatus !== 'all') {
    $where[] = 's.review_status = ?';
    $params[] = $reviewStatus;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM teaching_sessions s' . $whereSql);
$countStmt->execute($params);
$totalSessions = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalSessions / $pageSize));
$page = min($page, $totalPages);
$sessionStmt = $pdo->prepare('SELECT s.*, reviewer.name AS reviewer_name
    FROM teaching_sessions s
    LEFT JOIN auth_users reviewer ON reviewer.id = s.reviewed_by'
    . $whereSql . ' ORDER BY s.lesson_date DESC, s.start_time DESC, s.id DESC LIMIT ? OFFSET ?');
foreach ($params as $index => $param) {
    $sessionStmt->bindValue($index + 1, $param);
}
$sessionStmt->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
$sessionStmt->bindValue(count($params) + 2, ($page - 1) * $pageSize, PDO::PARAM_INT);
$sessionStmt->execute();
$sessions = $sessionStmt->fetchAll();

$outcomeLabels = [
    'taught' => 'Taught',
    'partially_taught' => 'Partially taught',
    'not_taught' => 'Not taught',
];
$reviewLabels = [
    'pending' => 'Pending review',
    'confirmed' => 'Confirmed',
    'changes_requested' => 'Changes requested',
];
$activePage = 'teaching-sessions';
$pageTitle = 'EduPlan AI — Teaching Sessions';
include __DIR__ . '/includes/header.php';
?>
<div class="space-y-4 p-6">
  <div>
    <h1 class="text-lg font-semibold text-white"><?= $isAdmin ? 'Teaching Sessions' : 'My Teaching Sessions' ?></h1>
    <p class="text-sm text-zinc-500"><?= $isAdmin ? 'Review teacher-submitted class sessions.' : 'Record the class sessions you taught for your administrator to review.' ?></p>
  </div>

  <?php if ($error): ?>
    <div role="alert" class="rounded-lg border border-red-900 bg-red-950/40 px-4 py-3 text-sm text-red-300"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($isTeacher): ?>
    <form method="post" class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-900 p-5">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editingId > 0 ? 'update_session' : 'submit_session' ?>">
      <?php if ($editingId > 0): ?><input type="hidden" name="id" value="<?= $editingId ?>"><?php endif; ?>
      <h2 class="text-sm font-semibold text-white"><?= $editingId > 0 ? 'Edit teaching session' : 'Log a teaching session' ?></h2>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <label class="text-xs font-medium text-zinc-400">Class
          <input name="class_name" required maxlength="190" value="<?= e($formValues['class_name']) ?>" class="mt-1 block w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
        </label>
        <label class="text-xs font-medium text-zinc-400">Subject
          <input name="subject" required maxlength="190" value="<?= e($formValues['subject']) ?>" class="mt-1 block w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
        </label>
        <label class="text-xs font-medium text-zinc-400">Topic
          <input name="topic" required maxlength="255" value="<?= e($formValues['topic']) ?>" class="mt-1 block w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
        </label>
        <label class="text-xs font-medium text-zinc-400">Date taught
          <input type="date" name="lesson_date" required value="<?= e($formValues['lesson_date']) ?>" class="mt-1 block w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
        </label>
        <label class="text-xs font-medium text-zinc-400">Start time
          <input type="time" name="start_time" required value="<?= e($formValues['start_time']) ?>" class="mt-1 block w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
        </label>
        <label class="text-xs font-medium text-zinc-400">End time
          <input type="time" name="end_time" required value="<?= e($formValues['end_time']) ?>" class="mt-1 block w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
        </label>
        <label class="text-xs font-medium text-zinc-400">Lesson outcome
          <select name="outcome" class="mt-1 block w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
            <?php foreach ($outcomeLabels as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $formValues['outcome'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label class="block text-xs font-medium text-zinc-400">Notes (optional)
        <textarea name="teacher_notes" maxlength="2000" rows="3" class="mt-1 block w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200"><?= e($formValues['teacher_notes']) ?></textarea>
      </label>
      <p class="text-xs text-zinc-500">Sessions are sent to the administrator as pending review. Use 24-hour times.</p>
      <button class="rounded-full bg-white px-4 py-2 text-xs font-medium text-black hover:bg-zinc-200"><?= $editingId > 0 ? 'Resubmit session' : 'Submit session' ?></button>
    </form>
  <?php endif; ?>

  <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-sm font-semibold text-white"><?= $isAdmin ? 'Teacher session records' : 'Your session records' ?></h2>
        <p class="mt-1 text-xs text-zinc-500"><?= $totalSessions ?> record<?= $totalSessions === 1 ? '' : 's' ?> · page <?= $page ?> of <?= $totalPages ?></p>
      </div>
      <form method="get" class="flex items-center gap-2">
        <label for="session-status" class="text-xs text-zinc-400">Review status</label>
        <select id="session-status" name="status" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-xs text-zinc-200">
          <option value="all" <?= $reviewStatus === 'all' ? 'selected' : '' ?>>All</option>
          <?php foreach ($reviewLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $reviewStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300">Filter</button>
      </form>
    </div>

    <?php if (!$sessions): ?>
      <p class="py-6 text-center text-xs text-zinc-500">No teaching sessions found for this filter.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($sessions as $session): ?>
          <?php
            $statusClasses = [
                'pending' => 'border-amber-900 bg-amber-950 text-amber-300',
                'confirmed' => 'border-emerald-900 bg-emerald-950 text-emerald-300',
                'changes_requested' => 'border-red-900 bg-red-950 text-red-300',
            ];
          ?>
          <article class="rounded-lg border border-zinc-800 bg-zinc-950 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="text-sm font-medium text-zinc-100"><?= e($session['subject']) ?> · <?= e($session['class_name']) ?></h3>
                <p class="mt-1 text-xs text-zinc-400"><?= e($session['topic']) ?></p>
                <p class="mt-1 text-xs text-zinc-500">
                  <?= $isAdmin ? e($session['teacher_name']) . ' · ' : '' ?>
                  <?= e($session['lesson_date']) ?> · <?= e(substr($session['start_time'], 0, 5)) ?>–<?= e(substr($session['end_time'], 0, 5)) ?>
                  · <?= e($outcomeLabels[$session['outcome']] ?? $session['outcome']) ?>
                </p>
              </div>
              <span class="rounded-full border px-2 py-0.5 text-xs <?= e($statusClasses[$session['review_status']] ?? 'border-zinc-700 text-zinc-300') ?>"><?= e($reviewLabels[$session['review_status']] ?? $session['review_status']) ?></span>
            </div>

            <?php if ($session['teacher_notes']): ?>
              <p class="mt-3 whitespace-pre-wrap text-xs text-zinc-400"><?= e($session['teacher_notes']) ?></p>
            <?php endif; ?>
            <?php if ($session['admin_note']): ?>
              <p class="mt-3 border-l-2 border-orange-700 pl-3 text-xs text-zinc-300">Admin note: <?= e($session['admin_note']) ?></p>
            <?php endif; ?>
            <?php if ($session['reviewed_at']): ?>
              <p class="mt-2 text-xs text-zinc-600">Reviewed <?= e($session['reviewed_at']) ?><?= $session['reviewer_name'] ? ' by ' . e($session['reviewer_name']) : '' ?></p>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
              <form method="post" class="mt-4 grid gap-2 border-t border-zinc-800 pt-3 sm:grid-cols-[1fr_auto]">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="review_session">
                <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
                <div class="space-y-2">
                  <select name="review_status" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-xs text-zinc-200">
                    <option value="confirmed">Confirm session</option>
                    <option value="changes_requested">Request changes</option>
                  </select>
                  <textarea name="admin_note" maxlength="2000" rows="2" placeholder="Admin note (required when requesting changes)" class="block w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-200"><?= e($session['admin_note'] ?? '') ?></textarea>
                </div>
                <button class="self-start rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Save review</button>
              </form>
            <?php elseif (in_array($session['review_status'], ['pending', 'changes_requested'], true)): ?>
              <details class="mt-3 border-t border-zinc-800 pt-3">
                <summary class="cursor-pointer text-xs font-medium text-orange-300"><?= $session['review_status'] === 'changes_requested' ? 'Edit requested changes' : 'Edit session' ?></summary>
                <form method="post" class="mt-3 grid gap-2 sm:grid-cols-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_session">
                  <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
                  <input name="class_name" required maxlength="190" value="<?= e($session['class_name']) ?>" aria-label="Class" class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-200">
                  <input name="subject" required maxlength="190" value="<?= e($session['subject']) ?>" aria-label="Subject" class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-200">
                  <input name="topic" required maxlength="255" value="<?= e($session['topic']) ?>" aria-label="Topic" class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-200">
                  <input type="date" name="lesson_date" required value="<?= e($session['lesson_date']) ?>" aria-label="Date taught" class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-200">
                  <input type="time" name="start_time" required value="<?= e(substr($session['start_time'], 0, 5)) ?>" aria-label="Start time" class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-200">
                  <input type="time" name="end_time" required value="<?= e(substr($session['end_time'], 0, 5)) ?>" aria-label="End time" class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-200">
                  <select name="outcome" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-2 text-xs text-zinc-200">
                    <?php foreach ($outcomeLabels as $value => $label): ?>
                      <option value="<?= e($value) ?>" <?= $session['outcome'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <textarea name="teacher_notes" maxlength="2000" rows="2" aria-label="Notes" class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-200 sm:col-span-2"><?= e($session['teacher_notes'] ?? '') ?></textarea>
                  <button class="justify-self-start rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300">Resubmit for review</button>
                </form>
              </details>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
      <div class="mt-4 flex justify-center gap-2">
        <?php if ($page > 1): ?>
          <a href="teaching_sessions.php?<?= e(http_build_query(['status' => $reviewStatus, 'page' => $page - 1])) ?>" class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a href="teaching_sessions.php?<?= e(http_build_query(['status' => $reviewStatus, 'page' => $page + 1])) ?>" class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300">Next</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
