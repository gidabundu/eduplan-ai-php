<?php
require_once __DIR__ . '/includes/db.php';
require_admin();
$pdo = get_db();

$date = $_GET['date'] ?? date('Y-m-d');
$dateParts = is_string($date) ? explode('-', $date) : [];
if (count($dateParts) !== 3 || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
    $date = date('Y-m-d');
}
$teacherInput = $_GET['teacher'] ?? '';
$teacherId = is_string($teacherInput) ? filter_var($teacherInput, FILTER_VALIDATE_INT) : false;
$teacherId = $teacherId !== false && $teacherId > 0 ? $teacherId : null;
$pageInput = $_GET['page'] ?? '1';
$page = is_string($pageInput) ? filter_var($pageInput, FILTER_VALIDATE_INT) : false;
$page = $page !== false && $page > 0 ? $page : 1;
$start = $date . ' 00:00:00';
$end = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
$pageSize = 20;

$teachers = $pdo->query("SELECT id, name FROM auth_users WHERE role = 'Teacher' ORDER BY name")->fetchAll();

$summarySql = "SELECT
    COUNT(DISTINCT CASE WHEN event_type IN ('page_view', 'activity', 'generation', 'forecast_saved', 'progress_updated') THEN auth_user_id END) AS active_teachers,
    SUM(CASE WHEN event_type = 'sign_in' THEN 1 ELSE 0 END) AS sign_ins,
    SUM(CASE WHEN event_type IN ('page_view', 'activity', 'generation', 'forecast_saved', 'progress_updated') THEN 1 ELSE 0 END) AS activities
    FROM teacher_activities WHERE created_at >= ? AND created_at < ?";
if ($teacherId !== null) {
    $summarySql .= ' AND auth_user_id = ?';
}
$summaryStmt = $pdo->prepare($summarySql);
$summaryParams = [$start, $end];
if ($teacherId !== null) {
    $summaryParams[] = $teacherId;
}
$summaryStmt->execute($summaryParams);
$summary = $summaryStmt->fetch();

$whereSql = ' FROM teacher_activities WHERE created_at >= ? AND created_at < ?';
$params = [$start, $end];
if ($teacherId !== null) {
    $whereSql .= ' AND auth_user_id = ?';
    $params[] = $teacherId;
}
$groupStmt = $pdo->prepare('SELECT auth_user_id, MAX(teacher_name) AS teacher_name, COUNT(*) AS activity_count'
    . $whereSql . ' GROUP BY auth_user_id ORDER BY teacher_name');
$groupStmt->execute($params);
$allTeacherGroups = $groupStmt->fetchAll();
$totalTeachers = count($allTeacherGroups);
$totalActivities = array_sum(array_map(static function (array $group): int {
    return (int)$group['activity_count'];
}, $allTeacherGroups));
$totalPages = max(1, (int)ceil($totalTeachers / $pageSize));
$page = min($page, $totalPages);
$teacherGroups = array_slice($allTeacherGroups, ($page - 1) * $pageSize, $pageSize);
$activitiesByTeacher = [];
if ($teacherGroups) {
    $teacherIds = array_map(static function (array $group): int {
        return (int)$group['auth_user_id'];
    }, $teacherGroups);
    $placeholders = implode(', ', array_fill(0, count($teacherIds), '?'));
    $activityStmt = $pdo->prepare('SELECT auth_user_id, event_type, activity, class_name, subject, topic, term, academic_year, created_at
        FROM teacher_activities
        WHERE created_at >= ? AND created_at < ? AND auth_user_id IN (' . $placeholders . ')
        ORDER BY auth_user_id, created_at DESC, id DESC');
    $activityStmt->bindValue(1, $start);
    $activityStmt->bindValue(2, $end);
    foreach ($teacherIds as $index => $id) {
        $activityStmt->bindValue($index + 3, $id, PDO::PARAM_INT);
    }
    $activityStmt->execute();
    foreach ($activityStmt->fetchAll() as $entry) {
        $activitiesByTeacher[(int)$entry['auth_user_id']][] = $entry;
    }
}

$activePage = 'teacher-activity';
$pageTitle = 'EduPlan AI — Teacher Activity';
include __DIR__ . '/includes/header.php';
?>
<div class="space-y-4 p-6">
  <div>
    <h1 class="text-lg font-semibold text-white">Teacher Activity</h1>
    <p class="text-sm text-zinc-500">Review teacher sign-ins, generations, and teaching progress.</p>
  </div>

  <div class="grid gap-3 sm:grid-cols-3">
    <?php foreach ([
        ['Active teachers', (int)($summary['active_teachers'] ?? 0)],
        ['Sign-ins', (int)($summary['sign_ins'] ?? 0)],
        ['Activities', (int)($summary['activities'] ?? 0)],
    ] as [$label, $value]): ?>
      <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
        <p class="text-xs text-zinc-500"><?= e($label) ?> · <?= e($date) ?></p>
        <p class="mt-2 text-2xl font-semibold text-white"><?= $value ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="get" class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <label class="text-xs font-medium text-zinc-400">Day
      <input type="date" name="date" value="<?= e($date) ?>" class="mt-1 block rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
    </label>
    <label class="text-xs font-medium text-zinc-400">Teacher
      <select name="teacher" class="mt-1 block w-64 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200">
        <option value="">All teachers</option>
        <?php foreach ($teachers as $teacher): ?>
          <option value="<?= (int)$teacher['id'] ?>" <?= $teacherId === (int)$teacher['id'] ? 'selected' : '' ?>><?= e($teacher['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="rounded-full bg-white px-4 py-2 text-xs font-medium text-black hover:bg-zinc-200">Apply filters</button>
  </form>

  <div class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <div class="mb-3 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-white">Teacher records</h2>
      <span class="text-xs text-zinc-500"><?= $totalTeachers ?> teachers · <?= $totalActivities ?> events · page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <?php if (!$teacherGroups): ?>
      <p class="py-6 text-center text-xs text-zinc-500">No teacher activity recorded for this day and filter.</p>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($teacherGroups as $group): $groupId = (int)$group['auth_user_id']; ?>
          <details class="rounded-lg border border-zinc-800 bg-zinc-950">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 hover:bg-zinc-900">
              <span class="font-medium text-zinc-100"><?= e($group['teacher_name']) ?></span>
              <span class="flex items-center gap-2 text-xs text-zinc-500">
                <?= (int)$group['activity_count'] ?> events
                <?= icon('chevron-down', 'h-4 w-4') ?>
              </span>
            </summary>
            <div class="overflow-x-auto border-t border-zinc-800 p-3">
              <table class="w-full min-w-[700px] text-left text-xs">
                <thead>
                  <tr class="border-b border-zinc-800 text-zinc-500">
                    <th class="py-2 pr-4 font-medium">Time</th>
                    <th class="py-2 pr-4 font-medium">Event</th>
                    <th class="py-2 pr-4 font-medium">Activity</th>
                    <th class="py-2 font-medium">Details</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                  <?php foreach ($activitiesByTeacher[$groupId] ?? [] as $entry): ?>
                    <?php
                      $details = [];
                      foreach ([
                          'Class' => $entry['class_name'],
                          'Subject' => $entry['subject'],
                          'Topic' => $entry['topic'],
                          'Term' => $entry['term'],
                          'Year' => $entry['academic_year'],
                      ] as $label => $value) {
                          if ($value !== null && $value !== '') {
                              $details[] = $label . ': ' . $value;
                          }
                      }
                    ?>
                    <tr>
                      <td class="whitespace-nowrap py-2 pr-4 text-zinc-400"><?= e($entry['created_at']) ?></td>
                      <td class="py-2 pr-4 text-zinc-400"><?= e(str_replace('_', ' ', ucfirst($entry['event_type']))) ?></td>
                      <td class="py-2 pr-4 text-zinc-300"><?= e($entry['activity']) ?></td>
                      <td class="py-2 text-zinc-400"><?= e(implode(' · ', $details) ?: '—') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
      <div class="mt-4 flex justify-center gap-2">
        <?php if ($page > 1): ?>
          <a href="teacher_activity.php?<?= e(http_build_query(['date' => $date, 'teacher' => $teacherId, 'page' => $page - 1])) ?>" class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a href="teacher_activity.php?<?= e(http_build_query(['date' => $date, 'teacher' => $teacherId, 'page' => $page + 1])) ?>" class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300">Next</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
