<?php // lib/diff_api.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Diff.php';

validate_csrf_token();

$path = $_POST['path'] ?? null;
if (!$path || !is_dir($path)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ungültiger oder fehlender Pfad.']);
    exit;
}

// Sicherheitsüberprüfung: Pfad muss innerhalb des ROOT_PATH liegen
$realBaseDir = realpath($ROOT_PATH);
$realPath = realpath($path);
if ($realPath === false || strpos($realPath, $realBaseDir) !== 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Zugriff auf den Pfad verweigert.']);
    exit;
}

try {
    $backupFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match('/\.srbkup(\d*)$/i', $file->getFilename())) {
            // Original-Dateinamen extrahieren
            $originalName = preg_replace('/\.srbkup(\d*)$/i', '', $file->getPathname());
            
            // Backup-Nummer extrahieren (Standard ist 1 für .srbkup)
            preg_match('/\.srbkup(\d*)$/i', $file->getFilename(), $matches);
            $backupNum = empty($matches[1]) ? 1 : (int)$matches[1];

            if (!isset($backupFiles[$originalName]) || $backupNum > $backupFiles[$originalName]['num']) {
                $backupFiles[$originalName] = [
                    'path' => $file->getPathname(),
                    'num' => $backupNum
                ];
            }
        }
    }

    if (empty($backupFiles)) {
        echo json_encode(['status' => 'success', 'diffs' => []]);
        exit;
    }

    $diffResults = [];
    foreach ($backupFiles as $originalPath => $latestBackup) {
        if (!file_exists($originalPath)) {
            continue; // Originaldatei existiert nicht (mehr), kein Vergleich möglich
        }

        $originalContent = file_get_contents($originalPath);
        $backupContent = file_get_contents($latestBackup['path']);

        $diffHtml = Diff::toHtml($backupContent, $originalContent);

        $diffResults[] = [
            'file' => str_replace($realPath . DIRECTORY_SEPARATOR, '', $originalPath),
            'html' => $diffHtml
        ];
    }

    echo json_encode(['status' => 'success', 'diffs' => $diffResults]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()]);
}