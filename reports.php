<?php
require_once __DIR__ . '/includes/db.php';
$pdo = get_db();

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
    require_admin();
    if (!csrf_validate()) {
        flash_set('Could not update Reports: invalid session.');
        header('Location: reports.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
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
