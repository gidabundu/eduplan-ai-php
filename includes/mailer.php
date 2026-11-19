<?php
require_once __DIR__ . '/../config.php';

function queue_mail(PDO $pdo, string $recipient, string $subject, string $body): void
{
    $recipient = trim($recipient);
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || trim($subject) === '') {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO mail_queue (recipient, subject, body) VALUES (?, ?, ?)');
    $stmt->execute([$recipient, trim($subject), $body]);
}

function smtp_read_line($socket): string
{
    $line = fgets($socket, 1024);
    if ($line === false) {
        throw new RuntimeException('SMTP connection closed unexpectedly.');
    }
    return rtrim($line, "\r\n");
}

function smtp_expect($socket, array $codes): void
{
    $response = smtp_read_line($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP error: ' . $response);
    }
}

function smtp_command($socket, string $command, array $codes): void
{
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $codes);
}

function smtp_send(string $recipient, string $subject, string $body): void
{
    if (SMTP_HOST === '') {
        throw new RuntimeException('SMTP is not configured.');
    }
    $host = SMTP_ENCRYPTION === 'ssl' ? 'ssl://' . SMTP_HOST : SMTP_HOST;
    $socket = @fsockopen($host, SMTP_PORT, $errno, $error, 20);
    if (!$socket) {
        throw new RuntimeException('Could not connect to SMTP: ' . $error);
    }
    stream_set_timeout($socket, 20);
    smtp_expect($socket, [220]);

    smtp_command($socket, 'EHLO eduplan-ai.local', [250]);
    if (SMTP_ENCRYPTION === 'tls') {
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not enable SMTP TLS.');
        }
        smtp_command($socket, 'EHLO eduplan-ai.local', [250]);
    }
    if (SMTP_USER !== '') {
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode(SMTP_USER), [334]);
        smtp_command($socket, base64_encode(SMTP_PASS), [235]);
    }

    $from = SMTP_FROM_EMAIL !== '' ? SMTP_FROM_EMAIL : SMTP_USER;
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid SMTP_FROM_EMAIL or SMTP_USER is required.');
    }
    smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250]);
    smtp_command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
    smtp_command($socket, 'DATA', [354]);

    $encodedBody = quoted_printable_encode(str_replace(["\r\n", "\r"], "\n", $body));
    $subject = preg_replace('/[\r\n]+/', ' ', trim($subject));
    $recipientName = '';
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = preg_replace('/\n\./', "\n..", $body);
    $headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . $from . '>',
        'To: <' . $recipient . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        'Date: ' . gmdate('r'),
    ];
    fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $encodedBody) . "\r\n.\r\n");
    smtp_expect($socket, [250]);
    smtp_command($socket, 'QUIT', [221]);
    fclose($socket);
}
