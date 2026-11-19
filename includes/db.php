<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<div style="font-family:monospace;background:#000;color:#f87171;padding:24px;white-space:pre-wrap;">'
            . "Could not connect to MySQL.\n\n"
            . "Checked: host=" . htmlspecialchars(DB_HOST) . " port=" . htmlspecialchars(DB_PORT)
            . " db=" . htmlspecialchars(DB_NAME) . " user=" . htmlspecialchars(DB_USER) . "\n\n"
            . "1. Make sure MySQL/MariaDB is running.\n"
            . "2. Create the database: CREATE DATABASE " . htmlspecialchars(DB_NAME) . ";\n"
            . "3. Set your credentials in config.php (or DB_HOST/DB_USER/DB_PASS/DB_NAME env vars).\n"
            . "4. You can also import schema.sql directly if you'd rather not rely on auto-creation.\n\n"
            . "Driver message: " . htmlspecialchars($e->getMessage())
            . '</div>';
        exit;
    }

    ensure_schema($pdo);

    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS auth_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'Admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        email VARCHAR(190) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'Teacher',
        department VARCHAR(190) DEFAULT NULL,
        classes INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // A "resource" is either a syllabus or a book/reference text. Both are
    // uploaded the same way by a user, and both can be used by the AI
    // Generator as grounding material — nothing in this table is seeded,
    // every row only exists because someone uploaded it.
    $pdo->exec("CREATE TABLE IF NOT EXISTS resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'syllabus',
        subject VARCHAR(190) DEFAULT NULL,
        class VARCHAR(190) DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Processing',
        file_path VARCHAR(500) DEFAULT NULL,
        extracted_text MEDIUMTEXT DEFAULT NULL,
        extraction_note VARCHAR(255) DEFAULT NULL,
        uploaded_by VARCHAR(190) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Holds AI Generator output the user explicitly chose to save (via the
    // "Save" button on a result). Nothing is written here automatically.
    $pdo->exec("CREATE TABLE IF NOT EXISTS generated_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(30) NOT NULL,
        title VARCHAR(255) NOT NULL,
        meta VARCHAR(255) DEFAULT NULL,
        content MEDIUMTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY,
        name VARCHAR(190) DEFAULT NULL,
        email VARCHAR(190) DEFAULT NULL,
        school VARCHAR(190) DEFAULT NULL,
        language VARCHAR(50) NOT NULL DEFAULT 'English',
        notifications TINYINT(1) DEFAULT 1,
        ai_suggestions TINYINT(1) DEFAULT 1,
        weekly_report TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS language VARCHAR(50) NOT NULL DEFAULT 'English'");
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS notifications TINYINT(1) DEFAULT 1");
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS weekly_report TINYINT(1) DEFAULT 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body MEDIUMTEXT NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        delivered_at DATETIME DEFAULT NULL,
        error_message VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mail_pending (delivered_at, attempts)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Exactly one settings row, created empty — the user fills it in via
    // Settings. Not sample data: every field starts blank/default.
    $exists = $pdo->query('SELECT COUNT(*) AS c FROM settings WHERE id = 1')->fetch();
    if ((int)($exists['c'] ?? 0) === 0) {
        $pdo->exec('INSERT INTO settings (id) VALUES (1)');
    }
}
