<?php // tools/search_replace.php

// --- Serverseitige Logik ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token(); // CSRF-Token validieren

    // KORREKTUR: Speichern der Suchbegriffe wieder aktiviert.
    $persistentSettings = [
        'sr_startDir' => $_POST['sr_startDir'] ?? $settings['sr_startDir'],
        'sr_searchString' => $_POST['sr_searchString'] ?? '',
        'sr_replaceString' => $_POST['sr_replaceString'] ?? '',
        'sr_includeSubdirs' => isset($_POST['sr_includeSubdirs']),
        'sr_backupFiles' => isset($_POST['sr_backupFiles']),
        'sr_fileTypes_manual' => $_POST['sr_fileTypes_manual'] ?? '',
    ];

    $selectedCheckboxTypes = [];
    if (isset($_POST['sr_file_types_checkboxes']) && is_array($_POST['sr_file_types_checkboxes'])) {
        $selectedCheckboxTypes = $_POST['sr_file_types_checkboxes'];
    }
    $persistentSettings['sr_selectedCheckboxes'] = implode(',', array_unique(array_filter($selectedCheckboxTypes)));

    saveSettings($persistentSettings);
    // Die $settings Variable für den Rest des Skripts aktualisieren.
    $settings = array_merge($settings, $persistentSettings);

    // Die Operations-Parameter für DIESEN Lauf werden nun wieder aus den gespeicherten Settings gelesen.
    $startDir = $settings['sr_startDir'];
    $searchString = $settings['sr_searchString'];
    $replaceString = $settings['sr_replaceString'];
    $includeSubdirs = $settings['sr_includeSubdirs'];
    $backupFiles = $settings['sr_backupFiles'];

    // Dateitypen für die Operation aus Checkboxen und manuellem Feld zusammenführen
    $fileTypesForOperation = [];
    if (!empty($settings['sr_selectedCheckboxes'])) {
        $fileTypesForOperation = array_map('trim', explode(',', strtolower($settings['sr_selectedCheckboxes'])));
    }
    if (!empty($settings['sr_fileTypes_manual'])) {
        $manualTypesArray = array_map('trim', explode(',', strtolower($settings['sr_fileTypes_manual'])));
        $fileTypesForOperation = array_merge($fileTypesForOperation, $manualTypesArray);
    }
    $fileTypes = implode(',', array_unique(array_filter($fileTypesForOperation)));
    
    if (!is_dir($startDir)) {
        logMsg($logMessages, "Fehler: Verzeichnis '$startDir' existiert nicht!", 'error');
    } else {
        switch ($_POST['action']) {
            case 'search_replace':
                if (empty($searchString)) { logMsg($logMessages, "Fehler: Suchfeld ist leer.", 'error'); break; }
                logMsg($logMessages, "== START Suche & Ersetzung ==", 'special');
                sr_searchAndReplace($startDir, $searchString, $replaceString, $fileTypes, $includeSubdirs, $backupFiles, true, $logMessages);
                logMsg($logMessages, "== ENDE Suche & Ersetzung ==", 'special');
                break;
            case 'search_only':
                if (empty($searchString)) { logMsg($logMessages, "Fehler: Suchfeld ist leer.", 'error'); break; }
                logMsg($logMessages, "== START Nur Suchen ==", 'special');
                sr_searchAndReplace($startDir, $searchString, '', $fileTypes, $includeSubdirs, false, false, $logMessages);
                logMsg($logMessages, "== ENDE Nur Suchen ==", 'special');
                break;
            case 'restore':
                logMsg($logMessages, "== START Wiederherstellung ==", 'special');
                sr_restoreLatestBackups($startDir, $includeSubdirs, $logMessages);
                logMsg($logMessages, "== ENDE Wiederherstellung ==", 'special');
                break;
            case 'cleanup':
                logMsg($logMessages, "== START Cleanup ==", 'special');
                sr_cleanupAllBackups($startDir, $includeSubdirs, $logMessages);
                logMsg($logMessages, "== ENDE Cleanup ==", 'special');
                break;
        }
    }
}

// --- Kernfunktionen (VOLLSTÄNDIG) ---
function sr_searchAndReplace($directory, $search, $replace, $fileTypes, $includeSubdirs, $backup, $doReplace, &$log) {
    $dirHandle = @opendir($directory);
    if (!$dirHandle) {
        logMsg($log, "Fehler beim Öffnen von: $directory", 'error');
        return;
    }
    $allowedTypes = array_map('trim', explode(',', strtolower($fileTypes)));
    
    $entries = [];
    while (false !== ($item = readdir($dirHandle))) {
        if ($item != "." && $item != "..") $entries[] = $item;
    }
    closedir($dirHandle);
    sort($entries);

    foreach ($entries as $item) {
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && $includeSubdirs) {
            sr_searchAndReplace($path, $search, $replace, $fileTypes, $includeSubdirs, $backup, $doReplace, $log);
        }
        if (is_file($path)) {
            // KORREKTUR: Überspringe die settings.json Datei, um Endlosschleifen zu verhindern.
            if (realpath($path) === realpath(SETTINGS_FILE)) {
                continue;
            }

            if (preg_match('/\.srbkup(\d*)$/i', $item)) continue;
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedTypes)) continue;
            
            $content = @file_get_contents($path);
            if ($content === false) {
                logMsg($log, "Konnte Datei nicht lesen:", 'warning', $path);
                continue;
            }

            if (strpos($content, $search) !== false) {
                if ($doReplace) {
                    $newContent = str_replace($search, $replace, $content);
                    if ($newContent !== $content) {
                        if ($backup) {
                            $counter = 1;
                            $backupPath = $path . '.srbkup';
                            while (file_exists($backupPath)) {
                                $counter++;
                                $backupPath = $path . '.srbkup' . $counter;
                            }
                            if (!@copy($path, $backupPath)) {
                                logMsg($log, "Konnte keine Backup-Datei erstellen:", 'error', $path);
                            }
                        }
                        if (@file_put_contents($path, $newContent) === false) {
                            logMsg($log, "Konnte Datei nicht schreiben:", 'error', $path);
                        } else {
                            logMsg($log, "Ersetzt in:", 'success', $path);
                        }
                    }
                } else {
                    logMsg($log, "Treffer in:", 'info', $path);
                    $lines = explode("\n", $content);
                    foreach ($lines as $i => $line) {
                        if (strpos($line, $search) !== false) {
                           logMsg($log, "Zeile " . ($i + 1) . ": " . trim($line), 'detail');
                        }
                    }
                }
            }
        }
    }
}

function sr_restoreLatestBackups($directory, $includeSubdirs, &$log) {
    $dirHandle = @opendir($directory);
    if (!$dirHandle) {
        logMsg($log, "Fehler beim Öffnen von: $directory", 'error');
        return;
    }
    $backupsInThisDir = [];
    while (($item = readdir($dirHandle)) !== false) {
        if ($item === '.' || $item === '..') continue;
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if ($includeSubdirs) sr_restoreLatestBackups($path, $includeSubdirs, $log);
        } else {
            if (preg_match('/^(.*)\.srbkup(\d*)$/i', $item, $m)) {
                $baseName = $m[1];
                $numStr = $m[2];
                $number = ($numStr === '') ? 0 : (int)$numStr;
                if (!isset($backupsInThisDir[$baseName])) $backupsInThisDir[$baseName] = [];
                $backupsInThisDir[$baseName][$number] = $path;
            }
        }
    }
    closedir($dirHandle);

    foreach ($backupsInThisDir as $base => $backups) {
        ksort($backups);
        $maxNumber = array_key_last($backups);
        $backupPath = $backups[$maxNumber];
        $originalPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $base;
        if (file_exists($originalPath)) {
            if (!@unlink($originalPath)) {
                logMsg($log, "Konnte bestehende Datei nicht löschen:", 'error', $originalPath);
                continue;
            }
        }
        if (!@rename($backupPath, $originalPath)) {
            logMsg($log, "Konnte Backup nicht wiederherstellen: $backupPath => $originalPath", 'error');
        } else {
            logMsg($log, "Backup wiederhergestellt:", 'success', $originalPath);
        }
    }
}

function sr_cleanupAllBackups($directory, $includeSubdirs, &$log) {
    $dirHandle = @opendir($directory);
    if (!$dirHandle) {
        logMsg($log, "Fehler beim Öffnen von: $directory", 'error');
        return;
    }
    while (($item = readdir($dirHandle)) !== false) {
        if ($item === '.' || $item === '..') continue;
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if ($includeSubdirs) sr_cleanupAllBackups($path, $includeSubdirs, $log);
        } else {
            if (preg_match('/\.srbkup(\d*)$/i', $item)) {
                if (@unlink($path)) {
                    logMsg($log, "Backup-Datei gelöscht:", 'success', $path);
                } else {
                    logMsg($log, "Fehler beim Löschen:", 'error', $path);
                }
            }
        }
    }
    closedir($dirHandle);
}

// --- UI-Rendering-Funktion ---
function renderToolUI($settings, $logMessages) {
    $commonSrFileTypes = ['php', 'html', 'css', 'js', 'json', 'txt', 'md', 'xml', 'ini', 'sh', 'yaml', 'sql'];
    $currentSrSelectedCheckboxes = array_map('trim', explode(',', strtolower($settings['sr_selectedCheckboxes'] ?? '')));
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-search me-2"></i>Einstellungen</h6></div>
            <div class="card-body">
                <form method="post" action="?tool=search_replace" data-settings-form="search_replace">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="sr_startDir" class="form-label">Startverzeichnis</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="sr_startDir" id="sr_startDir" value="<?php echo htmlspecialchars($_GET['sr_startDir'] ?? $settings['sr_startDir']); ?>">
                            <button class="btn btn-outline-secondary btn-folder-picker" type="button" data-target-input="#sr_startDir"><i class="fas fa-folder-open"></i></button>
                        </div>
                    </div>
                     <div class="mb-3">
                        <label for="sr_searchString" class="form-label">Suchen nach</label>
                        <textarea class="form-control" name="sr_searchString" id="sr_searchString" rows="3"><?php echo htmlspecialchars($settings['sr_searchString'] ?? ''); ?></textarea>
                    </div>
                     <div class="mb-3">
                        <label for="sr_replaceString" class="form-label">Ersetzen durch</label>
                        <textarea class="form-control" name="sr_replaceString" id="sr_replaceString" rows="3"><?php echo htmlspecialchars($settings['sr_replaceString'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dateitypen</label>
                        <div class="form-check-group">
                            <?php foreach ($commonSrFileTypes as $type): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="sr_file_types_checkboxes[]" id="sr_file_type_<?php echo $type; ?>" value="<?php echo $type; ?>" <?php if (in_array($type, $currentSrSelectedCheckboxes)) echo 'checked'; ?>>
                                    <label class="form-check-label" for="sr_file_type_<?php echo $type; ?>">.<?php echo $type; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" class="form-control mt-2" name="sr_fileTypes_manual" id="sr_fileTypes_manual" placeholder="Weitere Typen (kommagetrennt)" value="<?php echo htmlspecialchars($settings['sr_fileTypes_manual'] ?? ''); ?>">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" class="form-check-input" name="sr_includeSubdirs" id="sr_includeSubdirs" role="switch" <?php if ($settings['sr_includeSubdirs']) echo 'checked'; ?>>
                        <label class="form-check-label" for="sr_includeSubdirs">Unterverzeichnisse einbeziehen</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" name="sr_backupFiles" id="sr_backupFiles" role="switch" <?php if ($settings['sr_backupFiles']) echo 'checked'; ?>>
                        <label class="form-check-label" for="sr_backupFiles">Backup erstellen (.srbkup)</label>
                    </div>
                    <div class="btn-group w-100 mb-2" role="group">
                        <button type="submit" name="action" value="search_only" class="btn btn-info"><i class="fas fa-search me-1"></i>Nur Suchen</button>
                        <button type="submit" name="action" value="search_replace" class="btn btn-primary"><i class="fas fa-exchange-alt me-1"></i>Ersetzen</button>
                    </div>
                    <div class="btn-group w-100" role="group">
                        <button type="submit" name="action" value="restore" class="btn btn-warning"><i class="fas fa-undo me-1"></i>Backup Wiederh.</button>
                        <button type="submit" name="action" value="cleanup" class="btn btn-danger" data-confirm="Sollen wirklich ALLE .srbkup-Dateien im Startverzeichnis (und Unterordnern) gelöscht werden?"><i class="fas fa-trash me-1"></i>Alle Backups löschen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-stream me-2"></i>Log-Ausgabe</h5></div>
            <div class="card-body">
                <div class="log-output">
                    <?php
                    renderLog($logMessages, function($entry) {
                        $icon = '';
                        $textClass = '';
                        switch ($entry['type']) {
                            case 'success': $icon = '<i class="fas fa-check-circle text-success fa-fw"></i>'; $textClass = 'text-success'; break;
                            case 'error': $icon = '<i class="fas fa-times-circle text-danger fa-fw"></i>'; $textClass = 'text-danger'; break;
                            case 'warning': $icon = '<i class="fas fa-exclamation-triangle text-warning fa-fw"></i>'; break;
                            case 'info': $icon = '<i class="fas fa-info-circle text-info fa-fw"></i>'; break;
                            case 'special': $icon = '<i class="fas fa-star text-primary fa-fw"></i>'; return "<p class=\"mb-1 fw-bold {$textClass}\">" . htmlspecialchars($entry['message']) . "</p>";
                            case 'detail': return "<p class=\"mb-1 log-line-detail\">" . htmlspecialchars($entry['message']) . "</p>";
                        }
                        $time = '<code class="text-muted">[' . htmlspecialchars($entry['time']) . ']</code>';
                        $message = htmlspecialchars($entry['message']);
                        if ($entry['data']) {
                            $filepath = htmlspecialchars($entry['data']);
                            $url = '?tool=editor&file=' . urlencode($filepath);
                            $message .= " <a href='{$url}' target='_blank' class='text-info fw-bold'>{$filepath}</a>";
                        }
                        return "<p class=\"mb-1\">{$time} {$icon} <span class=\"{$textClass}\">{$message}</span></p>";
                    });
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
}
?>
