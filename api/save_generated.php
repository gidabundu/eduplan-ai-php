<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_permission('manage_saved');
csrf_required();
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$type = is_string($input['type'] ?? null) ? $input['type'] : '';
$title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
$meta = is_string($input['meta'] ?? null) ? trim($input['meta']) : null;
$content = is_string($input['content'] ?? null) ? trim($input['content']) : '';

if ($type === '' || $title === '' || $content === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing content to save.']);
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare('INSERT INTO generated_items (type, title, meta, content) VALUES (?, ?, ?, ?)');
$stmt->execute([$type, $title, $meta, $content]);

echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
