<?php
// lib/settings_api.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../helpers.php';
$settings = loadSettings();

// Nur nicht-sensible Daten an das Frontend senden
$safeSettings = [
    'llm_provider' => $settings['llm_provider'] ?? 'gemini'
];

echo json_encode($safeSettings);
?>