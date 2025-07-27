<?php
// tools/filemanager.php

function renderToolUI($settings, $logMessages) {
    global $ROOT_PATH;
    
    // --- BUGFIX: Pfadprüfung beim Laden ---
    // Logik in die Vergit-Klasse ausgelagert, um sie wiederverwenden zu können
    // Falls Vergit nicht existiert, wird ein einfacher Fallback verwendet.
    if (class_exists('Vergit')) {
        require_once __DIR__ . '/../lib/vergit.class.php';
        $vergit = new Vergit();
        // 1. Priorität: URL-Parameter
        $pathFromUrl = $_GET['path'] ?? null;
        // 2. Priorität: Fallback auf Root-Path
        $startDir = $vergit->fixPathOnStartup($pathFromUrl, $ROOT_PATH);
    } else {
        // Einfacher Fallback, falls die Vergit-Klasse nicht geladen wurde
        $startDir = $_GET['path'] ?? $ROOT_PATH;
        if (!is_dir($startDir)) {
            $startDir = $ROOT_PATH;
        }
    }

    // Zusätzliche Sicherheitsprüfung, um sicherzustellen, dass der Pfad innerhalb des erlaubten Bereichs liegt.
    if (realpath($startDir) === false || strpos(realpath($startDir), realpath($ROOT_PATH)) !== 0) {
        $startDir = $ROOT_PATH;
    }
    // --- ENDE BUGFIX ---
?>
<div class="row">
    <div class="col-12">
        <div class="card" id="fileManagerCard" data-start-path="<?php echo htmlspecialchars($startDir); ?>" data-root-path="<?php echo htmlspecialchars($ROOT_PATH); ?>">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div id="fm-header-main" class="d-flex align-items-center flex-wrap gap-2 mb-2 mb-md-0">
                        <h5 class="mb-0 me-2"><i class="fas fa-folder-tree me-2"></i>Dateimanager</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-sm btn-primary" id="fm-upload-btn" title="Dateien hochladen"><i class="fas fa-upload me-1"></i></button>
                            <button class="btn btn-sm btn-success" id="fm-create-folder-btn" title="Neuer Ordner"><i class="fas fa-folder-plus"></i></button>
                            <button class="btn btn-sm btn-success" id="fm-create-file-btn" title="Neue Datei"><i class="fas fa-file-medical"></i></button>
                            <button class="btn btn-sm btn-secondary" id="fm-refresh-btn" title="Aktualisieren"><i class="fas fa-sync-alt"></i></button>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Pfad übergeben">
                                <button class="btn btn-outline-secondary" id="fm-pass-to-sr-btn" title="Pfad an Suchen & Ersetzen übergeben"><i class="fas fa-search"></i></button>
                                <button class="btn btn-outline-secondary" id="fm-pass-to-collector-btn" title="Pfad an Collector übergeben"><i class="fas fa-archive"></i></button>
                                <button class="btn btn-outline-secondary" id="fm-pass-to-disposer-btn" title="Pfad an Disposer übergeben"><i class="fas fa-box-open"></i></button>
                                <button class="btn btn-outline-secondary" id="fm-pass-to-sitemap-btn" title="Pfad an Sitemap übergeben"><i class="fas fa-sitemap"></i></button>
                            </div>
                        </div>
                        <div class="spinner-border spinner-border-sm text-primary ms-2" id="fileManagerSpinner" role="status" style="display: none;">
                            <span class="visually-hidden">Laden...</span>
                        </div>
                    </div>
                    <div id="fm-info-bar" class="d-flex align-items-center gap-1 flex-wrap">
                        </div>
                </div>
                <div id="fm-breadcrumb" class="fm-breadcrumb-custom mt-2"></div>
                <div class="input-group input-group-sm mt-2">
                    <input type="text" class="form-control" id="fm-path-input" placeholder="Pfad eingeben..." aria-label="Current path">
                    <button class="btn btn-outline-secondary" type="button" id="fm-path-go-btn">Go</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 fm-table">
                        <thead class="table-dark">
                            <tr>
                                <th class="fm-col-checkbox"><input class="form-check-input" type="checkbox" id="fm-select-all"></th>
                                <th class="sortable fm-col-name" data-sort="name">Name <i class="fas fa-sort"></i></th>
                                <th class="sortable fm-col-size" data-sort="size_bytes">Größe <i class="fas fa-sort"></i></th>
                                <th class="fm-col-perms">Berechtigung</th>
                                <th class="fm-col-owner">Besitzer</th>
                                <th class="sortable fm-col-modified" data-sort="modified">Zuletzt geändert <i class="fas fa-sort"></i></th>
                                <th class="fm-col-type">Typ</th>
                                <th class="fm-col-actions text-end">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody id="fm-file-list"></tbody>
                        <tfoot class="fm-bulk-actions" style="display: none;">
                            <tr><td colspan="8" class="bg-body-tertiary">
                                <div class="d-flex align-items-center p-2 flex-wrap justify-content-center">
                                    <span class="me-3 mb-2 mb-md-0" id="fm-selection-info"></span>
                                    <div class="btn-group btn-group-sm mb-2 mb-md-0">
                                        <button class="btn btn-primary" data-action="invert-selection">Umkehren</button>
                                        <button class="btn btn-primary" data-action="deselect-all">Abwählen</button>
                                    </div>
                                    <div class="btn-group btn-group-sm ms-auto">
                                        <button class="btn btn-primary" data-action="zip"><i class="fas fa-file-archive me-1"></i> ZIP</button>
                                        <button class="btn btn-primary" data-action="move-copy"><i class="fas fa-copy me-1"></i> Kopieren/Verschieben</button>
                                        <button class="btn btn-primary" data-action="delete"><i class="fas fa-trash me-1"></i> Löschen</button>
                                    </div>
                                </div>
                            </td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
}
?>