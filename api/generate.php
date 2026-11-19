<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$apiResponseSent = false;

function api_json_error(string $message, int $code): void
{
    global $apiResponseSent;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    $apiResponseSent = true;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    error_log('AI generation endpoint exception: ' . $exception);
    api_json_error('The server encountered an error while preparing the AI request. Check the PHP error log for details.', 500);
});

register_shutdown_function(static function (): void {
    global $apiResponseSent;

    if ($apiResponseSent) {
        return;
    }
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('AI generation endpoint fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        api_json_error('The server encountered an internal error while generating the response. Check the PHP error log for details.', 500);
    }
});

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();
csrf_required();

ini_set('display_errors', '0');
ini_set('log_errors', '1');

function fail(string $msg, int $code = 400): void
{
    api_json_error($msg, $code);
    exit;
}

function s($v, $default = '')
{
    return is_string($v) && trim($v) !== '' ? trim($v) : $default;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || empty($input['type'])) {
    fail('Invalid request.');
}

$type = $input['type'];
$form = is_array($input['form'] ?? null) ? $input['form'] : [];
$resourceId = (int)($input['resourceId'] ?? 0);
$webSearch = !empty($input['webSearch']);

// --- Optional grounding material from the Resource Library ---
$groundingBlock = '';
if ($resourceId > 0) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT title, type, extracted_text FROM resources WHERE id = ? AND extracted_text IS NOT NULL');
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch();
    if ($resource) {
        $kind = $resource['type'] === 'book' ? 'book excerpt' : 'syllabus';
        $groundingBlock = "\n\nUse the following uploaded $kind as source material. Base facts, terminology, and structure on it where relevant, "
            . "and prefer it over general knowledge if there's a conflict. Source: \"{$resource['title']}\"\n---\n"
            . $resource['extracted_text'] . "\n---\n";
    }
}

$webSearchNote = $webSearch
    ? "\n\nYou may also use web search to pull in current, accurate supporting information beyond the material above if it would improve the result."
    : '';

switch ($type) {
    case 'lesson-plan':
        $classLevel = s($form['classLevel'] ?? null);
        $subject = s($form['subject'] ?? null);
        $term = s($form['term'] ?? null);
        $topic = s($form['topic'] ?? null);
        if ($classLevel === '' || $subject === '' || $term === '' || $topic === '') {
            fail('Enter a class level, subject, term, and topic.');
        }
        $prompt = "You are the AI engine inside a school lesson-planning tool called EduPlan AI.\n"
            . "Create a lesson plan for:\nClass level: $classLevel\nSubject: $subject\nTerm: $term\nTopic: $topic"
            . $groundingBlock . $webSearchNote
            . "\n\nThe selected subject, \"$subject\", is authoritative. Keep the lesson, examples, and activities within this subject."
            . "\n\n"
            . "Respond with ONLY raw JSON (no markdown fences, no commentary) matching exactly this shape:\n"
            . '{"duration":"e.g. 40 mins","introduction":"2-3 sentence hook and framing for the intro segment, referencing the topic","teacherActivities":["short imperative teaching step","...","..."],"studentActivities":["short imperative activity for students","...","..."]}' . "\n"
            . "Return exactly 3 teacherActivities and 3 studentActivities. Keep every string under 200 characters. Do not include any text outside the JSON object.";
        $expectJson = true;
        break;

    case 'assessment':
        $classLevel = s($form['classLevel'] ?? null);
        $subject = s($form['subject'] ?? null);
        $topic = s($form['topic'] ?? null);
        if ($classLevel === '' || $subject === '' || $topic === '') {
            fail('Enter a class level, subject, and topic.');
        }
        $assessType = s($form['assessType'] ?? null, 'Test');
        $difficulty = s($form['difficulty'] ?? null, 'Standard');
        $mcqCount = max(0, min(20, (int)($form['mcqCount'] ?? 4)));
        $essayCount = max(0, min(10, (int)($form['essayCount'] ?? 2)));
        $prompt = "You are the AI engine inside a school assessment generator called EduPlan AI.\n"
            . "Create an assessment for:\nClass level: $classLevel\nSubject: $subject\nTopic: $topic\nAssessment type: $assessType\nDifficulty: $difficulty\n"
            . "Include exactly $mcqCount multiple-choice questions and $essayCount open-ended questions."
            . $groundingBlock . $webSearchNote
            . "\n\nThe selected subject, \"$subject\", is authoritative. Keep all questions and answer options within this subject."
            . "\n\n"
            . "Respond with ONLY raw JSON (no markdown fences, no commentary) matching exactly:\n"
            . '{"title":"short assessment title","estimatedTime":"e.g. 45 mins","mcqs":[{"question":"...","options":[{"label":"A","text":"..."},{"label":"B","text":"..."},{"label":"C","text":"..."},{"label":"D","text":"..."}],"correct":"B"}],"essays":["open ended question text","..."]}' . "\n"
            . "Keep every string under 150 characters. Do not include any text outside the JSON object.";
        $expectJson = true;
        break;

    case 'forecast':
        $year = s($form['year'] ?? null);
        $term = s($form['term'] ?? null);
        $className = s($form['className'] ?? null);
        $subject = s($form['subject'] ?? null);
        $source = s($form['source'] ?? null);
        if ($year === '' || $term === '' || $className === '' || $subject === '' || $source === '') {
            fail('Enter the academic year, term, class, subject, and syllabus source.');
        }
        $weeks = max(2, min(6, (int)($form['weeks'] ?? 4)));
        $lessonsPerWeek = max(1, min(5, (int)($form['lessonsPerWeek'] ?? 3)));
        $startDate = s($form['startDate'] ?? null, date('Y-m-d'));
        $prompt = "You are the AI engine inside a school term-forecast generator called EduPlan AI.\n"
            . "Build a teaching forecast for:\nAcademic year: $year\nTerm: $term\nClass: $className\nSubject: $subject\nSyllabus source: $source\n"
            . "Duration: $weeks weeks, $lessonsPerWeek lessons per week\nStart date: $startDate"
            . $groundingBlock . $webSearchNote
            . "\n\nThe selected subject, \"$subject\", is authoritative. Keep every week and objective within this subject."
            . "\n\n"
            . "Respond with ONLY raw JSON (no markdown fences, no commentary) matching exactly:\n"
            . '{"insight":"2-3 sentence summary written in first person as the AI assistant, explaining how syllabus content was distributed across the weeks","weeks":[{"week":1,"date":"e.g. 02 Sep","topic":"...","subtopic":"...","objective":"short learning objective"}]}' . "\n"
            . "Return exactly $weeks week entries, numbered sequentially starting at 1, with dates increasing weekly from the start date. Keep every string under 130 characters. Do not include any text outside the JSON object.";
        $expectJson = true;
        break;

    case 'lesson-note':
        $classLevel = s($form['classLevel'] ?? null);
        $subject = s($form['subject'] ?? null);
        $topic = s($form['topic'] ?? null);
        $subtopic = s($form['subtopic'] ?? null);
        $duration = s($form['duration'] ?? null);
        $tone = s($form['tone'] ?? null);
        if ($classLevel === '' || $subject === '' || $topic === '' || $subtopic === '' || $duration === '' || $tone === '') {
            fail('Enter class level, subject, topic, sub-topic, duration, and tone.');
        }
        $prompt = "You are the AI engine inside a school lesson-note generator called EduPlan AI.\n"
            . "Write a lesson note for:\nClass level: $classLevel\nSubject: $subject\nTopic focus: $topic\nSub-topic / objective: $subtopic\nDuration: $duration\nTone: $tone"
            . $groundingBlock . $webSearchNote
            . "\n\nThe selected subject, \"$subject\", is authoritative. Keep the entire note, terminology, and worked examples within this subject."
            . "\n\n"
            . "Respond with ONLY raw JSON (no markdown fences, no commentary) matching exactly:\n"
            . '{"sections":[{"heading":"1. Introduction (Contextual Hook)","body":"2-4 sentence paragraph"},{"heading":"2. Definition & Key Concept","body":"2-4 sentence paragraph, may include one short formula written in plain text like ax^2+bx+c=0 if relevant"},{"heading":"3. Worked Example","body":"..."},{"heading":"4. Summary & Homework","body":"..."}]}' . "\n"
            . "Keep each body under 380 characters. Do not include any text outside the JSON object.";
        $expectJson = true;
        break;

    case 'custom':
        $request = s($form['request'] ?? null, '');
        if ($request === '') {
            fail('Describe what you want the AI to do.');
        }
        $prompt = "You are the AI engine inside a school management tool called EduPlan AI, able to help with any curriculum, "
            . "classroom, or administrative task a teacher or administrator asks for.\n\nRequest: $request"
            . $groundingBlock . $webSearchNote
            . "\n\nFollow the subject explicitly requested. Do not silently replace it with another subject. Use other subjects only where they are genuinely needed to answer the request."
            . "\n\nRespond directly and usefully with well-formatted plain text (you may use line breaks and simple markdown-style "
            . "headings/bullets). Do not wrap the response in JSON.";
        $expectJson = false;
        break;

    default:
        fail('Unknown generator type.');
}

$anthropicKey = trim((string)(defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : ''));
$googleKey = trim((string)(defined('GOOGLE_API_KEY') ? GOOGLE_API_KEY : getenv('GOOGLE_API_KEY')));
$provider = strtolower(trim((string)(defined('AI_PROVIDER') ? AI_PROVIDER : (getenv('AI_PROVIDER') ?: ''))));

if (!in_array($provider, ['google', 'anthropic'], true)) {
    $provider = '';
}

if ($provider === '') {
    if ($googleKey !== '' && !str_contains($googleKey, 'REPLACE-ME')) {
        $provider = 'google';
    } elseif ($anthropicKey !== '' && !str_contains($anthropicKey, 'REPLACE-ME')) {
        $provider = 'anthropic';
    } else {
        $provider = 'google';
    }
}

if ($provider === 'google') {
    if ($googleKey === '' || str_contains($googleKey, 'REPLACE-ME')) {
        fail('No Google API key configured yet. Set the GOOGLE_API_KEY environment variable.', 500);
    }

    $model = defined('GOOGLE_MODEL') ? GOOGLE_MODEL : (getenv('GOOGLE_MODEL') ?: 'gemini-3.8-flash');
    $fallbackModel = defined('GOOGLE_FALLBACK_MODEL')
        ? GOOGLE_FALLBACK_MODEL
        : (getenv('GOOGLE_FALLBACK_MODEL') ?: 'gemini-3.5-flash-lite');
    $models = array_values(array_unique([$model, $fallbackModel]));

    $body = [
        'contents' => [[
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ]],
    ];
    if ($expectJson) {
        $body['generationConfig'] = ['responseMimeType' => 'application/json'];
    }

    $text = '';
    foreach ($models as $modelIndex => $requestModel) {
            $attempts = 1;
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($requestModel) . ':generateContent';
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => (int)GOOGLE_TIMEOUT_SECONDS,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $googleKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($body),
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);
            $curlErr = curl_error($ch);
            $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
            curl_close($ch);

            if ($response === false) {
                if ($curlErrno === CURLE_OPERATION_TIMEDOUT && $connectTime == 0.0) {
                    fail('Could not connect to Google Gemini within 15 seconds. Check that Apache/PHP can make outbound HTTPS connections.', 504);
                }
                if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
                    fail('Google Gemini did not finish generating within ' . (int)GOOGLE_TIMEOUT_SECONDS . ' seconds. Try again, or use a shorter request or reference resource.', 504);
                }
                fail('Could not reach the Google Gemini API: ' . $curlErr, 502);
            }

            $data = json_decode($response, true);
            if ($httpCode === 429 || $httpCode === 503) {
                $apiMsg = $data['error']['message'] ?? ($data['error']['status'] ?? 'The model is temporarily busy.');
                if ($attempt + 1 < $attempts) {
                    usleep(750000);
                    continue;
                }
                if ($modelIndex + 1 < count($models)) {
                    break;
                }
                fail('Google Gemini is temporarily busy on the primary and fallback models. Please try again later. API message: ' . $apiMsg, 503);
            }

            if ($httpCode >= 400) {
                $apiMsg = $data['error']['message'] ?? ($data['error']['status'] ?? 'Unknown API error.');
                fail('Google API error: ' . $apiMsg, 502);
            }

            foreach (($data['candidates'] ?? []) as $candidate) {
                foreach (($candidate['content']['parts'] ?? []) as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }
            }
            break 2;
        }
    }
} else {
    if ($anthropicKey === '' || str_contains($anthropicKey, 'REPLACE-ME')) {
        fail('No Anthropic API key configured yet. Add yours in config.php or set the ANTHROPIC_API_KEY environment variable.', 500);
    }

    if (!str_starts_with($anthropicKey, 'sk-ant-')) {
        fail('Anthropic API key format looks invalid. It should start with sk-ant- and be saved in config.php or the ANTHROPIC_API_KEY environment variable.', 500);
    }

    $body = [
        'model' => ANTHROPIC_MODEL,
        'max_tokens' => (int)ANTHROPIC_MAX_TOKENS,
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ];
    if ($webSearch) {
        $body['tools'] = [['type' => 'web_search_20250305', 'name' => 'web_search']];
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $anthropicKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 90,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        fail('Could not reach the Anthropic API: ' . $curlErr, 502);
    }

    $data = json_decode($response, true);

    if ($httpCode >= 400) {
        $apiMsg = $data['error']['message'] ?? 'Unknown API error.';
        fail('Anthropic API error: ' . $apiMsg, 502);
    }

    // Web search responses interleave text/tool_use/tool_result blocks — we only
    // want the assistant's text blocks, concatenated in order.
    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }
}

if (!$expectJson) {
    echo json_encode(['result' => ['text' => trim($text)]]);
    exit;
}

$cleaned = trim(str_replace(['```json', '```'], '', $text));
$start = strpos($cleaned, '{');
$end = strrpos($cleaned, '}');
if ($start === false || $end === false) {
    fail('The model did not return valid JSON.', 502);
}
$jsonSlice = substr($cleaned, $start, $end - $start + 1);
$result = json_decode($jsonSlice, true);
if ($result === null) {
    fail('Could not parse the model response as JSON.', 502);
}

echo json_encode(['result' => $result]);
