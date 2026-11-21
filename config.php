<?php
/**
 * EduPlan AI — Configuration
 * Edit the values below for your environment.
 */

// Leave unset to prefer Gemini and fall back to Anthropic when only its key exists.
// Set to 'google' or 'anthropic' to explicitly select a provider.
define('AI_PROVIDER', getenv('AI_PROVIDER') ?: '');

$localSecrets = [];
$localSecretsPath = __DIR__ . '/config.local.php';
if (is_file($localSecretsPath)) {
    $localSecrets = require $localSecretsPath;
    if (!is_array($localSecrets)) {
        throw new UnexpectedValueException('config.local.php must return an array.');
    }
}

// Anthropic configuration. Get a key at https://console.anthropic.com/
$anthropicApiKey = getenv('ANTHROPIC_API_KEY');
if ($anthropicApiKey === false || $anthropicApiKey === '') {
    $anthropicApiKey = $localSecrets['ANTHROPIC_API_KEY'] ?? '';
}
if (!is_string($anthropicApiKey)) {
    throw new UnexpectedValueException('ANTHROPIC_API_KEY must be a string.');
}
define('ANTHROPIC_API_KEY', $anthropicApiKey);
define('ANTHROPIC_MODEL', getenv('ANTHROPIC_MODEL') ?: 'claude-sonnet-4-20250514');
define('ANTHROPIC_MAX_TOKENS', getenv('ANTHROPIC_MAX_TOKENS') ?: 32000);

// Google Gemini configuration. Get a key at https://aistudio.google.com/app/apikey
// Set GOOGLE_API_KEY in the environment; keep API keys out of source control.
$googleApiKey = getenv('GOOGLE_API_KEY');
if ($googleApiKey === false || $googleApiKey === '') {
    $googleApiKey = $localSecrets['GOOGLE_API_KEY'] ?? '';
}
if (!is_string($googleApiKey)) {
    throw new UnexpectedValueException('GOOGLE_API_KEY must be a string.');
}
define('GOOGLE_API_KEY', $googleApiKey);
define('GOOGLE_MODEL', getenv('GOOGLE_MODEL') ?: 'gemini-flash-lite-latest');
define('GOOGLE_FALLBACK_MODEL', getenv('GOOGLE_FALLBACK_MODEL') ?: 'gemini-flash-lite-latest');
define('GOOGLE_TIMEOUT_SECONDS', max(30, (int)(getenv('GOOGLE_TIMEOUT_SECONDS') ?: 60)));

// SMTP delivery. Leave SMTP_HOST empty to disable email delivery.
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_ENCRYPTION', strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls'));
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'EduPlan AI');
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost/eduplan-ai-php', '/'));

// MySQL / MariaDB connection. These are the common XAMPP/default-PHP-stack
// values — change them to match your server. Environment variables override
// these if set (handy for shared hosting or Docker).
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'eduplan_ai');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Where uploaded syllabus files are stored.
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', '1');
