<?php
require_once __DIR__ . '/includes/db.php';
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    require_admin();
    if (!csrf_validate()) {
        flash_set('Could not add user: invalid session.');
        header('Location: users.php');
        exit;
    }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = ($_POST['role'] ?? 'Teacher') === 'Admin' ? 'Admin' : 'Teacher';
    $department = trim($_POST['department'] ?? '');
    $classes = (int)($_POST['classes'] ?? 0);

    if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $classes >= 0) {
        $existing = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $existing->execute([strtolower($email)]);
        if ($existing->fetch()) {
            flash_set('A directory user with that email already exists.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, role, department, classes) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, strtolower($email), $role, $department, $classes]);
            flash_set('Added ' . $name . ' to the directory.');
        }
    } else {
        flash_set('Could not add user: check the name, email, and class count.');
    }
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_admin();
    if (!csrf_validate()) {
        flash_set('Could not remove user: invalid session.');
        header('Location: users.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
        require_admin();
        if (!csrf_validate()) {
            flash_set('Could not update user: invalid session.');
            header('Location: users.php');
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = ($_POST['role'] ?? 'Teacher') === 'Admin' ? 'Admin' : 'Teacher';
        $department = trim($_POST['department'] ?? '');
        $classes = max(0, (int)($_POST['classes'] ?? 0));
        if ($id > 0 && $name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $existing->execute([$email, $id]);
            if ($existing->fetch()) {
                flash_set('A directory user with that email already exists.');
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role=?, department=?, classes=? WHERE id=?');
                $stmt->execute([$name, $email, $role, $department, $classes, $id]);
                flash_set('Updated ' . $name . '.');
            }
        } else {
            flash_set('Could not update user: check the name, email, and class count.');
        }
        header('Location: users.php');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    flash_set('User removed.');
    header('Location: users.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM users WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR email LIKE ? OR department LIKE ? OR role LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$activePage = 'users';
$pageTitle = 'EduPlan AI — User Directory';
include __DIR__ . '/includes/header.php';
?>
<div class="space-y-4 p-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-lg font-semibold text-white">User Directory</h1>
      <p class="text-sm text-zinc-500">Manage teachers, admins, and their assignments.</p>
    </div>

    <div id="editUserModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-6">
      <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-5">
        <h2 class="text-sm font-semibold text-white">Edit user</h2>
        <form method="post" class="mt-4 space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="edit-user-id">
          <input name="name" id="edit-user-name" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          <input type="email" name="email" id="edit-user-email" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          <div class="grid grid-cols-2 gap-3">
            <select name="role" id="edit-user-role" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200"><option>Teacher</option><option>Admin</option></select>
            <input type="number" name="classes" id="edit-user-classes" min="0" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          </div>
          <input name="department" id="edit-user-department" class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
          <div class="flex gap-2 pt-2">
            <button type="button" onclick="document.getElementById('editUserModal').classList.add('hidden')" class="flex-1 rounded-full border border-zinc-700 py-2 text-sm">Cancel</button>
            <button type="submit" class="flex-1 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2 text-sm font-medium">Save changes</button>
          </div>
        </form>
      </div>
    </div>
    <button type="button" onclick="document.getElementById('addUserModal').classList.remove('hidden')" class="rounded-full bg-white px-4 py-2 text-xs font-medium text-black hover:bg-zinc-200">+ Add user</button>
  </div>

  <form method="get" class="flex items-center gap-2">
    <div class="relative">
      <?= icon('search', 'pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-500') ?>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search users..." class="w-64 rounded-lg border border-zinc-700 bg-zinc-900 py-1.5 pl-8 pr-3 text-sm text-zinc-200">
    </div>
    <button class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800">Search</button>
  </form>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <table class="w-full text-left text-sm">
      <thead>
        <tr class="border-b border-zinc-800 text-xs text-zinc-500">
          <th class="pb-2 font-medium">Name</th>
          <th class="pb-2 font-medium">Role</th>
          <th class="pb-2 font-medium">Department</th>
          <th class="pb-2 font-medium">Email</th>
          <th class="pb-2 font-medium">Classes</th>
          <th class="pb-2 font-medium"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-800">
        <?php foreach ($users as $u): ?>
          <tr>
            <td class="py-2.5 font-medium text-zinc-100"><?= e($u['name']) ?></td>
            <td class="py-2.5">
              <span class="rounded-full border px-2 py-0.5 text-xs <?= $u['role'] === 'Admin' ? 'border-amber-800 bg-amber-950 text-amber-400' : 'border-orange-900 bg-orange-950 text-orange-300' ?>"><?= e($u['role']) ?></span>
            </td>
            <td class="py-2.5 text-zinc-400"><?= e($u['department'] ?: '—') ?></td>
            <td class="py-2.5 text-zinc-400"><?= e($u['email']) ?></td>
            <td class="py-2.5 text-zinc-400"><?= (int)$u['classes'] ?: '—' ?></td>
            <td class="py-2.5 text-right">
              <button type="button" onclick="EduPlan.openUserEdit(<?= (int)$u['id'] ?>, <?= e(json_encode($u['name'])) ?>, <?= e(json_encode($u['email'])) ?>, <?= e(json_encode($u['role'])) ?>, <?= e(json_encode($u['department'] ?: '')) ?>, <?= (int)$u['classes'] ?>)" class="mr-3 text-xs font-medium text-zinc-500 hover:text-orange-400">Edit</button>
              <form method="post" class="inline" onsubmit="return confirm('Remove this user?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="text-xs font-medium text-zinc-500 hover:text-orange-400">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
          <tr><td colspan="6" class="py-6 text-center text-xs text-zinc-500">No users match your search.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="addUserModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-6">
  <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-5">
    <h2 class="text-sm font-semibold text-white">Add user</h2>
    <form method="post" class="mt-4 space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div>
        <label class="text-xs font-medium text-zinc-400">Name</label>
        <input name="name" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
      </div>
      <div>
        <label class="text-xs font-medium text-zinc-400">Email</label>
        <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-medium text-zinc-400">Role</label>
          <select name="role" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
            <option>Teacher</option>
            <option>Admin</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-medium text-zinc-400">Classes</label>
          <input type="number" name="classes" value="0" min="0" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
        </div>
      </div>
      <div>
        <label class="text-xs font-medium text-zinc-400">Department</label>
        <input name="department" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-200">
      </div>
      <div class="flex gap-2 pt-2">
        <button type="button" onclick="document.getElementById('addUserModal').classList.add('hidden')" class="flex-1 rounded-full border border-zinc-700 py-2 text-sm font-medium text-zinc-300 hover:bg-zinc-800">Cancel</button>
        <button type="submit" class="flex-1 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 py-2 text-sm font-medium text-white hover:opacity-90">Add user</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
