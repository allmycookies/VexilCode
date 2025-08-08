<?php
session_start();

require_once __DIR__ . '/helpers.php';
// Bereinigt abgelaufene Sperren bei jeder Interaktion eines eingeloggten Benutzers
cleanup_expired_lockouts();
generate_csrf_token();

// Überprüfen, ob die Konfiguration existiert. Wenn nicht, zum Setup leiten.
if (!file_exists(__DIR__ . '/config/users.php')) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config/users.php';
if (empty($users)) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$settings = loadSettings();
// "vergit" und "settings" zu den erlaubten Tools hinzufügen
$allowed_tools = ['collector', 'disposer', 'search_replace', 'sitemap', 'editor', 'filemanager', 'vergit', 'settings'];
$tool = isset($_GET['tool']) && in_array($_GET['tool'], $allowed_tools) ? $_GET['tool'] : 'filemanager';
if ($tool === 'editor') {
    include __DIR__ . '/tools/editor.php';
    exit();
}
$logMessages = [];

// --- Logik für Vergit-Aktionen ---
if ($tool === 'vergit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    require_once __DIR__ . '/lib/vergit.class.php';
    $vergit = new Vergit();
    $action = $_POST['action'] ?? '';
    $projectId = $_POST['project_id'] ?? null;
    try {
        switch ($action) {
            case 'create_project':
                $projectName = $_POST['project_name'] ?? '';
                $vergit->createProject($projectName);
                header('Location: ?tool=vergit');
                exit();
            case 'create_version':
                $versionNumber = $_POST['version_number'] ?? '';
                $copyLast = isset($_POST['copy_last']);
                $newVersion = $vergit->createVersion($projectId, $versionNumber, $copyLast);
                header('Location: ?tool=filemanager&path=' . urlencode($newVersion['path']));
                exit();
            case 'create_release_zip':
                $versionId = $_POST['version_id'] ?? '';
                $releaseName = $_POST['release_name'] ?? '';
                $vergit->createReleaseZip($versionId, $releaseName);
                header('Location: ?tool=vergit&project_id=' . $projectId);
                exit();
            case 'delete_version':
                $versionId = $_POST['version_id'] ?? '';
                $vergit->deleteVersion($versionId);
                header('Location: ?tool=vergit&project_id=' . $projectId);
                exit();
            case 'delete_project': // Dies ist jetzt die ARCHIVIEREN-Aktion
                $vergit->archiveProject($projectId);
                header('Location: ?tool=vergit');
                exit();
            case 'restore_project':
                $archiveId = $_POST['archive_id'] ?? null;
                $vergit->restoreProject($archiveId);
                header('Location: ?tool=vergit');
                exit();
            case 'delete_archive':
                $archiveId = $_POST['archive_id'] ?? null;
                $vergit->deleteArchivePermanently($archiveId);
                header('Location: ?tool=vergit');
                exit();
            case 'repair_project':
                $message = $vergit->repairProject($projectId);
                $_SESSION['flash_success'] = $message;
                header('Location: ?tool=vergit&project_id=' . $projectId);
                exit();
            case 'set_channel_beta':
            case 'set_channel_stable':
                $versionId = $_POST['version_id'] ?? '';
                $channel = $action === 'set_channel_beta' ? 'beta' : 'stable';
                $vergit->setChannel($projectId, $versionId, $channel);
                $_SESSION['flash_success'] = "Version erfolgreich als '{$channel}' markiert.";
                header('Location: ?tool=vergit&project_id=' . $projectId);
                exit();
            case 'unset_channel_beta':
            case 'unset_channel_stable':
                $channel = $action === 'unset_channel_beta' ? 'beta' : 'stable';
                $vergit->unsetChannel($projectId, $channel);
                $_SESSION['flash_success'] = "Veröffentlichung für Kanal '{$channel}' wurde zurückgezogen.";
                header('Location: ?tool=vergit&project_id=' . $projectId);
                exit();
            case 'delete_release_file':
                $fileName = $_POST['file_name'] ?? '';
                $vergit->deleteReleaseFile($projectId, $fileName);
                $_SESSION['flash_success'] = "Release-Archiv '{$fileName}' wurde gelöscht.";
                header('Location: ?tool=vergit&project_id=' . $projectId);
                exit();
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        $redirectUrl = $projectId ? '?tool=vergit&project_id=' . $projectId : '?tool=vergit';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

$toolLogicFile = __DIR__ . "/tools/{$tool}.php";
if (file_exists($toolLogicFile)) {
    include_once $toolLogicFile;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <title>VexilCode Suite - <?php echo ucfirst($tool); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
         <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="fas fa-toolbox"></i> VexilCode v0.9.6.llm.b1</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link <?php echo $tool === 'filemanager'
                        ? 'active'
                        : ''; ?>" href="?tool=filemanager"><i class="fas fa-folder-tree fa-fw me-1"></i>Dateimanager</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $tool === 'vergit'
                        ? 'active'
                        : ''; ?>" href="?tool=vergit" id="vergit-nav-link"><i class="fas fa-code-branch fa-fw me-1"></i>Vergit</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $tool === 'search_replace'
                        ? 'active'
                        : ''; ?>" href="?tool=search_replace"><i class="fas fa-search fa-fw me-1"></i>Suchen & Ersetzen</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $tool === 'collector'
                        ? 'active'
                        : ''; ?>" href="?tool=collector"><i class="fas fa-archive fa-fw me-1"></i>Collector</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $tool === 'disposer'
                        ? 'active'
                        : ''; ?>" href="?tool=disposer"><i class="fas fa-box-open fa-fw me-1"></i>Disposer</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $tool === 'sitemap'
                        ? 'active'
                        : ''; ?>" href="?tool=sitemap"><i class="fas fa-sitemap fa-fw me-1"></i>Sitemap</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $tool === 'settings'
                        ? 'active'
                        : ''; ?>" href="?tool=settings"><i class="fas fa-cogs fa-fw me-1"></i>Einstellungen</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="navbar-text me-3">
                        <i class="fas fa-user fa-fw"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <button class="btn btn-outline-light me-3" id="theme-switcher-btn" title="Theme wechseln"><i class="fas fa-sun"></i></button>
                    <form action="logout.php" method="post" class="d-inline">
                         <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(
                             $_SESSION['csrf_token']
                         ); ?>">
                         <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid mt-4 mb-4">
 
        <?php
        // Flash-Nachrichten für Vergit-Erfolg anzeigen
        if (isset($_SESSION['flash_success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' .
                htmlspecialchars($_SESSION['flash_success']) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['flash_success']);
        }

        // Flash-Nachrichten für Vergit-Fehler anzeigen
        if (isset($_SESSION['flash_error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' .
                htmlspecialchars($_SESSION['flash_error']) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['flash_error']);
        }

        if (function_exists('renderToolUI')) {
            renderToolUI($settings, $logMessages);
        } else {
            echo '<div class="alert alert-danger">Tool-UI-Funktion nicht gefunden.</div>';
        }
        ?>
    </main>
    
    <div class="modal fade" id="folderPickerModal" tabindex="-1" aria-labelledby="folderPickerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                     <h5 class="modal-title" id="folderPickerModalLabel">Ordner auswählen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="folder-picker-path" class="mb-2 text-monospace text-muted small bg-body-tertiary p-2 rounded"></div>
                    <div id="folder-picker-content" class="list-group"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                     <button type="button" class="btn btn-primary" id="selectFolderBtn">Auswählen</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fm-rename-modal" tabindex="-1" aria-labelledby="fm-rename-modalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                 <div class="modal-header">
                    <h5 class="modal-title" id="fm-rename-modalLabel">Umbenennen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="fm-new-name-input" class="form-label">Neuer Name</label>
                    <input type="text" class="form-control" id="fm-new-name-input">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                     <button type="button" class="btn btn-primary" id="fm-rename-confirm-btn">Umbenennen</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fm-zip-modal" tabindex="-1" aria-labelledby="fm-zip-modalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fm-zip-modalLabel">In ZIP verpacken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="fm-zip-name-input" class="form-label">Dateiname für das ZIP-Archiv</label>
                       <input type="text" class="form-control" id="fm-zip-name-input" placeholder="Wird generiert, wenn leer">
                </div>
                <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                     <button type="button" class="btn btn-primary" id="fm-zip-confirm-btn">Verpacken</button>
                  </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fm-move-copy-modal" tabindex="-1" aria-labelledby="fm-move-copy-modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                 <div class="modal-header">
                     <h5 class="modal-title" id="fm-move-copy-modalLabel">Kopieren/Verschieben</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                     <p>Wählen Sie das Zielverzeichnis aus:</p>
  
  
                     <div id="fm-move-copy-picker-path" class="mb-2 text-monospace text-muted small bg-body-tertiary p-2 rounded"></div>
                    <div id="fm-move-copy-picker-content" class="list-group"></div>
                </div>
                <div class="modal-footer justify-content-between">
                 
 
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                     <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-info" id="fm-copy-confirm-btn">Hierher Kopieren</button>
                        <button type="button" class="btn btn-primary" id="fm-move-confirm-btn">Hierher Verschieben</button>
                    </div>
                 </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fm-upload-modal" tabindex="-1" aria-labelledby="fm-upload-modalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
  
               
                 <div class="modal-header">
                     <h5 class="modal-title" id="fm-upload-modalLabel">Dateien hochladen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
    
                 <div class="modal-body">
                 
                    <p>Dateien werden in das Verzeichnis <code id="fm-upload-target-dir"></code> hochgeladen.</p>
                     <form id="fm-upload-form">
                     
                        <input class="form-control" type="file" id="fm-upload-files-input" multiple>
                    </form>
                 </div>
  
                  <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                     <button type="button" class="btn btn-primary" id="fm-upload-confirm-btn">Hochladen</button>
                </div>
            </div>
        
         </div>
    </div>

    <div class="modal fade" id="fm-chmod-modal" tabindex="-1" aria-labelledby="fm-chmod-modalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
     
                 <div class="modal-header">
                    <h5 class="modal-title" id="fm-chmod-modalLabel">Berechtigungen ändern</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                 <div class="modal-body">
       
                     <p>Element: <code id="fm-chmod-item-name"></code></p>
                    <table class="table table-sm text-center">
                    
                         <thead>
                 
                             <tr>
                                <th></th>
                                <th>Lesen (r)</th>
 
                      
                                <th>Schreiben (w)</th>
                                <th>Ausführen (x)</th>
                            </tr>
      
                     
                         </thead>
                         <tbody>
                            <tr>
                            
               
                               <td><strong>Besitzer</strong></td>
                                 <td><input type="checkbox" class="form-check-input" data-chmod-val="256"></td>
                                <td><input type="checkbox" class="form-check-input" data-chmod-val="128"></td>
           
              
                                 <td><input type="checkbox" class="form-check-input" data-chmod-val="64"></td>
                             </tr>
                      
                     <tr>
                               
                                 <td><strong>Gruppe</strong></td>
                              
                               <td><input type="checkbox" class="form-check-input" data-chmod-val="32"></td>
                                <td><input type="checkbox" class="form-check-input" data-chmod-val="16"></td>
                            
                               
                               <td><input type="checkbox" class="form-check-input" data-chmod-val="8"></td>
                             </tr>
                            <tr>
                                <td><strong>Andere</strong></td>
   
   
                                   <td><input type="checkbox" class="form-check-input" data-chmod-val="4"></td>
                                 <td><input type="checkbox" class="form-check-input" data-chmod-val="2"></td>
                             
   
                                 <td><input type="checkbox" class="form-check-input" data-chmod-val="1"></td>
                            </tr>
                         </tbody>
         
                     </table>
                    <div class="text-center">Numerischer Wert: 
<strong id="fm-chmod-numeric-val"></strong></div>
                 </div>
                <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
      
                     <button type="button" class="btn btn-primary" id="fm-chmod-confirm-btn">Speichern</button>
                
                </div>
             </div>
        </div>
    </div>

    <div class="modal fade" id="fm-unzip-modal" tabindex="-1" aria-labelledby="fm-unzip-modalLabel" aria-hidden="true">
         <div class="modal-dialog">
     
             <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fm-unzip-modalLabel">Archiv entpacken</h5>
  
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
        
                 <div class="modal-body">
                    <p>Wie soll das Archiv <code id="fm-unzip-item-name"></code> entpackt werden?</p>
                
                </div>
                 <div class="modal-footer justify-content-between">
            
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                     <div>
                        <button type="button" class="btn btn-primary" id="fm-unzip-to-folder-btn">In neuen Ordner</button>
      
                          <button type="button" class="btn btn-info" id="fm-unzip-here-btn">Hier 
 entpacken</button>
                    </div>
                 </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmModalLabel" aria-hidden="true">
     
        <div class="modal-dialog">
             <div class="modal-content">
 
                 <div class="modal-header">
                     <h5 class="modal-title" id="customConfirmModalLabel">Bestätigung erforderlich</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
 
                  <div 
 class="modal-body" id="customConfirmModalBody">
                    </div>
                 <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="customConfirmCancelBtn">Abbrechen</button>
                   
                 
                     <button type="button" class="btn btn-primary" id="customConfirmActionBtn">Bestätigen</button>
                 </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="customPromptModal" tabindex="-1" aria-labelledby="customPromptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
  
    
                      <h5 class="modal-title" id="customPromptModalLabel">Eingabe erforderlich</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                <div class="modal-body">
              
                     <p id="customPromptModalBody"></p>
  
                      <input type="text" class="form-control" id="customPromptInput">
                </div>
                <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
        
             
                     <button type="button" class="btn btn-primary" id="customPromptConfirmBtn">OK</button>
                 </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
         <div id="appToast" class="toast" role="alert" 
 aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle"></strong>
 
                  <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody"></div>
        </div>
    </div>

     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
 
    <script src="assets/js/main.js"></script>
</body>
</html>