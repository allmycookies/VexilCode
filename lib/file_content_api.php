<?php
session_start();
// Strikte Login-Prüfung
if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$filepath = $_POST['filepath'] ?? null;

if (!$filepath) {
    http_response_code(400);
    echo json_encode(['error' => 'Filepath not provided.']);
    exit;
}

// Sicherheitsüberprüfung: Verhindere den Zugriff auf Dateien außerhalb des Projektordners
$baseDir = realpath(__DIR__ . '/../');
$realFilepath = realpath($filepath);

if ($realFilepath === false || strpos($realFilepath, $baseDir) !== 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access to the requested file is denied.', 'path' => $filepath]);
    exit;
}

if (!is_file($realFilepath) || !is_readable($realFilepath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found or not readable.', 'path' => $realFilepath]);
    exit;
}

$content = file_get_contents($realFilepath);

if ($content === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not read file content.', 'path' => $realFilepath]);
    exit;
}

echo json_encode(['filepath' => $realFilepath, 'content' => $content]);
