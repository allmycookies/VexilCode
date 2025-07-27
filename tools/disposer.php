<?php // tools/disposer.php

// --- Serverseitige Logik ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispose') {
    validate_csrf_token(); // CSRF-Token validieren
    // Einstellung für das Zielverzeichnis wieder aus dem Formular lesen und speichern
    $currentSettings = [
        'disposer_targetDir' => $_POST['disposer_targetDir'] ?? $settings['disposer_targetDir'],
    ];
    saveSettings($currentSettings);
    $settings = array_merge($settings, $currentSettings);

    $baseDir = $settings['disposer_targetDir']; // Das vom Benutzer gewählte Basisverzeichnis

    if (empty($_FILES['collectorFile']['name']) || empty($_FILES['collectorFile']['tmp_name'])) {
        logMsg($logMessages, "Bitte wählen Sie eine Collector-Datei zum Hochladen aus.", 'error');
    } elseif (!is_dir($baseDir) || !is_writable($baseDir)) {
        logMsg($logMessages, "Das gewählte Basisverzeichnis '{$baseDir}' existiert nicht oder ist nicht beschreibbar. Bitte Berechtigungen prüfen.", 'error');
    } else {
        $originalFilename = $_FILES['collectorFile']['name'];
        
        $filenameSansExt = pathinfo($originalFilename, PATHINFO_FILENAME);
        $cleanDirName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $filenameSansExt);
        $cleanDirName = trim($cleanDirName, '_');
        $cleanDirName = strtolower($cleanDirName);
        
        if (empty($cleanDirName)) {
            $cleanDirName = 'disposed_' . date('Ymd_His');
        }

        $targetDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cleanDirName;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            logMsg($logMessages, "Konnte das Zielverzeichnis '{$targetDir}' nicht erstellen.", 'error');
        } 
        elseif (!is_writable($targetDir)) {
            logMsg($logMessages, "Das Zielverzeichnis '{$targetDir}' ist nicht beschreibbar.", 'error');
        }
        else {
            logMsg($logMessages, "Starte Disposer-Prozess im neuen Verzeichnis: " . $targetDir, 'special');
            
            $sourceContent = file_get_contents($_FILES['collectorFile']['tmp_name']);
            $lines = explode("\n", $sourceContent);

            $currentFile = null;
            $fileContent = '';
            $filesCreated = 0;
            $dirsCreated = 0;

            foreach ($lines as $line) {
                $line = rtrim($line, "\r"); 

                if (preg_match('~^// Quelldatei: (.*)~', $line, $matches)) {
                    if ($currentFile !== null) {
                        writeFile($targetDir, $currentFile, $fileContent, $filesCreated, $dirsCreated, $logMessages);
                    }
                    $currentFile = trim($matches[1]);
                    $fileContent = '';
                    logMsg($logMessages, "Verarbeite Datei: $currentFile", 'info');
                } elseif (preg_match('~^// Ende der Datei: (.*)~', $line, $matches)) {
                    if ($currentFile !== null) {
                        writeFile($targetDir, $currentFile, $fileContent, $filesCreated, $dirsCreated, $logMessages);
                        $currentFile = null;
                        $fileContent = '';
                    }
                } elseif ($currentFile !== null) {
                    $fileContent .= $line . "\n";
                }
            }
            if ($currentFile !== null) {
                writeFile($targetDir, $currentFile, $fileContent, $filesCreated, $dirsCreated, $logMessages);
            }
            logMsg($logMessages, "Disposer-Prozess abgeschlossen. $filesCreated Datei(en) und $dirsCreated Verzeichnis(se) erstellt.", 'success');
        }
    }
}

function writeFile($baseDir, $relativePath, $content, &$files, &$dirs, &$log) {
    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $relativePath;
    $dirName = dirname($fullPath);

    if (!is_dir($dirName)) {
        if (mkdir($dirName, 0755, true)) {
            logMsg($log, "Verzeichnis erstellt: " . str_replace($baseDir . DIRECTORY_SEPARATOR, '', $dirName), 'success');
            $dirs++;
        } else {
            logMsg($log, "Konnte Verzeichnis nicht erstellen: $dirName", 'error');
            return;
        }
    }

    $content = rtrim($content, "\n");

    if (file_put_contents($fullPath, $content) !== false) {
        logMsg($log, "Datei geschrieben: $relativePath", 'success');
        $files++;
    } else {
        logMsg($log, "Konnte Datei nicht schreiben: $fullPath", 'error');
    }
}


// --- UI-Rendering-Funktion ---
function renderToolUI($settings, $logMessages) {
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-box-open me-2"></i>Disposer</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Erstellt die Struktur aus einer Collector-Datei in einem neuen Unterordner innerhalb des gewählten Zielverzeichnisses.</p>
                <form method="post" action="?tool=disposer" enctype="multipart/form-data" data-settings-form="disposer">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="disposer_targetDir" class="form-label">Zielverzeichnis (Basis)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="disposer_targetDir" id="disposer_targetDir" value="<?php echo htmlspecialchars($_GET['disposer_targetDir'] ?? $settings['disposer_targetDir']); ?>">
                            <button class="btn btn-outline-secondary btn-folder-picker" type="button" data-target-input="#disposer_targetDir"><i class="fas fa-folder-open"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="collectorFile" class="form-label">Collector-Datei auswählen</label>
                        <input class="form-control" type="file" id="collectorFile" name="collectorFile" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="action" value="dispose" class="btn btn-warning"><i class="fas fa-cogs me-2"></i>Struktur jetzt erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-stream me-2"></i>Log-Ausgabe</h5>
            </div>
            <div class="card-body">
                <div class="log-output">
                    <?php renderLog($logMessages); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
}
?>
