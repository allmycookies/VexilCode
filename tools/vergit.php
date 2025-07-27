<?php // tools/vergit.php

require_once __DIR__ . '/../lib/vergit.class.php';

function renderToolUI($settings, $logMessages) {
    global $WEB_ROOT_PATH;
    $vergit = new Vergit();
    $projects = $vergit->getProjects();
    $selectedProjectId = $_GET['project_id'] ?? null;
    $selectedProject = $selectedProjectId ? $vergit->getProject($selectedProjectId) : null;
    
    $storage_path = $settings['vergit_storage_path'] ?? realpath(__DIR__ . '/../data/vergit_projects');

    function getChannelUrl($project, $channel, $webRoot) {
        if (!$project) return ['url' => 'N/A', 'exists' => false];
        $linkPath = $project['path'] . '/latest/' . $channel;
        if (!is_link($linkPath)) return ['url' => 'Nicht gesetzt', 'exists' => false];
        
        $realWebRoot = realpath($webRoot);
        if ($realWebRoot && strpos($linkPath, $realWebRoot) === 0) {
            $relativePath = str_replace($realWebRoot, '', $linkPath);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            return ['url' => $protocol . $domain . $relativePath, 'exists' => true];
        }
        return ['url' => 'Nicht im Web-Root', 'exists' => false];
    }
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-code-branch me-2"></i>Vergit Projekte</h5>
            </div>
            <div class="card-body">
                <form method="post" action="?tool=vergit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="create_project">
                    <div class="input-group">
                        <input type="text" name="project_name" class="form-control" placeholder="Neues Projekt erstellen..." required>
                        <button class="btn btn-primary" type="submit" title="Projekt erstellen"><i class="fas fa-plus"></i></button>
                    </div>
                </form>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($projects)): ?>
                    <li class="list-group-item text-muted">Noch keine Projekte vorhanden.</li>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo ($selectedProjectId === $project['id']) ? 'active' : ''; ?>">
                            <a href="?tool=vergit&project_id=<?php echo $project['id']; ?>" class="text-decoration-none stretched-link text-reset">
                                <?php echo htmlspecialchars($project['name']); ?>
                            </a>
                            <span style="z-index: 2;">
                                <form method="post" action="?tool=vergit" class="d-inline-block me-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="repair_project">
                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Projektstruktur & Symlinks reparieren">
                                        <i class="fas fa-wrench"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-archive-project" 
                                    data-project-id="<?php echo $project['id']; ?>" data-project-name="<?php echo htmlspecialchars($project['name']); ?>" title="Projekt archivieren">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-cog me-2"></i>Konfiguration</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted">Ablageort für Projekte und Archive. Gespeichert in <code>config/settings.json</code>.</p>
                <form method="post" action="?tool=vergit<?php echo $selectedProjectId ? '&project_id='.$selectedProjectId : ''; ?>" data-settings-form="vergit">
                     <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="save_vergit_settings">
                    <div class="mb-3">
                        <label for="vergit_storage_path" class="form-label">Ablageort</label>
                        <div class="input-group">
                             <input type="text" class="form-control" name="vergit_storage_path" id="vergit_storage_path" value="<?php echo htmlspecialchars($storage_path); ?>">
                            <button class="btn btn-outline-secondary btn-folder-picker" type="button" data-target-input="#vergit_storage_path"><i class="fas fa-folder-open"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">Speichern</button>
                </form>
            </div>
        </div>
        
        <?php
        $archives = $vergit->getArchives();
        ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-box-archive me-2"></i>Projekt-Archiv</h5>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($archives)): ?>
                    <li class="list-group-item text-muted">Das Archiv ist leer.</li>
                <?php else: ?>
                    <?php foreach($archives as $archive): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($archive['original_project_data']['name']); ?></strong>
                                <small class="text-muted d-block">Archiviert am: <?php echo date('d.m.Y H:i', strtotime($archive['archived_at'])); ?></small>
                            </div>
                            <div class="btn-group">
                                <form method="post" action="?tool=vergit" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="restore_project">
                                    <input type="hidden" name="archive_id" value="<?php echo $archive['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Projekt wiederherstellen">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                                <form method="post" action="?tool=vergit" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_archive">
                                    <input type="hidden" name="archive_id" value="<?php echo $archive['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Soll das Archiv für '<?php echo htmlspecialchars($archive['original_project_data']['name']); ?>' wirklich ENDGÜLTIG gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden." title="Archiv endgültig löschen">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($selectedProject):
            $versions = $vergit->getVersions($selectedProjectId);
            $lastVersion = $versions[0] ?? null;
            
            $betaVersionId = $selectedProject['beta_version_id'] ?? null;
            $betaVersion = $betaVersionId ? $vergit->getVersion($betaVersionId) : null;

            $stableVersionId = $selectedProject['stable_version_id'] ?? null;
            $stableVersion = $stableVersionId ? $vergit->getVersion($stableVersionId) : null;

            $currentUrl = getChannelUrl($selectedProject, 'current', $WEB_ROOT_PATH);
            $betaUrl = getChannelUrl($selectedProject, 'beta', $WEB_ROOT_PATH);
            $stableUrl = getChannelUrl($selectedProject, 'stable', $WEB_ROOT_PATH);
        ?>
            <div class="card mb-4">
                <div class="card-header"><h5><i class="fas fa-globe-europe me-2"></i>Veröffentlichungen & Links</h5></div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tbody>
                            <tr>
                                <td class="fw-bold align-middle"><span class="badge bg-secondary">Current</span></td>
                                <td class="align-middle"><small class="text-muted"><?php echo $lastVersion ? htmlspecialchars($lastVersion['version_number']) : 'N/A'; ?></small></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentUrl['url']); ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)" <?php if (!$currentUrl['exists']) echo 'disabled'; ?>><i class="fas fa-copy"></i></button>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td class="fw-bold align-middle"><span class="badge bg-warning text-dark">Beta</span></td>
                                <td class="align-middle"><small class="text-muted"><?php echo $betaVersion ? htmlspecialchars($betaVersion['version_number']) : 'N/A'; ?></small></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($betaUrl['url']); ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)" <?php if (!$betaUrl['exists']) echo 'disabled'; ?>><i class="fas fa-copy"></i></button>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <?php if ($betaVersion): ?>
                                    <button onclick="submitVersionAction('unset_channel_beta', '')" class="btn btn-sm btn-outline-danger" title="Beta-Veröffentlichung zurückziehen"><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold align-middle"><span class="badge bg-success">Stable</span></td>
                                <td class="align-middle"><small class="text-muted"><?php echo $stableVersion ? htmlspecialchars($stableVersion['version_number']) : 'N/A'; ?></small></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($stableUrl['url']); ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)" <?php if (!$stableUrl['exists']) echo 'disabled'; ?>><i class="fas fa-copy"></i></button>
                                    </div>
                                </td>
                                <td class="align-middle">
                                     <?php if ($stableVersion): ?>
                                    <button onclick="submitVersionAction('unset_channel_stable', '')" class="btn btn-sm btn-outline-danger" title="Stable-Veröffentlichung zurückziehen"><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>Versionen für: <?php echo htmlspecialchars($selectedProject['name']); ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="?tool=vergit&project_id=<?php echo $selectedProjectId; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="create_version">
                        <input type="hidden" name="project_id" value="<?php echo $selectedProjectId; ?>">
                        <div class="mb-3">
                            <label for="version_number" class="form-label">Neue Versionsnummer</label>
                            <input type="text" name="version_number" id="version_number" class="form-control" placeholder="z.B. 1.0.0, 1.1.0-alpha1, etc." required>
                            <?php if($lastVersion): ?><small class="form-text text-muted">Letzte Version war: <?php echo htmlspecialchars($lastVersion['version_number']); ?></small><?php endif; ?>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="copy_last" id="copy_last" value="1" <?php echo $lastVersion ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="copy_last">Inhalt der letzten Version (<?php echo htmlspecialchars($lastVersion['version_number'] ?? 'N/A'); ?>) kopieren</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Neue Version anlegen</button>
                    </form>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (empty($versions)): ?>
                        <li class="list-group-item text-muted">Noch keine Versionen für dieses Projekt vorhanden.</li>
                    <?php else: ?>
                        <?php foreach ($versions as $version): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($version['version_number']); ?></strong>
                                        <?php if ($version['id'] === $betaVersionId) echo '<span class="badge bg-warning text-dark ms-2">Beta</span>'; ?>
                                        <?php if ($version['id'] === $stableVersionId) echo '<span class="badge bg-success ms-2">Stable</span>'; ?>
                                        <small class="text-muted d-block">Erstellt: <?php echo $version['created_at']; ?></small>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-cog me-1"></i> Aktionen</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><button class="dropdown-item" type="button" onclick="submitVersionAction('set_channel_beta', '<?php echo $version['id']; ?>')"><i class="fas fa-flask fa-fw me-2"></i>Als Beta setzen</button></li>
                                            <li><button class="dropdown-item" type="button" onclick="submitVersionAction('set_channel_stable', '<?php echo $version['id']; ?>')"><i class="fas fa-check-circle fa-fw me-2"></i>Als Stable setzen</button></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#releaseModal" data-version-id="<?php echo $version['id']; ?>" data-version-number="<?php echo htmlspecialchars($version['version_number']); ?>"><i class="fas fa-rocket fa-fw me-2"></i>Release erstellen...</button></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="?tool=filemanager&path=<?php echo urlencode($version['path']); ?>"><i class="fas fa-folder-open fa-fw me-2"></i>Dateimanager</a></li>
                                            <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#instanceModal" data-version-id="<?php echo $version['id']; ?>" data-version-number="<?php echo htmlspecialchars($version['version_number']); ?>"><i class="fas fa-server fa-fw me-2"></i>Instanzen</button></li>
                                            <li><button class="dropdown-item btn-show-diff" type="button" data-bs-toggle="collapse" data-bs-target="#diff-<?php echo $version['id']; ?>" data-version-path="<?php echo htmlspecialchars($version['path']); ?>"><i class="fas fa-exchange-alt fa-fw me-2"></i>Diff anzeigen</button></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-danger" type="button" onclick="submitVersionAction('delete_version', '<?php echo $version['id']; ?>', 'Möchtest du die Version \'<?php echo htmlspecialchars($version['version_number']); ?>\' wirklich endgültig löschen?')"><i class="fas fa-trash-alt fa-fw me-2"></i>Löschen</button></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="collapse mt-3" id="diff-<?php echo $version['id']; ?>"><div class="diff-container-wrapper p-3 bg-light border rounded"></div></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
             <div class="card mt-4">
                 <div class="card-header"><h5><i class="fas fa-archive me-2"></i>Release-Archive</h5></div>
                 <ul class="list-group list-group-flush">
                 <?php
                     $releaseDir = $selectedProject['path'] . '/release';
                     $releaseFiles = is_dir($releaseDir) ? array_diff(scandir($releaseDir), ['.', '..']) : [];
                     if (empty($releaseFiles)):
                 ?>
                     <li class="list-group-item text-muted">Keine Release-Archive für dieses Projekt vorhanden.</li>
                 <?php else: ?>
                     <?php foreach ($releaseFiles as $file): 
                         $filePath = $releaseDir . DIRECTORY_SEPARATOR . $file;
                         if(!is_file($filePath) || !str_ends_with($file, '.zip')) continue;
                     ?>
                         <li class="list-group-item d-flex justify-content-between align-items-center">
                             <div>
                                 <i class="fas fa-file-archive fa-fw text-secondary"></i>
                                 <strong class="font-monospace"><?php echo htmlspecialchars($file); ?></strong>
                                 <small class="text-muted d-block">Größe: <?php echo round(filesize($filePath) / 1024, 2); ?> KB</small>
                             </div>
                             <div class="btn-group">
                                 <a href="lib/file_manager_api.php?action=download&path=<?php echo urlencode($filePath); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>Download</a>
                                 <button class="btn btn-sm btn-outline-danger" onclick="submitVersionAction('delete_release_file', '<?php echo htmlspecialchars($file); ?>', 'Soll das Release-Archiv \'<?php echo htmlspecialchars($file); ?>\' wirklich gelöscht werden?')"><i class="fas fa-trash me-1"></i>Löschen</button>
                             </div>
                         </li>
                     <?php endforeach; ?>
                 <?php endif; ?>
                 </ul>
             </div>
        <?php else: ?>
            <div class="alert alert-info">Bitte wählen Sie ein Projekt aus der Liste aus oder erstellen Sie ein neues, um dessen Versionen zu verwalten.</div>
        <?php endif; ?>
    </div>
</div>

<form id="versionActionForm" method="post" action="?tool=vergit&project_id=<?php echo $selectedProjectId; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="project_id" value="<?php echo $selectedProjectId; ?>">
    <input type="hidden" name="action" id="form_action_id">
    <input type="hidden" name="version_id" id="form_version_id">
    <input type="hidden" name="file_name" id="form_file_name_id">
</form>
<script>
function submitVersionAction(action, id, confirmMsg = null) {
    const doSubmit = () => {
        document.getElementById('form_action_id').value = action;
        if (action === 'delete_release_file') {
             document.getElementById('form_file_name_id').value = id;
        } else {
             document.getElementById('form_version_id').value = id;
        }
        document.getElementById('versionActionForm').submit();
    };

    if (confirmMsg) {
        if (confirm(confirmMsg)) {
            doSubmit();
        }
    } else {
        doSubmit();
    }
}
</script>

<div class="modal fade" id="releaseModal" tabindex="-1" aria-labelledby="releaseModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="releaseModalLabel">Release-Archiv erstellen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="?tool=vergit&project_id=<?php echo $selectedProjectId; ?>">
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
             <input type="hidden" name="action" value="create_release_zip">
            <input type="hidden" name="version_id" id="modal_version_id">
            <input type="hidden" name="project_id" value="<?php echo $selectedProjectId; ?>">
            <p>Ein bereinigtes ZIP-Archiv der Version <strong id="release_version_number_modal"></strong> wird erstellt.</p>
            <div class="mb-3">
                <label for="release_name" class="form-label">Name des Archivs (ohne .zip)</label>
                <input type="text" name="release_name" id="release_name" class="form-control" placeholder="z.B. my-project-v1.0-final" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-rocket me-1"></i>Release jetzt erstellen</button>
          </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="instanceModal" tabindex="-1" aria-labelledby="instanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
       <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="instanceModalLabel">Instanzen für Version <span id="instance-version-number" class="fw-bold"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <div class="d-flex justify-content-between align-items-center mb-3">
             <p class="mb-0 text-muted small">Verwalten Sie Test-Instanzen für diese Version. Jede Instanz ist eine eigenständige Kopie.</p>
             <button type="button" class="btn btn-primary" id="create-instance-btn"><i class="fas fa-plus me-2"></i>Neue Instanz erstellen</button>
         </div>
         <div id="instance-list-container">
            <div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>
          </div>
      </div>
    </div>
  </div>
</div>
<?php
// JS zum Befüllen des Release-Modals
$script = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    const releaseModalEl = document.getElementById('releaseModal');
    if(releaseModalEl) {
        releaseModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const versionId = button.getAttribute('data-version-id');
            const versionNumber = button.getAttribute('data-version-number');
            releaseModalEl.querySelector('#modal_version_id').value = versionId;
            releaseModalEl.querySelector('#release_version_number_modal').textContent = versionNumber;
            releaseModalEl.querySelector('#release_name').value = 'release-' + versionNumber;
        });
    }
});
JS;
echo '<script>'.$script.'</script>';
}
?>