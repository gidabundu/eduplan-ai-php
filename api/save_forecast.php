<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_permission('manage_saved');
csrf_required();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid forecast data.']);
    exit;
}

$year = is_string($input['year'] ?? null) ? trim($input['year']) : '';
$term = is_string($input['term'] ?? null) ? trim($input['term']) : '';
$className = is_string($input['class_name'] ?? null) ? trim($input['class_name']) : '';
$subject = is_string($input['subject'] ?? null) ? trim($input['subject']) : '';
$weeks = $input['weeks'] ?? null;

if ($year === '' || $term === '' || $className === '' || $subject === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Academic year, term, class, and subject are required.']);
    exit;
}
if (strlen($year) > 50 || strlen($term) > 190 || strlen($className) > 190 || strlen($subject) > 190) {
    http_response_code(400);
    echo json_encode(['error' => 'Forecast details are too long.']);
    exit;
}
if (!is_array($weeks) || count($weeks) < 1 || count($weeks) > 6) {
    http_response_code(400);
    echo json_encode(['error' => 'A forecast must contain between 1 and 6 weekly items.']);
    exit;
}

$items = [];
$seenWeeks = [];
foreach ($weeks as $week) {
    if (!is_array($week)) {
        http_response_code(400);
        echo json_encode(['error' => 'A forecast week is invalid.']);
        exit;
    }

    $weekNumber = filter_var($week['week'] ?? null, FILTER_VALIDATE_INT);
    $topic = is_string($week['topic'] ?? null) ? trim($week['topic']) : '';
    $date = is_string($week['date'] ?? null) ? trim($week['date']) : '';
    $subtopic = is_string($week['subtopic'] ?? null) ? trim($week['subtopic']) : '';
    $objective = is_string($week['objective'] ?? null) ? trim($week['objective']) : '';

    if ($weekNumber === false || $weekNumber < 1 || $weekNumber > 6 || isset($seenWeeks[$weekNumber]) || $topic === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Each forecast week needs a unique week number and topic.']);
        exit;
    }
    if (strlen($topic) > 255 || strlen($date) > 50 || strlen($subtopic) > 255) {
        http_response_code(400);
        echo json_encode(['error' => 'A forecast topic or date is too long.']);
        exit;
    }

    $seenWeeks[$weekNumber] = true;
    $items[] = [
        'week' => $weekNumber,
        'date' => $date,
        'topic' => $topic,
        'subtopic' => $subtopic,
        'objective' => $objective,
    ];
}

usort($items, static function (array $a, array $b): int {
    return $a['week'] <=> $b['week'];
});

$pdo = get_db();
$user = current_user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$pdo->beginTransaction();
try {
    $planStmt = $pdo->prepare('INSERT INTO forecast_plans (academic_year, term, class_name, subject, created_by) VALUES (?, ?, ?, ?, ?)');
    $planStmt->execute([$year, $term, $className, $subject, $userId]);
    $planId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO forecast_plan_items (plan_id, week_number, lesson_date, topic, subtopic, objective) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($items as $item) {
        $itemStmt->execute([
            $planId,
            $item['week'],
            $item['date'] !== '' ? $item['date'] : null,
            $item['topic'],
            $item['subtopic'] !== '' ? $item['subtopic'] : null,
            $item['objective'] !== '' ? $item['objective'] : null,
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$user = current_user();
if ($user) {
    record_teacher_activity(
        $pdo,
        $user,
        'forecast_saved',
        'Saved forecast',
        $className,
        $subject,
        null,
        $term,
        $year
    );
}

echo json_encode(['ok' => true, 'id' => $planId]);
