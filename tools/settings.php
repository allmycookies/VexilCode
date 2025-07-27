<?php // tools/settings.php

// --- Serverseitige Logik ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_structured_settings') {
    validate_csrf_token(); // CSRF-Token validieren

    $newSettings = [];

    // Alle bekannten textbasierten Einstellungen aus dem POST-Request lesen
    $knownKeys = [
        'vergit_storage_path',
        'collector_startDir', 'collector_selectedCheckboxes', 'collector_excludeFileTypes', 'collector_header', 'collector_footer',
        'disposer_targetDir',
        'sitemap_targetDirectory',
        'sr_startDir', 'sr_selectedCheckboxes', 'sr_fileTypes_manual'
    ];
    // Alle bekannten Checkbox-Einstellungen
    $checkboxKeys = [
        'collector_includeSubdirs',
        'sr_includeSubdirs', 'sr_backupFiles'
    ];

    foreach ($knownKeys as $key) {
        if (isset($_POST[$key])) {
            $newSettings[$key] = $_POST[$key];
        }
    }

    foreach ($checkboxKeys as $key) {
        // Wenn eine Checkbox nicht im POST-Request ist, bedeutet das 'false'
        $newSettings[$key] = isset($_POST[$key]);
    }

    if (saveSettings($newSettings)) {
        logMsg($logMessages, "Einstellungen erfolgreich gespeichert.", 'success');
        // Einstellungen neu laden, damit die Seite die neuen Werte anzeigt
        $settings = loadSettings();
    } else {
        logMsg($logMessages, "Fehler: Einstellungen konnten nicht gespeichert werden. Bitte Berechtigungen für config/settings.json prüfen.", 'error');
    }
}


// --- UI-Rendering-Funktion ---
function renderToolUI($settings, $logMessages) {
    global $ROOT_PATH, $WEB_ROOT_PATH;

    // Metadaten zur Beschreibung der Formularfelder
    $settingsMeta = [
        'Allgemeine Konfiguration' => [
            'ROOT_PATH' => ['label' => 'Stammverzeichnis (ROOT_PATH)', 'type' => 'text', 'help' => 'Das globale Arbeitsverzeichnis für alle Tools. Wird in <code>config/config.php</code> definiert und kann hier nicht geändert werden.', 'disabled' => true],
            'WEB_ROOT_PATH' => ['label' => 'Web-Stammverzeichnis (WEB_ROOT_PATH)', 'type' => 'text', 'help' => 'Das Document-Root des Servers, um Web-URLs zu erzeugen. Wird in <code>config/config.php</code> definiert und kann hier nicht geändert werden.', 'disabled' => true]
        ],
        'Vergit' => [
            'vergit_storage_path' => ['label' => 'Ablageort für Projekte', 'type' => 'text_picker', 'help' => 'Der Ordner, in dem Vergit seine Projekt- und Versionsdaten speichert.']
        ],
        'Collector' => [
            'collector_startDir' => ['label' => 'Standard-Startverzeichnis', 'type' => 'text_picker'],
            'collector_selectedCheckboxes' => ['label' => 'Standard-Dateitypen (Checkboxes)', 'type' => 'text', 'help' => 'Kommagetrennte Liste der standardmäßig aktivierten Checkboxen (z.B. php,html,js).'],
            'collector_excludeFileTypes' => ['label' => 'Standardmäßig ausgeschlossene Dateitypen', 'type' => 'text', 'help' => 'Kommagetrennte Liste (z.B. log,tmp).'],
            'collector_includeSubdirs' => ['label' => 'Unterverzeichnisse standardmäßig einbeziehen', 'type' => 'checkbox'],
            'collector_header' => ['label' => 'Standard-Kopfzeile', 'type' => 'textarea', 'rows' => 3, 'help' => 'Variablen: {date}, {root_dir}, {allowed_ext}, {subdirs}'],
            'collector_footer' => ['label' => 'Standard-Fußzeile', 'type' => 'textarea', 'rows' => 3, 'help' => 'Variablen: {date}, {file_count}']
        ],
        'Disposer' => [
            'disposer_targetDir' => ['label' => 'Standard-Zielverzeichnis', 'type' => 'text_picker']
        ],
        'Sitemap' => [
            'sitemap_targetDirectory' => ['label' => 'Standard-Zielverzeichnis', 'type' => 'text_picker']
        ],
        'Suchen & Ersetzen' => [
            'sr_startDir' => ['label' => 'Standard-Startverzeichnis', 'type' => 'text_picker'],
            'sr_selectedCheckboxes' => ['label' => 'Standard-Dateitypen (Checkboxes)', 'type' => 'text', 'help' => 'Kommagetrennte Liste der standardmäßig aktivierten Checkboxen (z.B. php,html,js).'],
            'sr_fileTypes_manual' => ['label' => 'Zusätzliche manuelle Dateitypen', 'type' => 'text', 'help' => 'Kommagetrennte Liste (z.B. tpl,inc).'],
            'sr_includeSubdirs' => ['label' => 'Unterverzeichnisse standardmäßig einbeziehen', 'type' => 'checkbox'],
            'sr_backupFiles' => ['label' => 'Backups standardmäßig erstellen', 'type' => 'checkbox']
        ],
    ];

    // Temporär die Werte aus der Haupt-Konfiguration hinzufügen, damit sie angezeigt werden können.
    $settings['ROOT_PATH'] = $ROOT_PATH;
    $settings['WEB_ROOT_PATH'] = $WEB_ROOT_PATH;
?>
<div class="row justify-content-center">
    <div class="col-lg-10">
        
        <?php if (!empty($logMessages)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-stream me-2"></i>Meldungen</h5>
            </div>
            <div class="card-body">
                <div class="log-output p-0">
                     <?php renderLog($logMessages); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="post" action="?tool=settings">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="save_structured_settings">

            <?php foreach ($settingsMeta as $groupName => $groupFields): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs me-2"></i><?php echo htmlspecialchars($groupName); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($groupFields as $key => $meta): ?>
                            <div class="mb-4">
                                <label for="setting_<?php echo $key; ?>" class="form-label fw-bold"><?php echo $meta['label']; ?></label>
                                <?php
                                $value = $settings[$key] ?? '';
                                $isDisabled = $meta['disabled'] ?? false;
                                switch ($meta['type']) {
                                    case 'checkbox':
                                        ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="setting_<?php echo $key; ?>" name="<?php echo $key; ?>" <?php echo !empty($value) ? 'checked' : ''; ?> <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                        </div>
                                        <?php
                                        break;
                                    case 'textarea':
                                        ?>
                                        <textarea class="form-control" id="setting_<?php echo $key; ?>" name="<?php echo $key; ?>" rows="<?php echo $meta['rows'] ?? 5; ?>" <?php echo $isDisabled ? 'disabled' : ''; ?>><?php echo htmlspecialchars($value); ?></textarea>
                                        <?php
                                        break;
                                    case 'text_picker':
                                        ?>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="setting_<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                            <button class="btn btn-outline-secondary btn-folder-picker" type="button" data-target-input="#setting_<?php echo $key; ?>" <?php echo $isDisabled ? 'disabled' : ''; ?>><i class="fas fa-folder-open"></i></button>
                                        </div>
                                        <?php
                                        break;
                                    default: // 'text'
                                        ?>
                                        <input type="text" class="form-control" id="setting_<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                        <?php
                                }
                                if (!empty($meta['help'])): ?>
                                    <div class="form-text small text-muted mt-1"><?php echo $meta['help']; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card mb-4">
                <div class="card-body text-end">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i> Alle Einstellungen speichern
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
}
?>