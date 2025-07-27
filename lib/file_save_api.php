<?php // lib/file_save_api.php
session_start();
// Strikte Login-Prüfung
if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/../helpers.php';
validate_csrf_token(); // CSRF-Schutz

$filepath = $_POST['filepath'] ?? null;
$content = $_POST['content'] ?? null;
$createBackup = isset($_POST['backup']) && $_POST['backup'] === '1';

if (!$filepath || $content === null) {
    echo json_encode(['status' => 'error', 'message' => 'Fehlende Parameter.']);
    exit;
}

// Sicherheitsüberprüfung
require_once __DIR__ . '/../config/config.php';
$baseDir = realpath($ROOT_PATH);
$realFilepath = realpath($filepath);

if ($realFilepath === false || strpos($realFilepath, $baseDir) !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Zugriff auf die Datei verweigert.']);
    exit;
}

if (!is_file($realFilepath) || !is_writable($realFilepath)) {
    echo json_encode(['status' => 'error', 'message' => 'Datei nicht gefunden oder nicht beschreibbar.']);
    exit;
}

// Backup erstellen, falls gewünscht
if ($createBackup) {
    $counter = 1;
    $backupPath = $realFilepath . '.srbkup';
    while (file_exists($backupPath)) {
        $counter++;
        $backupPath = $realFilepath . '.srbkup' . $counter;
    }
    if (!@copy($realFilepath, $backupPath)) {
        echo json_encode(['status' => 'error', 'message' => 'Backup konnte nicht erstellt werden.']);
        exit;
    }
}

// Inhalt speichern
if (file_put_contents($realFilepath, $content) !== false) {
    $message = $createBackup ? 'Backup erstellt und Datei erfolgreich gespeichert.' : 'Datei erfolgreich gespeichert.';
    echo json_encode(['status' => 'success', 'message' => $message]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Datei konnte nicht geschrieben werden.']);
}
?>