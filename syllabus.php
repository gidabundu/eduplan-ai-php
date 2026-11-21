<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/extract.php';
require_once __DIR__ . '/includes/mailer.php';

$pdo = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    require_permission('manage_resources');
    if (!csrf_validate()) {
        $errors[] = 'Could not upload: invalid session.';
    }
    $title = trim($_POST['title'] ?? '');
    $type = ($_POST['type'] ?? 'syllabus') === 'book' ? 'book' : 'syllabus';
    $subject = trim($_POST['subject'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $pastedText = trim($_POST['pasted_text'] ?? '');

    $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($title === '') {
        $errors[] = 'Please give this resource a title.';
    }
    if (!$hasFile && $pastedText === '') {
        $errors[] = 'Upload a file or paste the text content.';
    }

    $filePath = null;
    $extractedText = null;
    $extractionNote = null;

    if (empty($errors) && $hasFile) {
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The file upload failed (error code ' . $file['error'] . ').';
        } elseif ($file['size'] > MAX_UPLOAD_SIZE) {
            $errors[] = 'That file is larger than the 10 MB limit.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'docx', 'txt', 'md'];
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Unsupported file type .' . $ext . '. Allowed: ' . implode(', ', $allowed) . '.';
            } else {
                $mimeMap = [
                    'pdf' => ['application/pdf'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                    'txt' => ['text/plain', 'application/octet-stream'],
                    'md' => ['text/plain', 'text/markdown', 'application/octet-stream'],
                ];
                $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!in_array($detectedMime, $mimeMap[$ext], true)) {
                    $errors[] = 'The uploaded file content does not match its .' . $ext . ' extension.';
                } else {
                    $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
                    $destName = date('Ymd_His') . '_' . $safeName;
                    $destPath = UPLOAD_DIR . '/' . $destName;
                    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                        $errors[] = 'Could not save the uploaded file.';
                    } else {
                        $filePath = 'uploads/' . $destName;
                        $extraction = extract_resource_text($destPath, $file['name']);
                        $extractedText = $extraction['text'];
                        $extractionNote = $extraction['note'];
                    }
                }
            }
        }
    } elseif (empty($errors) && $pastedText !== '') {
        $extractedText = text_cut($pastedText, 20000);
        $extractionNote = text_len($pastedText) > 20000 ? 'Only the first portion of the pasted text is used as context.' : null;
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO resources (title, type, subject, class, status, file_path, extracted_text, extraction_note, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $title,
            $type,
            $subject !== '' ? $subject : null,
            $class !== '' ? $class : null,
            $extractedText ? 'Approved' : 'Needs review',
            $filePath,
            $extractedText,
            $extractionNote,
            'You',
        ]);
        flash_set(ucfirst($type) . ' "' . $title . '" uploaded' . ($extractedText ? ' and indexed for the AI Generator.' : ', but no text could be extracted — add it manually if needed.'));
        $settings = $pdo->query('SELECT email, notifications FROM settings WHERE id = 1')->fetch();
        if (!empty($settings['notifications']) && !empty($settings['email'])) {
            queue_mail(
                $pdo,
                $settings['email'],
                'EduPlan AI: resource uploaded',
                ucfirst($type) . ' "' . $title . '" was uploaded and ' . ($extractedText ? 'indexed.' : 'needs manual text review.')
            );
        }
        header('Location: syllabus.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['edit', 'delete'], true)) {
    require_permission('manage_resources');
    $action = $_POST['action'];
    if (!csrf_validate()) {
        flash_set('Could not update resource: invalid session.');
        header('Location: syllabus.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $type = ($_POST['type'] ?? 'syllabus') === 'book' ? 'book' : 'syllabus';
        $subject = trim($_POST['subject'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $text = trim($_POST['extracted_text'] ?? '');
        if ($id > 0 && $title !== '') {
            $stmt = $pdo->prepare('UPDATE resources SET title=?, type=?, subject=?, class=?, extracted_text=?, status=?, extraction_note=? WHERE id=?');
            $stmt->execute([
                $title,
                $type,
                $subject !== '' ? $subject : null,
                $class !== '' ? $class : null,
                $text !== '' ? text_cut($text, 20000) : null,
                $text !== '' ? 'Approved' : 'Needs review',
                $text !== '' ? 'Text reviewed or edited manually.' : 'No readable text is indexed.',
                $id,
            ]);
            flash_set('Resource updated.');
        } else {
            flash_set('Could not update resource.');
        }
    } else {
        $row = $pdo->prepare('SELECT file_path FROM resources WHERE id = ?');
        $row->execute([$id]);
        $r = $row->fetch();
        if ($r && $r['file_path'] && file_exists(__DIR__ . '/' . $r['file_path'])) {
            @unlink(__DIR__ . '/' . $r['file_path']);
        }
        $pdo->prepare('DELETE FROM resources WHERE id = ?')->execute([$id]);
        flash_set('Resource removed.');
    }
    header('Location: syllabus.php');
    exit;
}

$filterType = $_GET['type'] ?? 'all';
$q = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM resources WHERE 1=1';
$params = [];
if (in_array($filterType, ['book', 'syllabus'], true)) {
    $sql .= ' AND type = ?';
    $params[] = $filterType;
}
if ($q !== '') {
    $sql .= ' AND (title LIKE ? OR subject LIKE ? OR class LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

$activePage = 'syllabus';
$pageTitle = 'EduPlan AI — Resource Library';
include __DIR__ . '/includes/header.php';
?>
<div class="space-y-6 p-6">
  <div>
    <h1 class="text-lg font-semibold text-white">Resource Library</h1>
    <p class="text-sm text-zinc-500">Upload syllabus and books — the AI Generator can use them as source material.</p>
  </div>

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
      <h2 class="text-sm font-semibold text-white">Upload a resource</h2>
      <p class="mt-1 text-xs text-zinc-500">PDF, DOCX, TXT or MD — or just paste the text below. Text is extracted automatically so the AI Generator can read it.</p>

      <?php if ($errors): ?>
        <div class="mt-3 rounded-lg border border-orange-900 bg-orange-950 p-3 text-xs text-orange-300">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="mt-4 space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <div>
          <label class="text-xs font-medium text-zinc-400">Title</label>
          <input name="title" required placeholder="Enter resource title" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="text-xs font-medium text-zinc-400">Type</label>
            <select name="type" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
              <option value="syllabus">Syllabus</option>
              <option value="book">Book / reference</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-zinc-400">Subject</label>
            <input name="subject" placeholder="Enter subject" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
          <div>
            <label class="text-xs font-medium text-zinc-400">Class</label>
            <input name="class" placeholder="Enter class" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">File (PDF, DOCX, TXT, MD — max 10MB)</label>
          <input type="file" name="file" accept=".pdf,.docx,.doc,.txt,.md" class="mt-1 w-full rounded-lg border border-dashed border-zinc-700 bg-zinc-900 px-2 py-2 text-sm text-zinc-300 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-800 file:px-2 file:py-1 file:text-xs file:text-zinc-200">
        </div>
        <div class="text-center text-xs text-zinc-600">— or —</div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Paste text instead</label>
          <textarea name="pasted_text" rows="4" placeholder="Paste the chapter or syllabus text here..." class="mt-1 w-full resize-none rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"></textarea>
        </div>
        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2.5 text-sm font-medium text-white hover:opacity-90">
          <?= icon('upload', 'h-4 w-4') ?> Upload &amp; index
        </button>
      </form>
    </div>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
      <h2 class="text-sm font-semibold text-white">How this feeds the AI</h2>
      <ul class="mt-3 space-y-2 text-xs text-zinc-400">
        <li class="flex gap-2"><?= icon('check-circle-2', 'h-4 w-4 flex-shrink-0 text-orange-500') ?> Text is extracted from your upload automatically (PDF, DOCX, TXT, MD).</li>
        <li class="flex gap-2"><?= icon('check-circle-2', 'h-4 w-4 flex-shrink-0 text-orange-500') ?> In the AI Generator, pick a resource under "Reference material" to ground the output in it.</li>
        <li class="flex gap-2"><?= icon('check-circle-2', 'h-4 w-4 flex-shrink-0 text-orange-500') ?> Turn on "Search the web" in the Generator to let the AI pull in extra context beyond your library.</li>
        <li class="flex gap-2"><?= icon('check-circle-2', 'h-4 w-4 flex-shrink-0 text-orange-500') ?> Scanned/image-only PDFs can't be read automatically — paste the text instead for best results.</li>
      </ul>
    </div>
  </div>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-sm font-semibold text-white">Library</h2>
      <form method="get" class="flex items-center gap-2">
        <select name="type" onchange="this.form.submit()" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-xs text-zinc-200">
          <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All types</option>
          <option value="syllabus" <?= $filterType === 'syllabus' ? 'selected' : '' ?>>Syllabus</option>
          <option value="book" <?= $filterType === 'book' ? 'selected' : '' ?>>Books</option>
        </select>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search..." class="w-40 rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-xs text-zinc-200">
        <button class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800">Filter</button>
      </form>
    </div>
    <table class="w-full text-left text-sm">
      <thead>
        <tr class="border-b border-zinc-800 text-xs text-zinc-500">
          <th class="pb-2 font-medium">Title</th>
          <th class="pb-2 font-medium">Type</th>
          <th class="pb-2 font-medium">Subject</th>
          <th class="pb-2 font-medium">Class</th>
          <th class="pb-2 font-medium">Status</th>
          <th class="pb-2 font-medium">Added</th>
          <th class="pb-2 font-medium"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-800">
        <?php foreach ($resources as $r): ?>
          <tr>
            <td class="py-2.5 text-zinc-200"><?= e($r['title']) ?></td>
            <td class="py-2.5">
              <span class="rounded-full border px-2 py-0.5 text-xs <?= $r['type'] === 'book' ? 'border-amber-800 bg-amber-950 text-amber-400' : 'border-orange-900 bg-orange-950 text-orange-300' ?>">
                <?= $r['type'] === 'book' ? 'Book' : 'Syllabus' ?>
              </span>
            </td>
            <td class="py-2.5 text-zinc-400"><?= e($r['subject'] ?: '—') ?></td>
            <td class="py-2.5 text-zinc-400"><?= e($r['class'] ?: '—') ?></td>
            <td class="py-2.5 text-zinc-400">
              <?= $r['extracted_text'] ? '<span class="text-emerald-400">Indexed</span>' : '<span class="text-amber-400">' . e($r['status']) . '</span>' ?>
            </td>
            <td class="py-2.5 text-zinc-500"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
            <td class="py-2.5 text-right">
              <button type="button" onclick="EduPlan.openResourceEdit(<?= (int)$r['id'] ?>, <?= e(json_encode($r['title'])) ?>, <?= e(json_encode($r['type'])) ?>, <?= e(json_encode($r['subject'] ?: '')) ?>, <?= e(json_encode($r['class'] ?: '')) ?>, <?= e(json_encode($r['extracted_text'] ?: '')) ?>)" class="mr-3 text-xs font-medium text-zinc-500 hover:text-orange-400">Edit</button>
              <form method="post" class="inline" onsubmit="return confirm('Remove this resource?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="text-xs font-medium text-zinc-500 hover:text-orange-400">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$resources): ?>
          <tr><td colspan="7" class="py-6 text-center text-xs text-zinc-500">No resources yet — upload a syllabus or book above.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div id="editResourceModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-6">
  <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="text-sm font-semibold text-white">Edit resource</h2>
    <form method="post" class="mt-4 space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-resource-id">
      <input name="title" id="edit-resource-title" required class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm">
      <div class="grid grid-cols-3 gap-3">
        <select name="type" id="edit-resource-type" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm"><option value="syllabus">Syllabus</option><option value="book">Book / reference</option></select>
        <input name="subject" id="edit-resource-subject" placeholder="Subject" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm">
        <input name="class" id="edit-resource-class" placeholder="Class" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm">
      </div>
      <label class="block text-xs font-medium text-zinc-400">Indexed text
        <textarea name="extracted_text" id="edit-resource-text" rows="10" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm"></textarea>
      </label>
      <div class="flex gap-2">
        <button type="button" onclick="document.getElementById('editResourceModal').classList.add('hidden')" class="flex-1 rounded-full border border-zinc-700 py-2 text-sm">Cancel</button>
        <button type="submit" class="flex-1 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2 text-sm font-medium">Save changes</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
