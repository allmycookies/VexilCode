<?php
/**
 * ace_cleanup.php
 * * Dieses Skript reduziert die Größe der Ace-Editor-Bibliothek, indem es alle nicht
 * benötigten Dateien und Ordner in ein `_archive`-Verzeichnis verschiebt.
 * * VERSION 2: Entfernt zusätzlich die "Worker"-Dateien für eine maximale Größenreduktion.
 * * ANWENDUNG:
 * 1. Legen Sie diese Datei in das Stammverzeichnis Ihrer VexilCode-Anwendung.
 * 2. Rufen Sie sie einmalig im Browser auf.
 * 3. Löschen Sie diese Datei nach der Ausführung.
 *
 * WICHTIG: Erstellen Sie vor der Ausführung zur Sicherheit ein Backup des
 * `assets/js/ace/` Ordners.
 */

header('Content-Type: text/plain; charset=utf-8');

// --- KONFIGURATION ---

// Pfad zum Ace-Verzeichnis, das aufgeräumt werden soll.
$aceDir = __DIR__ . '/assets/js/ace/src-noconflict/';

// Name des Archiv-Ordners, der erstellt wird.
$archiveDirName = '_archive';

// Liste der Dateien, die UNBEDINGT BEHALTEN werden sollen.
// Wildcards (*) sind nicht erlaubt, die Prüfung erfolgt mit `in_array`.
$filesToKeep = [
    'ace.js',
    // Benötigtes Theme
    'theme-tomorrow_night_eighties.js',
    // Benötigte Modes (Sprachen) - Syntax Highlighting funktioniert weiterhin
    'mode-javascript.js',
    'mode-json.js',
    'mode-html.js',
    'mode-css.js',
    'mode-php.js',
    'mode-markdown.js',
    'mode-sh.js',
    'mode-sql.js',
    'mode-xml.js',
    'mode-yaml.js',
    'mode-ini.js',
    'mode-text.js', // Als Fallback
    // Wichtige Erweiterungen
    'ext-searchbox.js', // Behalten für die STRG+F Suchfunktion im Editor

    // HINWEIS: Alle 'worker-*.js' Dateien wurden bewusst entfernt, um die Größe
    // drastisch zu reduzieren. Dies deaktiviert die Live-Syntax-Prüfung
    // (Fehlerunterstreichung), aber die Syntax-Hervorhebung bleibt erhalten.
    // 'ext-language_tools.js' wurde ebenfalls entfernt, da es oft von den Workern abhängt.
];

// Liste von Ordnern, die komplett archiviert werden sollen.
$foldersToArchive = [
    'demo',
    'doc',
    'kitchen-sink',
    'tool',
    'snippets',
];

// --- SKRIPT-LOGIK (AB HIER NICHTS ÄNDERN) ---

echo "=========================================\n";
echo "Ace Editor Cleanup Skript (Aggressiv)\n";
echo "=========================================\n\n";

if (!is_dir($aceDir)) {
    die("[FEHLER] Das Ace-Verzeichnis wurde nicht gefunden: " . htmlspecialchars($aceDir) . "\nStellen Sie sicher, dass der Pfad korrekt ist.");
}

$archivePath = rtrim($aceDir, '/') . '/' . $archiveDirName;

if (!is_dir($archivePath)) {
    if (mkdir($archivePath, 0755, true)) {
        echo "[OK] Archiv-Verzeichnis erstellt: " . htmlspecialchars($archivePath) . "\n";
    } else {
        die("[FEHLER] Konnte das Archiv-Verzeichnis nicht erstellen. Bitte Berechtigungen prüfen.\n");
    }
}

echo "Starte aggressive Aufräumarbeiten (entfernt auch Worker)...\n\n";

$movedFiles = 0;
$keptFiles = 0;
$movedFolders = 0;

try {
    $items = new DirectoryIterator($aceDir);

    foreach ($items as $item) {
        if ($item->isDot() || $item->getBasename() === $archiveDirName) {
            continue;
        }

        $itemName = $item->getBasename();
        $sourcePath = $item->getPathname();
        $destinationPath = $archivePath . '/' . $itemName;

        if ($item->isDir()) {
            if (in_array($itemName, $foldersToArchive)) {
                if (rename($sourcePath, $destinationPath)) {
                    echo "[ARCHIVIERT] Kompletter Ordner: " . htmlspecialchars($itemName) . "\n";
                    $movedFolders++;
                } else {
                    echo "[FEHLER] Konnte Ordner nicht verschieben: " . htmlspecialchars($itemName) . "\n";
                }
            }
            continue; // Andere Ordner ignorieren
        }

        if ($item->isFile()) {
            if (in_array($itemName, $filesToKeep)) {
                $keptFiles++;
            } else {
                if (rename($sourcePath, $destinationPath)) {
                    $movedFiles++;
                } else {
                    echo "[FEHLER] Konnte Datei nicht verschieben: " . htmlspecialchars($itemName) . "\n";
                }
            }
        }
    }
} catch (Exception $e) {
    die("[FATALER FEHLER] Ein Fehler ist aufgetreten: " . $e->getMessage());
}


echo "\n=========================================\n";
echo "Zusammenfassung\n";
echo "=========================================\n";
echo "Behaltene Dateien: " . $keptFiles . "\n";
echo "Archivierte Dateien: " . $movedFiles . "\n";
echo "Archivierte Ordner: " . $movedFolders . "\n\n";
echo "Cleanup abgeschlossen. Sie können diese PHP-Datei jetzt löschen.\n";

?>
