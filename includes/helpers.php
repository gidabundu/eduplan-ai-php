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
    auth_start();
    if (empty($_SESSION['auth_user_id'])) {
        $current = basename($_SERVER['PHP_SELF'] ?? '');
        if (!in_array($current, ['login.php', 'logout.php'], true)) {
            header('Location: login.php');
            exit;
        }
    }
}

function current_user(): ?array
{
    auth_start();
    return is_array($_SESSION['auth_user'] ?? null) ? $_SESSION['auth_user'] : null;
}

function require_admin(): void
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'Admin') {
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
