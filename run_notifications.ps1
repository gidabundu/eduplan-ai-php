$ErrorActionPreference = "Stop"
$php = "C:\xampp\php\php.exe"
$app = "C:\xampp\htdocs\eduplan-ai-php"

& $php "$app\weekly_report.php"
& $php "$app\mail_worker.php"
