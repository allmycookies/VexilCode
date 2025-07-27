<?php
/**
 * config.php
 * Zentrale Konfiguration für die VexilCode Suite.
 */

// WICHTIG: Definieren Sie hier den absoluten Pfad zum Stammverzeichnis,
// in dem Sie arbeiten möchten. Alle Dateioperationen werden auf dieses
// Verzeichnis und seine Unterordner beschränkt.
//
// BEISPIELE:
// $ROOT_PATH = $_SERVER['DOCUMENT_ROOT']; // Das Hauptverzeichnis der Domain.
// $ROOT_PATH = '/www/xxxx/xxxx/';
// $ROOT_PATH = realpath(__DIR__ . '/../..'); // Eine Ebene über dem Tool-Verzeichnis.

$ROOT_PATH = $_SERVER['DOCUMENT_ROOT']; // Standard: Das Verzeichnis, in dem sich das VexilCode befindet.
                                       // ÄNDERN SIE DIESEN PFAD NACH IHREN BEDÜRFNISSEN.


// WICHTIG: Definieren Sie hier den Pfad zum Document Root Ihres Webservers.
// Dies ist notwendig, um absolute Serverpfade in aufrufbare Web-URLs umzuwandeln.
// In den meisten Fällen ist `$_SERVER['DOCUMENT_ROOT']` die korrekte Einstellung.
$WEB_ROOT_PATH = $_SERVER['DOCUMENT_ROOT'];


// Überprüfen, ob die Pfade existieren, um Fehler zu vermeiden.
if (!is_dir($ROOT_PATH)) {
    die("FATALER FEHLER: Der definierte ROOT_PATH ('" . htmlspecialchars($ROOT_PATH) . "') in config/config.php existiert nicht oder ist kein Verzeichnis.");
}
if (!is_dir($WEB_ROOT_PATH)) {
    die("FATALER FEHLER: Der definierte WEB_ROOT_PATH ('" . htmlspecialchars($WEB_ROOT_PATH) . "') in config/config.php existiert nicht oder ist kein Verzeichnis.");
}
