<?php
/**
 * Renders a Lucide icon placeholder. Actual SVGs are injected client-side by
 * lucide.createIcons() (see assets/app.js), using the same icon set as the
 * original design.
 */
function icon(string $name, string $class = ''): string
{
    return '<i data-lucide="' . e($name) . '" class="' . e($class) . '"></i>';
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = $message;
}

function flash_get(): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $msg = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $msg;
}

function auth_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function csrf_token(): string
{
    auth_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_validate(): bool
{
    auth_start();
    $provided = is_string($_POST['csrf_token'] ?? null)
        ? $_POST['csrf_token']
        : (is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    return $provided !== '' && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $provided);
}

function csrf_required(): void
{
    if (!csrf_validate()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

function require_login(): void
{
    static $activityLogged = false;

    auth_start();
    if (empty($_SESSION['auth_user_id'])) {
        $current = basename($_SERVER['PHP_SELF'] ?? '');
        if (!in_array($current, ['login.php', 'logout.php'], true)) {
            header('Location: login.php');
            exit;
        }
    } else {
        if (function_exists('get_db')) {
            $stmt = get_db()->prepare('SELECT must_change_password FROM auth_users WHERE id = ?');
            $stmt->execute([(int)$_SESSION['auth_user_id']]);
            $mustChangePassword = $stmt->fetchColumn();
            if ($mustChangePassword === false) {
                $_SESSION = [];
                session_destroy();
                header('Location: login.php');
                exit;
            }
            $_SESSION['auth_user']['must_change_password'] = (bool)$mustChangePassword;
        }

        if (!empty($_SESSION['auth_user']['must_change_password'])) {
            $current = basename($_SERVER['PHP_SELF'] ?? '');
            if (!in_array($current, ['change_password.php', 'logout.php'], true)) {
                header('Location: change_password.php');
                exit;
            }
        }

        if (!$activityLogged) {
            $user = current_user();
            if ($user && ($user['role'] ?? '') === 'Teacher' && function_exists('get_db')) {
                $page = basename($_SERVER['PHP_SELF'] ?? '');
                $labels = [
                    'index.php' => 'Home',
                    'generator.php' => 'AI Generator',
                    'syllabus.php' => 'Resource Library',
                    'users.php' => 'User Directory',
                    'reports.php' => 'Reports',
                    'settings.php' => 'Settings',
                    'help.php' => 'Help',
                    'change_password.php' => 'Password settings',
                    'generate.php' => 'AI Generator',
                    'save_generated.php' => 'Saved generations',
                    'save_forecast.php' => 'Forecast tracker',
                ];
                $label = $labels[$page] ?? 'EduPlan';
                $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
                $eventType = $method === 'GET' ? 'page_view' : 'activity';

                if ($method === 'GET') {
                    $activity = 'Viewed ' . $label;
                } elseif ($page === 'generate.php') {
                    $activity = 'Used AI Generator';
                } elseif ($page === 'save_generated.php') {
                    $activity = 'Saved generated content';
                } elseif ($page === 'save_forecast.php') {
                    $activity = 'Saved forecast progress';
                } else {
                    $activity = 'Submitted on ' . $label;
                }

                $isForecastStatusUpdate = $page === 'reports.php'
                    && ($_POST['action'] ?? '') === 'update_forecast_status';
                if (!in_array($page, ['generate.php', 'save_forecast.php'], true) && !$isForecastStatusUpdate) {
                    record_teacher_activity(get_db(), $user, $eventType, $activity);
                }
            }
            $activityLogged = true;
        }
    }
}

function record_teacher_activity(
    PDO $pdo,
    array $user,
    string $eventType,
    string $activity,
    ?string $className = null,
    ?string $subject = null,
    ?string $topic = null,
    ?string $term = null,
    ?string $academicYear = null
): void
{
    if (($user['role'] ?? '') !== 'Teacher') {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO teacher_activities
        (auth_user_id, directory_user_id, teacher_name, teacher_email, event_type, activity, class_name, subject, topic, term, academic_year)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        (int)$user['id'],
        isset($user['directory_user_id']) ? (int)$user['directory_user_id'] : null,
        (string)$user['name'],
        (string)$user['email'],
        $eventType,
        $activity,
        activity_text($className, 190),
        activity_text($subject, 190),
        activity_text($topic, 255),
        activity_text($term, 190),
        activity_text($academicYear, 50),
    ]);
}

function activity_text(?string $value, int $maxLength): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    if (preg_match('/^.{0,' . $maxLength . '}/us', $value, $matches)) {
        return $matches[0];
    }

    return substr($value, 0, $maxLength);
}

function current_user(): ?array
{
    auth_start();
    return is_array($_SESSION['auth_user'] ?? null) ? $_SESSION['auth_user'] : null;
}

function require_admin(): void
{
    require_login();
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'Admin') {
        http_response_code(403);
        echo 'Forbidden.';
        exit;
    }
}

function require_permission(string $permission): void
{
    require_login();
    $user = current_user();
    $teacherPermissions = [
        'generate',
        'manage_resources',
        'manage_saved',
        'manage_users',
        'edit_profile',
    ];

    if (!$user || (($user['role'] ?? '') !== 'Admin'
        && !(($user['role'] ?? '') === 'Teacher' && in_array($permission, $teacherPermissions, true)))) {
        http_response_code(403);
        echo 'Forbidden.';
        exit;
    }
}

function get_user_language(): string
{
    $language = 'English';

    if (function_exists('get_db')) {
        try {
            $pdo = get_db();
            $row = $pdo->query('SELECT language FROM settings WHERE id = 1')->fetch();
            if (is_array($row) && !empty($row['language']) && in_array($row['language'], ['English', 'Krio'], true)) {
                $language = $row['language'];
            }
        } catch (Throwable $e) {
            $language = 'English';
        }
    }

    return $language;
}

function ui_text(string $key, ?string $language = null): string
{
    $lang = $language ?? get_user_language();
    $translations = [
        'English' => [
            'home' => 'Home',
            'create' => 'Create',
            'assets' => 'Assets',
            'tools' => 'Tools',
            'settings' => 'Settings',
            'help' => 'Help',
            'tips' => 'Tips',
            'login' => 'Login',
            'start_free' => 'Start for Free',
            'save_changes' => 'Save changes',
            'profile' => 'Profile',
            'preferences' => 'Preferences',
            'language' => 'Language',
            'name' => 'Name',
            'email' => 'Email',
            'school' => 'School',
            'manage_profile' => 'Manage your profile and preferences.',
            'role' => 'Role',
            'appearance' => 'Appearance',
            'ai_configuration' => 'AI configuration',
            'popular_tools' => 'Popular tools',
            'jump_workflow' => 'Jump straight into a workflow',
            'all_tools' => 'All tools',
            'what_plan_today' => 'What would you like to plan today?',
            'resource_library' => 'Resource Library',
            'more' => 'more',
            'school_name_hint' => 'Edit school name in Settings',
        ],
        'Krio' => [
            'home' => 'Home',
            'create' => 'Kreat',
            'assets' => 'Aset',
            'tools' => 'Tulz',
            'settings' => 'Setings',
            'help' => 'Lɛp',
            'tips' => 'Tɛps',
            'login' => 'Lɔgin',
            'start_free' => 'Stat fɔ Frɛ',
            'save_changes' => 'Sev chenjiz',
            'profile' => 'Profil',
            'preferences' => 'Prefrens',
            'language' => 'Languij',
            'name' => 'Nɛm',
            'email' => 'Emɛl',
            'school' => 'Skul',
            'manage_profile' => 'Manage yu profil ɛn prefrens.',
            'role' => 'Rol',
            'appearance' => 'Apeaans',
            'ai_configuration' => 'AI konfigurashɔn',
            'popular_tools' => 'Popula tulz',
            'jump_workflow' => 'Jamp direkt to wan wokfɔw',
            'all_tools' => 'Ol tulz',
            'what_plan_today' => 'Wet yu want fɔ plan tide?',
            'resource_library' => 'Rɛsɔs libri',
            'more' => 'mɔ',
            'school_name_hint' => 'Edit skul nem na Setings',
        ],
    ];

    $dictionary = $translations[$lang] ?? $translations['English'];
    return $dictionary[$key] ?? $translations['English'][$key] ?? $key;
}
