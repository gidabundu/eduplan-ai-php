<?php
require_once __DIR__ . '/includes/db.php';
$pdo = get_db();
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'Admin';

if (($_GET['export'] ?? '') === 'csv') {
    require_login();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="eduplan-saved-generations.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Type', 'Title', 'Meta', 'Created', 'Content']);
    $rows = $pdo->query('SELECT type,title,meta,created_at,content FROM generated_items ORDER BY created_at DESC');
    while ($row = $rows->fetch()) {
        fputcsv($out, [$row['type'], $row['title'], $row['meta'], $row['created_at'], $row['content']]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_forecast_status') {
        require_login();
        $actor = current_user();
        if (!$actor || !in_array($actor['role'] ?? '', ['Admin', 'Teacher'], true)) {
            http_response_code(403);
            exit('Forbidden.');
        }
    } else {
        require_permission('manage_saved');
    }
    if (!csrf_validate()) {
        flash_set('Could not update Reports: invalid session.');
        header('Location: reports.php');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM generated_items WHERE id = ?')->execute([$id]);
        flash_set('Saved generation removed.');
        header('Location: reports.php');
        exit;
    }
    if ($action === 'edit' && $id > 0) {
        $title = trim($_POST['title'] ?? '');
        $meta = trim($_POST['meta'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title !== '' && $content !== '') {
            $pdo->prepare('UPDATE generated_items SET title=?, meta=?, content=? WHERE id=?')->execute([$title, $meta !== '' ? $meta : null, $content, $id]);
            flash_set('Saved generation updated.');
        }
        header('Location: reports.php');
        exit;
    }
    if ($action === 'update_forecast_status' && $id > 0) {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['planned', 'in_progress', 'taught'], true)) {
            $progressStmt = $pdo->prepare('SELECT p.created_by, p.class_name, p.subject, p.term, p.academic_year,
                    i.week_number, i.topic
                FROM forecast_plan_items i
                INNER JOIN forecast_plans p ON p.id = i.plan_id
                WHERE i.id = ?');
            $progressStmt->execute([$id]);
            $progressItem = $progressStmt->fetch();
            if (!$progressItem || (($actor['role'] ?? '') === 'Teacher'
                && (int)$progressItem['created_by'] !== (int)$actor['id'])) {
                http_response_code(404);
                exit('Forecast week not found.');
            }

            $pdo->prepare('UPDATE forecast_plan_items SET status = ? WHERE id = ?')->execute([$status, $id]);
            if (($actor['role'] ?? '') === 'Teacher') {
                $statusLabels = ['planned' => 'Planned', 'in_progress' => 'In progress', 'taught' => 'Taught'];
                record_teacher_activity(
                    $pdo,
                    $actor,
                    'progress_updated',
                    'Updated Week ' . (int)$progressItem['week_number'] . ' to ' . $statusLabels[$status],
                    $progressItem['class_name'],
                    $progressItem['subject'],
                    $progressItem['topic'],
                    $progressItem['term'],
                    $progressItem['academic_year']
                );
            }
            flash_set('Forecast progress updated.');
        } else {
            flash_set('Could not update forecast progress: invalid status.');
        }
        header('Location: reports.php#progress');
        exit;
    }
}

$userCount = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
$teacherCount = (int)$pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'Teacher'")->fetch()['c'];
$resourceCount = (int)$pdo->query('SELECT COUNT(*) AS c FROM resources')->fetch()['c'];
$indexedCount = (int)$pdo->query('SELECT COUNT(*) AS c FROM resources WHERE extracted_text IS NOT NULL')->fetch()['c'];

$savedCount = (int)$pdo->query('SELECT COUNT(*) AS c FROM generated_items')->fetch()['c'];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$pages = max(1, (int)ceil($savedCount / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$savedStmt = $pdo->prepare('SELECT * FROM generated_items ORDER BY created_at DESC LIMIT ? OFFSET ?');
$savedStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$savedStmt->bindValue(2, $offset, PDO::PARAM_INT);
$savedStmt->execute();
$savedItems = $savedStmt->fetchAll();

// Real counts only — no illustrative/placeholder numbers.
$subjects = $pdo->query("SELECT COALESCE(NULLIF(subject, ''), 'Uncategorized') AS name, COUNT(*) AS total
    FROM resources GROUP BY name ORDER BY total DESC")->fetchAll();
$maxSubjectCount = max(1, ...array_map(fn($s) => (int)$s['total'], $subjects ?: [['total' => 0]]));

$forecastSql = "SELECT p.id AS plan_id, p.academic_year, p.term, p.class_name, p.subject, p.created_at AS plan_created_at,
        au.name AS creator_name, au.role AS creator_role, i.id AS item_id, i.week_number, i.lesson_date, i.topic, i.subtopic, i.objective, i.status, i.updated_at
    FROM forecast_plans p
    INNER JOIN forecast_plan_items i ON i.plan_id = p.id
    LEFT JOIN auth_users au ON au.id = p.created_by
";
if (!$isAdmin) {
    $forecastSql .= ' WHERE p.created_by = ?';
}
$forecastSql .= ' ORDER BY p.created_at DESC, i.week_number ASC';
$forecastStmt = $pdo->prepare($forecastSql);
if ($isAdmin) {
    $forecastStmt->execute();
} else {
    $forecastStmt->execute([(int)($currentUser['id'] ?? 0)]);
}
$forecastRows = $forecastStmt->fetchAll();
$forecastPlans = [];
$forecastTopicCount = 0;
$taughtTopicCount = 0;
foreach ($forecastRows as $row) {
    $planId = (int)$row['plan_id'];
    if (!isset($forecastPlans[$planId])) {
        $forecastPlans[$planId] = [
            'academic_year' => $row['academic_year'],
            'term' => $row['term'],
            'class_name' => $row['class_name'],
            'subject' => $row['subject'],
            'creator_label' => $row['creator_name'] !== null
                ? (($row['creator_role'] === 'Teacher' ? 'Teacher: ' : 'Admin: ') . $row['creator_name'])
                : 'Former or unlinked account',
            'created_at' => $row['plan_created_at'],
            'items' => [],
            'taught' => 0,
        ];
    }
    $forecastPlans[$planId]['items'][] = $row;
    $forecastTopicCount++;
    if ($row['status'] === 'taught') {
        $forecastPlans[$planId]['taught']++;
        $taughtTopicCount++;
    }
}

$activePage = 'reports';
$pageTitle = 'EduPlan AI — Reports';
include __DIR__ . '/includes/header.php';
?>
<div class="space-y-6 p-6">
  <div>
    <h1 class="text-lg font-semibold text-white">Reports</h1>
    <p class="text-sm text-zinc-500">Track curriculum progress across your school.</p>
  </div>

  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
      <div class="flex items-center justify-between"><span class="text-xs font-medium text-zinc-400">Directory users</span><?= icon('users', 'h-4 w-4 text-orange-500') ?></div>
      <div class="mt-2 text-2xl font-semibold text-white"><?= $userCount ?></div>
    </div>
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
      <div class="flex items-center justify-between"><span class="text-xs font-medium text-zinc-400">Teachers</span><?= icon('graduation-cap', 'h-4 w-4 text-orange-500') ?></div>
      <div class="mt-2 text-2xl font-semibold text-white"><?= $teacherCount ?></div>
    </div>
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
      <div class="flex items-center justify-between"><span class="text-xs font-medium text-zinc-400">Resources uploaded</span><?= icon('book-open', 'h-4 w-4 text-orange-500') ?></div>
      <div class="mt-2 text-2xl font-semibold text-white"><?= $resourceCount ?></div>
    </div>
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
      <div class="flex items-center justify-between"><span class="text-xs font-medium text-zinc-400">Indexed for AI</span><?= icon('sparkles', 'h-4 w-4 text-orange-500') ?></div>
      <div class="mt-2 text-2xl font-semibold text-white"><?= $indexedCount ?></div>
    </div>
  </div>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="mb-1 text-sm font-semibold text-white">Resources by subject</h2>
    <p class="mb-4 text-xs text-zinc-500">Real counts from your Resource Library — not an estimate.</p>
    <?php if (!$subjects): ?>
      <p class="py-6 text-center text-xs text-zinc-500">Upload resources in the Resource Library to see a breakdown here.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($subjects as $s): $pct = round(((int)$s['total'] / $maxSubjectCount) * 100); ?>
          <div>
            <div class="mb-1 flex items-center justify-between text-xs text-zinc-400">
              <span><?= e($s['name']) ?></span>
              <span class="font-medium text-zinc-200"><?= (int)$s['total'] ?> resource<?= (int)$s['total'] === 1 ? '' : 's' ?></span>
            </div>
            <div class="h-2 w-full rounded-full bg-zinc-800">
              <div class="h-2 rounded-full bg-gradient-to-r from-orange-500 to-amber-600" style="width: <?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div id="progress" class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <div class="mb-4">
      <h2 class="text-sm font-semibold text-white"><?= $isAdmin ? 'Teacher forecast progress' : 'My forecast progress' ?></h2>
      <p class="mt-1 text-xs text-zinc-500"><?= $isAdmin ? 'Review each teacher’s forecast and completion date.' : 'Update your forecast status as you teach each week.' ?></p>
    </div>
    <?php if (!$forecastPlans): ?>
      <p class="py-6 text-center text-xs text-zinc-500">No tracked forecasts yet. Generate a Forecast and choose “Save to Progress Tracker”.</p>
    <?php else: ?>
      <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3">
          <div class="text-xs text-zinc-500">Plans tracked</div>
          <div class="mt-1 text-xl font-semibold text-white"><?= count($forecastPlans) ?></div>
        </div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3">
          <div class="text-xs text-zinc-500">Weeks taught</div>
          <div class="mt-1 text-xl font-semibold text-white"><?= $taughtTopicCount ?> / <?= $forecastTopicCount ?></div>
        </div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3">
          <div class="text-xs text-zinc-500">Overall coverage</div>
          <div class="mt-1 text-xl font-semibold text-white"><?= $forecastTopicCount > 0 ? (int)round($taughtTopicCount / $forecastTopicCount * 100) : 0 ?>%</div>
        </div>
      </div>
      <div class="space-y-4">
        <?php foreach ($forecastPlans as $planId => $plan): ?>
          <div class="rounded-lg border border-zinc-800 p-3">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
              <div>
                <h3 class="text-sm font-medium text-zinc-100"><?= e($plan['subject']) ?> — <?= e($plan['class_name']) ?></h3>
                <p class="text-xs text-zinc-500"><?= e($plan['creator_label']) ?> · <?= e($plan['term']) ?> · <?= e($plan['academic_year']) ?> · <?= (int)$plan['taught'] ?> of <?= count($plan['items']) ?> weeks taught</p>
              </div>
              <span class="text-xs text-zinc-500">Saved <?= e($plan['created_at']) ?></span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[700px] text-left text-xs">
                <thead><tr class="border-b border-zinc-800 text-zinc-500">
                  <th class="py-2 pr-3 font-medium">Week / date</th><th class="py-2 pr-3 font-medium">Topic</th><th class="py-2 pr-3 font-medium">Sub-topic / objective</th><th class="py-2 font-medium">Progress / last updated</th>
                </tr></thead>
                <tbody class="divide-y divide-zinc-800">
                  <?php foreach ($plan['items'] as $item): ?>
                    <tr>
                      <td class="py-2 pr-3 text-zinc-400">Week <?= (int)$item['week_number'] ?><?= $item['lesson_date'] ? '<br>' . e($item['lesson_date']) : '' ?></td>
                      <td class="py-2 pr-3 font-medium text-zinc-200"><?= e($item['topic']) ?></td>
                      <td class="py-2 pr-3 text-zinc-400"><?= e(implode(' · ', array_filter([$item['subtopic'], $item['objective']]))) ?></td>
                      <td class="py-2">
                        <form method="post" class="flex items-center gap-2">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="update_forecast_status">
                          <input type="hidden" name="id" value="<?= (int)$item['item_id'] ?>">
                          <select name="status" class="rounded border border-zinc-700 bg-zinc-950 px-2 py-1 text-xs text-zinc-200">
                            <option value="planned" <?= $item['status'] === 'planned' ? 'selected' : '' ?>>Planned</option>
                            <option value="in_progress" <?= $item['status'] === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                            <option value="taught" <?= $item['status'] === 'taught' ? 'selected' : '' ?>>Taught</option>
                          </select>
                          <button class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800">Update</button>
                        </form>
                        <div class="mt-1 text-zinc-500">Updated <?= e($item['updated_at']) ?></div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <div class="mb-3 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-white">Saved AI generations</h2>
      <a href="reports.php?export=csv" class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Export CSV</a>
    </div>
    <p class="mb-4 text-xs text-zinc-500">Only items you explicitly saved from the AI Generator's "Save" button appear here.</p>
    <?php if (!$savedItems): ?>
      <p class="py-6 text-center text-xs text-zinc-500">Nothing saved yet — generate something and click Save.</p>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($savedItems as $item): ?>
          <details class="rounded-lg border border-zinc-800 p-3">
            <summary class="cursor-pointer list-none">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <div class="truncate text-sm font-medium text-zinc-100"><?= e($item['title']) ?></div>
                  <div class="text-xs text-zinc-500"><?= e($item['meta'] ?: '') ?></div>
                </div>
                <span class="flex-shrink-0 rounded-full border border-orange-900 bg-orange-950 px-2 py-0.5 text-xs text-orange-300"><?= e(ucwords(str_replace('-', ' ', $item['type']))) ?></span>
              </div>
            </summary>
            <pre class="mt-3 whitespace-pre-wrap border-t border-zinc-800 pt-3 text-xs text-zinc-400"><?= e($item['content']) ?></pre>
            <div class="mt-3 flex gap-2 border-t border-zinc-800 pt-3">
              <button type="button" onclick="EduPlan.openReportEdit(<?= (int)$item['id'] ?>, <?= e(json_encode($item['title'])) ?>, <?= e(json_encode($item['meta'] ?: '')) ?>, <?= e(json_encode($item['content'])) ?>)" class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300">Edit</button>
              <form method="post" onsubmit="return confirm('Delete this saved generation?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                <button class="rounded-lg border border-red-900 px-3 py-1.5 text-xs text-red-300">Delete</button>
              </form>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
        <div class="mt-4 flex items-center justify-center gap-2">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="reports.php?page=<?= $p ?>" class="rounded-lg border px-2.5 py-1 text-xs <?= $p === $page ? 'border-orange-600 text-orange-300' : 'border-zinc-700 text-zinc-400' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<div id="editReportModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-6">
  <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="text-sm font-semibold text-white">Edit saved generation</h2>
    <form method="post" class="mt-4 space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-report-id">
      <input name="title" id="edit-report-title" required class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm">
      <input name="meta" id="edit-report-meta" class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm">
      <textarea name="content" id="edit-report-content" rows="14" required class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm"></textarea>
      <div class="flex gap-2">
        <button type="button" onclick="document.getElementById('editReportModal').classList.add('hidden')" class="flex-1 rounded-full border border-zinc-700 py-2 text-sm">Cancel</button>
        <button type="submit" class="flex-1 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2 text-sm font-medium">Save changes</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
