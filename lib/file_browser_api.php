<?php // lib/file_browser_api.php
session_start();
if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']); // Geändert zu 'status' und 'message' für Konsistenz
    exit;
}
header('Content-Type: application/json');

require_once __DIR__ . '/../helpers.php';
validate_csrf_token(); // CSRF-Schutz für diese POST-Anfrage

require_once __DIR__ . '/../config/config.php';

$baseDir = realpath($ROOT_PATH);

function is_safe_path_browser($path, $baseDir) {
    // Sicherstellen, dass der Pfad existiert und ein Verzeichnis ist, bevor realpath aufgerufen wird
    if (!file_exists($path) || !is_dir($path)) {
        return false;
    }
    $realPath = realpath($path);
    // Überprüfen, ob realpath erfolgreich war und der Pfad innerhalb des Basisverzeichnisses liegt
    return $realPath !== false && strpos($realPath, $baseDir) === 0;
}

$path = $_POST['path'] ?? null;

// Wenn kein Pfad übergeben wird oder der Pfad nicht existiert/nicht sicher ist, auf baseDir zurückfallen
if (!$path || !is_safe_path_browser($path, $baseDir)) {
    $path = $baseDir;
}

$response = [
    'path' => $path,
    'parent' => (realpath($path) !== $baseDir) ? dirname($path) : null,
    'directories' => []
];

try {
    $items = scandir($path);
    $dirs = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $path . DIRECTORY_SEPARATOR . $item;
        // Nur lesbare Verzeichnisse hinzufügen
        if (is_dir($fullPath) && is_readable($fullPath)) {
            $dirs[] = $item;
        }
    }
    sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
    $response['directories'] = $dirs;

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Konnte Verzeichnis nicht lesen: ' . $e->getMessage(), 'path' => $path]); // Konsistente Fehlermeldung
    exit;
}
?>
