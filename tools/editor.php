<?php
// tools/editor.php

session_start();
if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../helpers.php';
generate_csrf_token(); // Sicherstellen, dass ein Token existiert

$filepath = $_GET['file'] ?? null;
$content = '';
$error = '';

if (!$filepath) {
    $error = "Kein Dateipfad angegeben.";
} else {
    require_once __DIR__ . '/../config/config.php';
    $baseDir = realpath($ROOT_PATH);
    $realFilepath = realpath($filepath);

    if ($realFilepath === false || strpos($realFilepath, $baseDir) !== 0) {
        $error = "Zugriff auf die angeforderte Datei verweigert.";
        $filepath = null;
    } elseif (!is_file($realFilepath) || !is_readable($realFilepath)) {
        $error = "Datei nicht gefunden oder nicht lesbar.";
        $filepath = null;
    } else {
        $content = file_get_contents($realFilepath);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSRF Token für JavaScript-Aufrufe -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <title>Editor - <?php echo $filepath ? htmlspecialchars(basename($filepath)) : 'Fehler'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body, html { height: 100%; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .editor-container { flex-grow: 1; display: flex; flex-direction: column; }
        #editor { width: 100%; flex-grow: 1; }
        .top-bar { flex-shrink: 0; }
        .ace_search {
            background-color: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
            color: var(--bs-body-color);
        }
        .ace_search_form, .ace_replace_form {
            background-color: var(--bs-tertiary-bg);
            border-bottom: 1px solid var(--bs-border-color);
        }
        .ace_search_field {
            background-color: var(--bs-body-bg);
            color: var(--bs-body-color);
            border: 1px solid var(--bs-border-color);
        }
        .ace_button {
            background-color: var(--bs-secondary-bg);
            color: var(--bs-body-color);
            border: 1px solid var(--bs-border-color);
        }
        .ace_button.checked {
            background-color: var(--bs-primary);
            color: white;
        }
    </style>
</head>
<body>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <div id="editorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle"></strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody"></div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger m-3"><?php echo $error; ?></div>
    <?php else: ?>
        <div class="top-bar bg-body-tertiary d-flex align-items-center p-2 border-bottom">
            <button id="closeBtn" class="btn btn-secondary btn-sm me-3"><i class="fas fa-times me-1"></i> Schließen</button>
            <i class="fas fa-file-alt fa-fw me-2 text-body-secondary"></i>
            <span class="me-auto text-truncate" title="<?php echo htmlspecialchars($filepath); ?>"><?php echo htmlspecialchars($filepath); ?></span>
            
            <div class="btn-group btn-group-sm me-2" role="group">
                <button id="undoBtn" class="btn btn-outline-secondary" title="Rückgängig (Strg+Z)"><i class="fas fa-undo"></i></button>
                <button id="redoBtn" class="btn btn-outline-secondary" title="Wiederholen (Strg+Y)"><i class="fas fa-redo"></i></button>
            </div>
            <div class="btn-group btn-group-sm me-2" role="group">
                <button id="searchBtn" class="btn btn-outline-secondary" title="Suchen (Strg+F)"><i class="fas fa-search"></i></button>
            </div>

            <button id="saveBtn" class="btn btn-primary btn-sm me-2" disabled title="Speichern (Strg+S)"><i class="fas fa-save me-1"></i> Speichern</button>
            <button id="backupSaveBtn" class="btn btn-info btn-sm" disabled title="Backup & Speichern (Strg+Shift+S)"><i class="fas fa-copy me-1"></i> Backup & Speichern</button>
        </div>
        <div class="editor-container">
            <div id="editor"></div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/ace/src-noconflict/ace.js" type="text/javascript" charset="utf-8"></script>
        <script src="assets/js/ace/src-noconflict/ext-searchbox.js" type="text/javascript" charset="utf-8"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const savedTheme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
                const editorTheme = savedTheme === 'dark' ? 'ace/theme/tomorrow_night_eighties' : 'ace/theme/chrome';
                
                if (typeof ace === 'undefined') {
                    document.getElementById('editor').innerHTML = '<div class="alert alert-danger m-3">Ace Editor nicht geladen.</div>';
                    return;
                }

                const editor = ace.edit("editor");
                const saveBtn = document.getElementById('saveBtn');
                const backupSaveBtn = document.getElementById('backupSaveBtn');
                const closeBtn = document.getElementById('closeBtn');
                const undoBtn = document.getElementById('undoBtn');
                const redoBtn = document.getElementById('redoBtn');
                const searchBtn = document.getElementById('searchBtn');
                const toastElement = document.getElementById('editorToast');
                const toast = new bootstrap.Toast(toastElement);

                editor.setTheme(editorTheme);
                editor.session.setMode(getAceMode("<?php echo $filepath; ?>"));
                editor.setValue(<?php echo json_encode($content); ?>, -1);
                editor.focus();
                editor.clearSelection();

                editor.session.on('change', function() {
                    saveBtn.disabled = false;
                    backupSaveBtn.disabled = false;
                });

                closeBtn.addEventListener('click', () => { window.close(); });
                undoBtn.addEventListener('click', () => editor.undo());
                redoBtn.addEventListener('click', () => editor.redo());
                searchBtn.addEventListener('click', () => editor.execCommand("find"));

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        window.close();
                    }
                });

                async function handleSave(withBackup) {
                    saveBtn.disabled = true;
                    backupSaveBtn.disabled = true;
                    
                    const bodyParams = new URLSearchParams({
                        filepath: "<?php echo addslashes($filepath); ?>",
                        content: editor.getValue(),
                        backup: withBackup ? '1' : '0',
                        csrf_token: csrfToken // CSRF-Token mitsenden
                    });

                    const response = await fetch('lib/file_save_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: bodyParams.toString()
                    });

                    const result = await response.json();
                    showToast(result.status, result.message);

                    if (result.status !== 'success') {
                        editor.session.once('change', function() {
                            saveBtn.disabled = false;
                            backupSaveBtn.disabled = false;
                        });
                    }
                }

                saveBtn.addEventListener('click', () => handleSave(false));
                backupSaveBtn.addEventListener('click', () => handleSave(true));

                editor.commands.addCommand({
                    name: 'saveFile',
                    bindKey: {win: 'Ctrl-S', mac: 'Command-S'},
                    exec: function(editor) { saveBtn.click(); }
                });
                editor.commands.addCommand({
                    name: 'saveFileWithBackup',
                    bindKey: {win: 'Ctrl-Shift-S', mac: 'Command-Shift-S'},
                    exec: function(editor) { backupSaveBtn.click(); }
                });

                function showToast(status, message) {
                    const toastTitle = document.getElementById('toastTitle');
                    const toastBody = document.getElementById('toastBody');
                    
                    toastElement.classList.remove('bg-success', 'bg-danger', 'text-white');
                    if (status === 'success') {
                        toastTitle.textContent = 'Erfolg';
                        toastElement.classList.add('bg-success', 'text-white');
                    } else {
                        toastTitle.textContent = 'Fehler';
                        toastElement.classList.add('bg-danger', 'text-white');
                    }
                    toastBody.textContent = message;
                    toast.show();
                }

                function getAceMode(filepath) {
                    const ext = filepath.split('.').pop().toLowerCase();
                    const modeMap = {
                        'js': 'ace/mode/javascript', 'json': 'ace/mode/json', 'html': 'ace/mode/html',
                        'css': 'ace/mode/css', 'php': 'ace/mode/php', 'md': 'ace/mode/markdown',
                        'sh': 'ace/mode/sh', 'sql': 'ace/mode/sql', 'xml': 'ace/mode/xml',
                        'yaml': 'ace/mode/yaml', 'ini': 'ace/mode/ini'
                    };
                    return modeMap[ext] || 'ace/mode/text';
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
