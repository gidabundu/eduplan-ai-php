<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

auth_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate()) {
    $user = current_user();
    if ($user && ($user['role'] ?? '') === 'Teacher') {
        record_teacher_activity(get_db(), $user, 'sign_out', 'Signed out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
header('Location: login.php');
exit;
