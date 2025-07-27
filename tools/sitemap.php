<?php // tools/sitemap.php

$output = '';

// --- Serverseitige Logik ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['sitemap_targetDirectory'])) {
    // CSRF-Validierung für GET-Anfragen ist optional, aber eine gute Praxis, wenn Aktionen ausgelöst werden
    validate_csrf_token();
    $currentSettings = [
        'sitemap_targetDirectory' => $_GET['sitemap_targetDirectory'] ?? $settings['sitemap_targetDirectory'],
    ];
    saveSettings($currentSettings);
    $settings = array_merge($settings, $currentSettings);

    $targetDirectory = $settings['sitemap_targetDirectory'];

    $output = "Sitemap für: " . htmlspecialchars($targetDirectory) . "\n";
    $output .= str_repeat("-", 50) . "\n";

    if (!is_dir($targetDirectory)) {
        $output .= "<span class='error'>[FEHLER] Das angegebene Verzeichnis '" . htmlspecialchars($targetDirectory) . "' existiert nicht.</span>\n";
    } elseif (!is_readable($targetDirectory)) {
        $output .= "<span class='error'>[FEHLER] Das angegebene Verzeichnis '" . htmlspecialchars($targetDirectory) . "' ist nicht lesbar (Prüfe Webserver-Berechtigungen!).</span>\n";
    } else {
        $sitemapContent = sm_generateDirectorySitemapHtml(rtrim($targetDirectory, DIRECTORY_SEPARATOR));
        if (empty(trim($sitemapContent))) {
            $output .= "<span class='info'>[INFO] Das Verzeichnis ist leer oder es konnten keine Einträge gelesen werden.</span>\n";
        } else {
            $output .= $sitemapContent;
        }
        $output .= str_repeat("-", 50) . "\n";
        $output .= "<span class='info'>Hinweis: [D] = Verzeichnis, [F] = Datei</span>\n";
    }
}

function sm_generateDirectorySitemapHtml(string $dir, string $prefix = '', string $basePath = ''): string {
    $sitemapOutput = '';
    if ($basePath === '') {
        $realDir = realpath($dir);
        if ($realDir === false) return $prefix . "<span class='error'>[FEHLER] Verzeichnis nicht gefunden: " . htmlspecialchars($dir) . "</span>\n";
        $basePath = rtrim($realDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $dir = $realDir;
    }

    $handle = @opendir($dir);
    if (!$handle) return $prefix . "<span class='error'>[FEHLER] Konnte Verzeichnis nicht öffnen: " . htmlspecialchars($dir) . "</span>\n";

    $entries = [];
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") $entries[] = $entry;
    }
    closedir($handle);
    sort($entries);

    foreach ($entries as $entry) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
        $displayPath = htmlspecialchars($entry);

        if (is_dir($fullPath)) {
            $sitemapOutput .= $prefix . "[D] " . $displayPath . "/\n";
            $sitemapOutput .= sm_generateDirectorySitemapHtml($fullPath, $prefix . "    ", $basePath);
        } else {
            $sitemapOutput .= $prefix . "[F] " . $displayPath . "\n";
        }
    }
    return $sitemapOutput;
}

// --- UI-Rendering-Funktion ---
function renderToolUI($settings, $logMessages) {
    global $output;
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-sitemap me-2"></i>Sitemap Generator</h5>
            </div>
            <div class="card-body">
                <form method="get" action="?tool=sitemap" data-settings-form="sitemap">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="tool" value="sitemap">
                    <div class="input-group">
                         <input type="text" class="form-control form-control-lg" name="sitemap_targetDirectory" id="sitemap_targetDirectory" value="<?php echo htmlspecialchars($_GET['sitemap_targetDirectory'] ?? $settings['sitemap_targetDirectory']); ?>" placeholder="Verzeichnis auswählen oder Pfad eingeben...">
                         <button class="btn btn-outline-secondary btn-folder-picker" type="button" data-target-input="#sitemap_targetDirectory"><i class="fas fa-folder-open"></i></button>
                         <button class="btn btn-primary" type="submit"><i class="fas fa-list-ul me-2"></i>Auflisten</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($output)): ?>
        <div class="card mt-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-stream me-2"></i>Ausgabe</h5>
                <button class="btn btn-sm btn-outline-secondary" id="copySitemapBtn">
                    <i class="fas fa-clipboard me-1"></i> In Zwischenablage kopieren
                </button>
            </div>
            <div class="card-body sitemap-output">
                <pre id="sitemapOutputContent"><?php echo $output; ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
}
?>
