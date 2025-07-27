<?php // tools/collector.php

// --- Serverseitige Logik ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token(); // CSRF-Token validieren
    // Aktuelle Einstellungen aus dem Formular holen und speichern
    $currentSettings = [
        'collector_startDir' => $_POST['collector_startDir'] ?? $settings['collector_startDir'],
        'collector_includeSubdirs' => isset($_POST['collector_includeSubdirs']),
        // KORREKTUR: Neue Felder für Kopf- und Fußzeile
        'collector_header' => $_POST['collector_header'] ?? '',
        'collector_footer' => $_POST['collector_footer'] ?? '',
        // KORREKTUR: Exkludierte Dateitypen speichern
        'collector_excludeFileTypes' => $_POST['collector_excludeFileTypes'] ?? '',
    ];
    // KORREKTUR: Checkbox-Werte separat speichern
    $selectedCheckboxTypes = [];
    if (isset($_POST['collector_file_types_checkboxes']) && is_array($_POST['collector_file_types_checkboxes'])) {
        $selectedCheckboxTypes = $_POST['collector_file_types_checkboxes'];
    }
    $currentSettings['collector_selectedCheckboxes'] = implode(',', array_unique(array_filter($selectedCheckboxTypes)));

    saveSettings($currentSettings);
    // Die $settings Variable für den Rest des Skripts aktualisieren
    $settings = array_merge($settings, $currentSettings);

    $startDir = $settings['collector_startDir'];
    $includeSubdirs = $settings['collector_includeSubdirs'];
    // KORREKTUR: Kopf- und Fußzeile aus Einstellungen
    $headerContent = $settings['collector_header'];
    $footerContent = $settings['collector_footer'];

    // KORREKTUR: Dateitypen für die Operation aus Checkboxen und manuellem Ausschluss zusammenführen
    $fileTypesForOperation = [];
    if (!empty($settings['collector_selectedCheckboxes'])) {
        $fileTypesForOperation = array_map('trim', explode(',', strtolower($settings['collector_selectedCheckboxes'])));
    }
    if (!empty($settings['collector_excludeFileTypes'])) {
        $excludedTypesArray = array_map('trim', explode(',', strtolower($settings['collector_excludeFileTypes'])));
        $fileTypesForOperation = array_diff($fileTypesForOperation, $excludedTypesArray);
    }
    $fileTypes = implode(',', array_unique(array_filter($fileTypesForOperation)));

    if (!is_dir($startDir) || !is_readable($startDir)) {
        logMsg($logMessages, "Startverzeichnis '$startDir' existiert nicht oder ist nicht lesbar.", 'error');
    } else {
        $collectedContent = "";
        $fileCount = 0;

        // KORREKTUR: Dynamische Kopfzeile
        if (!empty($headerContent)) {
            $collectedContent .= str_replace(
                ['{date}', '{root_dir}', '{allowed_ext}', '{subdirs}'],
                [date('Y-m-d H:i:s'), realpath($startDir), $fileTypes, ($includeSubdirs ? 'Ja' : 'Nein')],
                $headerContent
            ) . "\n\n";
        } else {
            // Standard-Header, wenn keine benutzerdefinierte Kopfzeile gesetzt ist
            $collectedContent .= "// Sammlung von Dateien - Erstellt: " . date('Y-m-d H:i:s') . "\n";
            $collectedContent .= "// Stammverzeichnis: " . realpath($startDir) . "\n";
            $collectedContent .= "// Erlaubte Endungen: " . $fileTypes . "\n";
            $collectedContent .= "// Unterverzeichnisse: " . ($includeSubdirs ? 'Ja' : 'Nein') . "\n";
            $collectedContent .= "//------------------------------------------------------------------------------//\n\n";
        }

        logMsg($logMessages, "Starte Sammlung im Verzeichnis: " . realpath($startDir), 'info');

        collectFiles(realpath($startDir), realpath($startDir), $fileTypes, $includeSubdirs, $collectedContent, $fileCount, $logMessages);

        // KORREKTUR: Dynamische Fußzeile
        if (!empty($footerContent)) {
            $collectedContent .= "\n" . str_replace(
                ['{date}', '{file_count}'],
                [date('Y-m-d H:i:s'), $fileCount],
                $footerContent
            ) . "\n";
        } else {
            $collectedContent .= "\n// Ende der Sammlung - " . date('Y-m-d H:i:s') . "\n";
            $collectedContent .= "// Gesamtzahl der gesammelten Dateien: " . $fileCount . "\n";
            $collectedContent .= "//------------------------------------------------------------------------------//\n";
        }

        logMsg($logMessages, "Sammlung abgeschlossen. " . $fileCount . " Datei(en) gefunden.", 'success');

        if ($_POST['action'] === 'download') {
            $filename = 'collect_' . date('Ymd_His') . '.txt';
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($collectedContent));
            echo $collectedContent;
            exit;
        } elseif ($_POST['action'] === 'collect') {
            // Die Log-Ausgabe wird unten gerendert. Wir fügen eine Vorschau hinzu.
            logMsg($logMessages, "VORSCHAU 500 Zeichen:\n\n" . htmlspecialchars(substr($collectedContent, 0, 500)) . "\n\n//-------------------------------...ENDE DER VORSCHAU---------------------------//\n", 'info');
        }
    }
}

function collectFiles($baseDir, $currentDir, $fileTypes, $includeSubdirs, &$output, &$count, &$log) {
    $allowedTypes = array_map('trim', explode(',', strtolower($fileTypes)));
    $handle = @opendir($currentDir);
    if (!$handle) {
        logMsg($log, "Konnte Verzeichnis nicht öffnen: $currentDir", 'error');
        return;
    }

    $entries = [];
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            $entries[] = $entry;
        }
    }
    closedir($handle);
    sort($entries);

    foreach ($entries as $entry) {
        $fullPath = $currentDir . DIRECTORY_SEPARATOR . $entry;
        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $fullPath);

        if (is_dir($fullPath) && $includeSubdirs) {
            collectFiles($baseDir, $fullPath, $fileTypes, $includeSubdirs, $output, $count, $log);
        } elseif (is_file($fullPath) && is_readable($fullPath)) {
            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if (in_array($extension, $allowedTypes)) {
                $content = file_get_contents($fullPath);
                $output .= "// Quelldatei: " . $relativePath . "\n\n";
                $output .= $content;
                $output .= "\n\n// Ende der Datei: " . $relativePath . "\n";
                $output .= "//------------------------------------------------------------------------------//\n\n";
                $count++;
                logMsg($log, "Datei hinzugefügt: $relativePath", 'info');
            }
        }
    }
}

// --- UI-Rendering-Funktion ---
function renderToolUI($settings, $logMessages) {
    // KORREKTUR: Gängige Dateiendungen für Collector
    $commonCollectorFileTypes = ['php', 'html', 'css', 'js', 'json', 'txt', 'md', 'xml', 'ini', 'sh', 'sql', 'yaml'];
    // KORREKTUR: Ausgewählte Checkboxen aus settings lesen
    $currentCollectorSelectedCheckboxes = array_map('trim', explode(',', strtolower($settings['collector_selectedCheckboxes'] ?? '')));
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-archive me-2"></i>Collector Einstellungen</h5>
            </div>
            <div class="card-body">
                <form method="post" action="?tool=collector" data-settings-form="collector">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="collector_startDir" class="form-label">Startverzeichnis</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="collector_startDir" id="collector_startDir" value="<?php echo htmlspecialchars($_GET['collector_startDir'] ?? $settings['collector_startDir']); ?>">
                            <button class="btn btn-outline-secondary btn-folder-picker" type="button" data-target-input="#collector_startDir"><i class="fas fa-folder-open"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dateitypen zum Sammeln</label>
                        <div class="form-check-group">
                            <?php foreach ($commonCollectorFileTypes as $type): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="collector_file_types_checkboxes[]" id="collector_file_type_<?php echo $type; ?>" value="<?php echo $type; ?>" <?php if (in_array($type, $currentCollectorSelectedCheckboxes)) echo 'checked'; ?>>
                                    <label class="form-check-label" for="collector_file_type_<?php echo $type; ?>">.<?php echo $type; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" class="form-control mt-2" name="collector_excludeFileTypes" id="collector_excludeFileTypes" placeholder="Dateitypen ausschließen (kommagetrennt)" value="<?php echo htmlspecialchars($settings['collector_excludeFileTypes'] ?? ''); ?>">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" name="collector_includeSubdirs" id="collector_includeSubdirs" role="switch" <?php if ($settings['collector_includeSubdirs']) echo 'checked'; ?>>
                        <label class="form-check-label" for="collector_includeSubdirs">Unterverzeichnisse einbeziehen</label>
                    </div>
                    <div class="mb-3">
                        <label for="collector_header" class="form-label">Kopfzeile (Variablen: {date}, {root_dir}, {allowed_ext}, {subdirs})</label>
                        <textarea class="form-control" name="collector_header" id="collector_header" rows="4" placeholder="// Sammlung von Dateien - Erstellt: {date}&#10;// Stammverzeichnis: {root_dir}&#10;// Erlaubte Endungen: {allowed_ext}&#10;// Unterverzeichnisse: {subdirs}&#10;//------------------------------------------------------------------------------//"><?php echo htmlspecialchars($settings['collector_header'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="collector_footer" class="form-label">Fußzeile (Variablen: {date}, {file_count})</label>
                        <textarea class="form-control" name="collector_footer" id="collector_footer" rows="4" placeholder="// Ende der Sammlung - {date}&#10;// Gesamtzahl der gesammelten Dateien: {file_count}&#10;//------------------------------------------------------------------------------//"><?php echo htmlspecialchars($settings['collector_footer'] ?? ''); ?></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="collect" class="btn btn-primary"><i class="fas fa-eye me-2"></i>Einstellungen speichern & Anzeigen</button>
                        <button type="submit" name="action" value="download" class="btn btn-success"><i class="fas fa-download me-2"></i>Sammeln & Herunterladen</button>
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
