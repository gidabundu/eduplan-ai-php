<?php
/**
 * Best-effort text extraction for uploaded resources, with zero external
 * dependencies (no Composer packages required).
 *
 * Returns ['text' => string|null, 'note' => string|null]
 * - text: extracted plain text (truncated to a safe length for prompts)
 * - note: a human-readable note when extraction was partial/unsupported
 */
function extract_resource_text(string $tmpPath, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $maxChars = 20000;

    try {
        switch ($ext) {
            case 'txt':
            case 'md':
                $text = file_get_contents($tmpPath);
                return finish_extraction($text, $maxChars, null);

            case 'docx':
                return extract_docx($tmpPath, $maxChars);

            case 'pdf':
                return extract_pdf($tmpPath, $maxChars);

            case 'doc':
                return ['text' => null, 'note' => 'Legacy .doc files are not supported for text extraction — please upload .docx, .txt, or paste the text directly.'];

            default:
                return ['text' => null, 'note' => "Text extraction isn't supported for .$ext files — the file was saved, but the AI will only see its title and subject."];
        }
    } catch (Throwable $e) {
        return ['text' => null, 'note' => 'Automatic extraction failed for this file. The file was saved, but the AI will only see its title and subject.'];
    }
}

/** strlen/substr that work whether or not the mbstring extension is enabled. */
function text_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function text_cut(string $s, int $len): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $len, 'UTF-8') : substr($s, 0, $len);
}

function finish_extraction(?string $text, int $maxChars, ?string $note): array
{
    if ($text === null || trim($text) === '') {
        return ['text' => null, 'note' => $note ?? 'No readable text was found in this file.'];
    }
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);
    $truncatedNote = null;
    if (text_len($text) > $maxChars) {
        $text = text_cut($text, $maxChars);
        $truncatedNote = 'Only the first portion of this file is used as context (it was long).';
    }
    return ['text' => $text, 'note' => $note ?? $truncatedNote];
}

/** .docx is a zip archive; word/document.xml holds the text in <w:t> runs. */
function extract_docx(string $tmpPath, int $maxChars): array
{
    if (!class_exists('ZipArchive')) {
        return ['text' => null, 'note' => 'PHP\'s zip extension is not enabled, so .docx text could not be extracted.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) {
        return ['text' => null, 'note' => 'Could not open this .docx file for extraction.'];
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        return ['text' => null, 'note' => 'This .docx file did not contain a readable document.xml.'];
    }
    // Preserve paragraph breaks, then strip remaining XML tags.
    $xml = str_replace('</w:p>', "</w:p>\n", $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return finish_extraction($text, $maxChars, null);
}

/**
 * Minimal pure-PHP PDF text extraction. Works reasonably well for simple,
 * text-based PDFs (most generated/exported documents). Scanned/image-only
 * PDFs and heavily compressed/encrypted PDFs will not extract cleanly —
 * in that case we fail gracefully with a note.
 */
function extract_pdf(string $tmpPath, int $maxChars): array
{
    $raw = file_get_contents($tmpPath);
    if ($raw === false) {
        return ['text' => null, 'note' => 'Could not read this PDF file.'];
    }

    $externalText = extract_pdf_external($tmpPath);
    if ($externalText !== null) {
        return finish_extraction(
            $externalText,
            $maxChars,
            'Extracted from PDF with local PDF/OCR tools.'
        );
    }

    $text = '';
    // Find every stream...endstream block and try to inflate it (most PDF
    // content streams use Flate/zlib compression).
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches)) {
        foreach ($matches[1] as $streamData) {
            $decoded = @gzuncompress($streamData);
            if ($decoded === false) {
                // Not compressed (or not zlib) — try the raw bytes as-is.
                $decoded = $streamData;
            }
            $text .= extract_pdf_text_operators($decoded);
        }
    }

    if (trim($text) === '') {
        return ['text' => null, 'note' => 'This PDF could not be read automatically (it may be scanned or image-based). The file was saved — try pasting the text directly instead.'];
    }

    return finish_extraction($text, $maxChars, 'Extracted automatically from PDF — formatting and accuracy may be imperfect.');
}

function find_external_tool(string $name, array $knownPaths): ?string
{
    $which = trim((string)shell_exec('where ' . escapeshellarg($name) . ' 2>nul'));
    if ($which !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $which);
        if (!empty($lines[0]) && is_file($lines[0])) {
            return $lines[0];
        }
    }
    foreach ($knownPaths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function run_external_command(string $command): array
{
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);
    return ['text' => implode("\n", $output), 'exit' => $exitCode];
}

function extract_pdf_external(string $tmpPath): ?string
{
    $pdftotext = find_external_tool('pdftotext.exe', [
        'C:\\Users\\maria\\AppData\\Local\\Microsoft\\WinGet\\Packages\\oschwartz10612.Poppler_Microsoft.Winget.Source_8wekyb3d8bbwe\\poppler-25.07.0\\Library\\bin\\pdftotext.exe',
        'C:\\Program Files\\Poppler\\bin\\pdftotext.exe',
    ]);

    if ($pdftotext) {
        $result = run_external_command(escapeshellarg($pdftotext) . ' -enc UTF-8 ' . escapeshellarg($tmpPath) . ' -');
        if ($result['exit'] === 0 && trim($result['text']) !== '') {
            return trim($result['text']);
        }
    }

    $pdftoppm = find_external_tool('pdftoppm.exe', [
        'C:\\Users\\maria\\AppData\\Local\\Microsoft\\WinGet\\Packages\\oschwartz10612.Poppler_Microsoft.Winget.Source_8wekyb3d8bbwe\\poppler-25.07.0\\Library\\bin\\pdftoppm.exe',
        'C:\\Program Files\\Poppler\\bin\\pdftoppm.exe',
    ]);
    $tesseract = find_external_tool('tesseract.exe', [
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Users\\maria\\AppData\\Local\\Programs\\Tesseract-OCR\\tesseract.exe',
    ]);
    if (!$pdftoppm || !$tesseract) {
        return null;
    }

    $workDir = sys_get_temp_dir() . '\\eduplan_ocr_' . bin2hex(random_bytes(6));
    if (!mkdir($workDir, 0700, true)) {
        return null;
    }

    $prefix = $workDir . '\\page';
    run_external_command(escapeshellarg($pdftoppm) . ' -png -r 150 -f 1 -l 10 ' . escapeshellarg($tmpPath) . ' ' . escapeshellarg($prefix));

    $text = '';
    $pages = glob($workDir . '\\page-*.png');
    if (is_array($pages)) {
        sort($pages, SORT_NATURAL);
        foreach ($pages as $page) {
            $result = run_external_command(escapeshellarg($tesseract) . ' ' . escapeshellarg($page) . ' stdout -l eng');
            if ($result['exit'] === 0) {
                $text .= trim($result['text']) . "\n\n";
            }
            @unlink($page);
        }
    }
    @rmdir($workDir);

    return trim($text) !== '' ? trim($text) : null;
}

/** Pulls text shown via Tj / TJ operators out of a decoded PDF content stream. */
function extract_pdf_text_operators(string $stream): string
{
    $out = '';
    // (text) Tj   and   (text) '    operators
    if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*T[jJ\']/', $stream, $m)) {
        foreach ($m[0] as $chunk) {
            if (preg_match('/\((.*)\)/s', $chunk, $inner)) {
                $out .= pdf_unescape($inner[1]) . ' ';
            }
        }
    }
    // [ (text) -200 (text) ... ] TJ  arrays
    if (preg_match_all('/\[((?:[^\[\]])*)\]\s*TJ/', $stream, $m)) {
        foreach ($m[1] as $arrayBody) {
            if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $arrayBody, $parts)) {
                foreach ($parts[0] as $p) {
                    $inner = substr($p, 1, -1);
                    $out .= pdf_unescape($inner);
                }
                $out .= ' ';
            }
        }
    }
    return $out . "\n";
}

function pdf_unescape(string $s): string
{
    $s = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);
    $s = preg_replace('/\\\\n/', "\n", $s);
    return $s;
}
