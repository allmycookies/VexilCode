<?php
// tools/editor.php

session_start();
if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../helpers.php';
generate_csrf_token();
$filepath = $_GET['file'] ?? null;
$content = '';
$error = '';
$is_writable = false;
$displayPath = 'Fehler';
if (!$filepath) {
    $error = "Kein Dateipfad angegeben.";
} else {
    require_once __DIR__ .
 '/../config/config.php';
    
    $baseDir = realpath($ROOT_PATH);
    $realFilepath = realpath($filepath);

    if ($realFilepath === false || strpos($realFilepath, $baseDir) !== 0) {
        $error = "Zugriff auf die angeforderte Datei verweigert.";
        $filepath = null;
    } elseif (!is_file($realFilepath) || !is_readable($realFilepath)) {
        $error = "Datei nicht gefunden oder nicht lesbar.";
        $filepath = null;
    } else {
        $file_content_raw = file_get_contents($realFilepath);
        if (function_exists('mb_check_encoding') && !mb_check_encoding($file_content_raw, 'UTF-8')) {
            $content = mb_convert_encoding($file_content_raw, 'UTF-8');
        } else {
            $content = $file_content_raw;
        }
        $is_writable = is_writable($realFilepath);
        // Pfad relativ zum WEB_ROOT_PATH machen
        $webRoot = realpath($WEB_ROOT_PATH);
        if ($webRoot && strpos($realFilepath, $webRoot) === 0) {
            $displayPath = str_replace($webRoot, '', $realFilepath);
            $displayPath = str_replace(DIRECTORY_SEPARATOR, '/', $displayPath);
            if (substr($displayPath, 0, 1) !== '/') {
                $displayPath = '/' .
 $displayPath;
            }
        } else {
            $displayPath = basename($realFilepath);
            // Fallback auf Dateinamen
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" class="editor-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <title>Editor - <?php echo htmlspecialchars(basename($filepath ?? 'Fehler'));
 ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="editor-body">

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
        <div class="alert alert-danger m-3"><?php echo $error;
 ?></div>
    <?php else: ?>
        <div class="top-bar bg-body-tertiary d-flex align-items-center p-2 border-bottom">
            <div class="editor-actions">
                <button id="closeBtn" class="btn btn-secondary btn-sm" title="Schließen"><i class="fas fa-times"></i></button>
                <div class="btn-group btn-group-sm" role="group">
                    <button id="undoBtn" class="btn btn-outline-secondary" title="Rückgängig 
 (Strg+Z)"><i class="fas fa-undo"></i></button>
                    <button id="redoBtn" class="btn btn-outline-secondary" title="Wiederholen (Strg+Y)"><i class="fas fa-redo"></i></button>
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button id="searchBtn" class="btn btn-outline-secondary" title="Suchen (Strg+F)"><i class="fas fa-search"></i></button>
                    <button id="beautifyBtn" class="btn btn-outline-secondary" title="Code formatieren (Strg+Shift+F)"><i class="fas fa-magic"></i></button>
                </div>
                <button id="llmBtn" class="btn btn-info btn-sm" title="LLM-Assistent (Strg+M)"><i class="fas fa-robot"></i></button>
                <div class="btn-group btn-group-sm" role="group">
                    <button id="saveBtn" class="btn btn-primary" disabled title="Speichern (Strg+S)"><i class="fas fa-save"></i></button>
                    <button id="backupSaveBtn" 
 class="btn btn-info" disabled title="Backup & Speichern (Strg+Shift+S)"><i class="fas fa-copy"></i></button>
                </div>
            </div>
            <span class="editor-filepath text-truncate" title="<?php echo htmlspecialchars($filepath);
 ?>">
                <i class="fas fa-file-alt fa-fw me-1 text-body-secondary"></i><?php echo htmlspecialchars($displayPath); ?>
                <?php if (!$is_writable): ?>
                    <span class="badge bg-warning text-dark ms-2"><i class="fas fa-lock"></i></span>
                <?php endif; ?>
            </span>
 
             </div>
        
        <div class="editor-container" id="main-container">
            <div id="editor-wrapper">
                 <div id="editor"></div>
                 <div class="editor-status-bar">
                    <span>Größe: <strong id="file-size-kb">0.00</strong> KB</span>
                    <span>Zeichen: <strong id="char-count">0</strong></span>
                    <span>Zeilen: <strong id="line-count">1</strong></span>
                 </div>
            </div>
            <div id="llm-wrapper" class="d-none">
                <div class="card 
 h-100">
                    <div class="card-header d-flex justify-content-between align-items-center p-2">
                        <span class="small">KI-Assistent</span>
                        <div class="spinner-border spinner-border-sm text-primary d-none" id="llm-spinner" role="status"></div>
                    
                     </div>
                    <div class="card-body p-2 d-flex flex-column">
                        <div id="llm-feedback" class="flex-grow-1 p-2 bg-body-tertiary rounded mb-2" style="overflow-y: auto;
 font-size: 0.9em;"></div>
                        <div id="llm-prompt-container">
                            <textarea id="llm-prompt" class="form-control form-control-sm" placeholder="Prompt eingeben (Strg+Enter zum Senden)" rows="3"></textarea>
                            <button id="llm-send-btn" class="btn btn-primary btn-sm" title="Prompt an KI senden"><i 
 class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://unpkg.com/prettier@2.8.8/standalone.js"></script>
        <script src="https://unpkg.com/prettier@2.8.8/parser-babel.js"></script>
        <script src="https://unpkg.com/prettier@2.8.8/parser-html.js"></script>
        <script src="https://unpkg.com/prettier@2.8.8/parser-postcss.js"></script>
        <script src="https://unpkg.com/@prettier/plugin-php@0.19.6/standalone.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/ace/src-noconflict/ace.js" 
 type="text/javascript" charset="utf-8"></script>
        <script src="assets/js/ace/src-noconflict/ext-searchbox.js" type="text/javascript" charset="utf-8"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const savedTheme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
                const editorTheme = savedTheme === 'dark' ? 'ace/theme/tomorrow_night_eighties' : 'ace/theme/chrome';
                if (typeof ace === 'undefined') {
                    document.getElementById('editor').innerHTML = '<div class="alert alert-danger m-3">Ace Editor konnte nicht geladen werden.</div>';
                    return;
                }

                const editor = ace.edit("editor");
                const saveBtn = document.getElementById('saveBtn');
                const backupSaveBtn = document.getElementById('backupSaveBtn');
                const closeBtn = document.getElementById('closeBtn');
                const undoBtn = document.getElementById('undoBtn');
                const redoBtn = document.getElementById('redoBtn');
                const searchBtn = document.getElementById('searchBtn');
                const beautifyBtn = document.getElementById('beautifyBtn');
                const toastElement = document.getElementById('editorToast');
                const toast = new bootstrap.Toast(toastElement);
                // LLM elements
                const llmBtn = document.getElementById('llmBtn');
                const mainContainer = document.getElementById('main-container');
                const llmWrapper = document.getElementById('llm-wrapper');
                const llmPrompt = document.getElementById('llm-prompt');
                const llmSendBtn = document.getElementById('llm-send-btn');
                const llmFeedback = document.getElementById('llm-feedback');
                const llmSpinner = document.getElementById('llm-spinner');
                // Status bar elements
                const statusBar_size = document.getElementById('file-size-kb');
                const statusBar_chars = document.getElementById('char-count');
                const statusBar_lines = document.getElementById('line-count');

                editor.setTheme(editorTheme);
                editor.session.setMode(getAceMode("<?php echo $filepath; ?>"));
                
                <?php
                    $jsonContent = json_encode($content);
                    if ($jsonContent === false) {
                        $error_message = "FEHLER: Die Datei konnte nicht als valides UTF-8 interpretiert werden und kann nicht im Editor geladen werden.\\n\\n" .
 json_last_error_msg();
                        $jsonContent = json_encode($error_message);
                        echo "editor.setReadOnly(true);\n";
                    }
                ?>
                editor.setValue(<?php echo $jsonContent; ?>, -1);
                editor.setReadOnly(<?php echo $is_writable ? 'false' : 'true'; ?>);
                
                editor.focus();
                editor.clearSelection();
                
                function updateStatusBar() {
                    const content = editor.getValue();
                    statusBar_lines.textContent = editor.session.getLength();
                    statusBar_chars.textContent = content.length;
                    const sizeInBytes = new Blob([content]).size;
                    statusBar_size.textContent = (sizeInBytes / 1024).toFixed(2);
                }

                editor.session.on('change', function() {
                    if (!editor.getReadOnly()) {
                        saveBtn.disabled = false;
                        backupSaveBtn.disabled = false;
                    }
                });
                
                // Initial call to populate status bar
                updateStatusBar();
                
                // Set interval to update status bar periodically for performance
                setInterval(updateStatusBar, 3300);

                closeBtn.addEventListener('click', () => { window.close(); });
                undoBtn.addEventListener('click', () => editor.undo());
                redoBtn.addEventListener('click', () => editor.redo());
                searchBtn.addEventListener('click', () => editor.execCommand("find"));
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        if (document.querySelector('.modal.show') || document.querySelector('.ace_search.show')) {
                            return;
                  
                   }
                        window.close();
                    }
                });

                // --- BEAUTIFY LOGIC ---
                function getPrettierParser(filepath) {
                    const ext = filepath.split('.').pop().toLowerCase();
                    const parserMap = {
                        'js': 'babel', 'json': 'json', 'html': 'html',
                        'css': 'css', 'php': 'php', 'md': 'markdown',
                        'yaml': 'yaml', 'sh': 'sh'
                    };
                    return parserMap[ext] || null;
                }

                async function beautifyCode() {
                    if (typeof prettier === 'undefined' || typeof prettierPlugins === 'undefined') {
                        showToast('error', 'Prettier-Bibliothek nicht geladen.');
                        return;
                    }
                    const parser = getPrettierParser("<?php echo $filepath; ?>");
                    if (!parser) {
                        showToast('warning', 'Kein Beautifier für diesen Dateityp verfügbar.');
                        return;
                    }
                    try {
                        const formattedCode = prettier.format(editor.getValue(), {
                            parser: parser,
                            plugins: prettierPlugins,
                            printWidth: 120,
                            tabWidth: 4,
                            singleQuote: true
                        });
                        editor.setValue(formattedCode, -1);
                        showToast('success', 'Code erfolgreich formatiert.');
                    } catch (error) {
                        showToast('error', 'Formatierung fehlgeschlagen: ' + error.message);
                    }
                }

                beautifyBtn.addEventListener('click', beautifyCode);
                editor.commands.addCommand({
                    name: 'beautifyCode',
                    bindKey: {win: 'Ctrl-Shift-F', mac: 'Command-Shift-F'},
                    exec: function(editor) { beautifyCode(); }
                });

                // Disable button if parser is not available
                if (!getPrettierParser("<?php echo $filepath; ?>")) {
                    beautifyBtn.disabled = true;
                    beautifyBtn.title = 'Kein Beautifier für diesen Dateityp verfügbar.';
                }


                // --- LLM LOGIC ---
                function toggleLlmView() {
                    mainContainer.classList.toggle('llm-active');
                    const isActive = mainContainer.classList.contains('llm-active');
                    llmWrapper.classList.toggle('d-none', !isActive);
                    editor.resize();
                    if (isActive) {
                        llmPrompt.focus();
                    } else {
                        editor.focus();
                    }
                }

                llmBtn.addEventListener('click', toggleLlmView);
                llmSendBtn.addEventListener('click', sendToLlm);

                llmPrompt.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        sendToLlm();
                    
                    }
                });
                async function sendToLlm() {
                    const selectedText = editor.getSelectedText();
                    const contentToSend = selectedText || editor.getValue();
                    const userPrompt = llmPrompt.value;
                    if (!userPrompt.trim()) {
                        showToast('error', 'Bitte geben Sie einen Prompt ein.');
                        return;
                    }

                    llmSpinner.classList.remove('d-none');
                    llmPrompt.disabled = true;
                    llmSendBtn.disabled = true;

                    try {
                        const settingsResponse = await fetch('lib/settings_api.php?csrf_token=' + csrfToken);
                        if (!settingsResponse.ok) {
                             const errorText = await settingsResponse.text();
                            throw new Error('Einstellungen konnten nicht geladen werden: ' + errorText);
                        }
                        const settings = await settingsResponse.json();
                        const response = await fetch('lib/llm_api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
     
                                 provider: settings.llm_provider,
                                prompt: userPrompt,
                                content: contentToSend,
      
                                 csrf_token: csrfToken
                            })
                        });
                        const result = await response.json();

                        if (result.status !== 'success') {
                           throw new Error(result.message || 'Ein unbekannter API-Fehler ist aufgetreten.');
                        }
                        
                        processLlmResponse(result.response, !!selectedText);
                    } catch (error) {
                        showToast('error', 'Fehler: ' + error.message);
                        llmFeedback.innerHTML = `<div class="text-danger p-2">${error.message}</div>`;
                    } finally {
                        llmSpinner.classList.add('d-none');
                        llmPrompt.disabled = false;
                        llmSendBtn.disabled = false;
                        llmPrompt.focus();
                    }
                }

                function processLlmResponse(responseText, hasSelection) {
                    let codePart = null;
                    let explanationPart = null;
                    let toastMessage = '';
                    let toastStatus = 'info';

                    const codeRegex = /---LLM_CODE_BEGIN---([\s\S]*?)---LLM_CODE_END---/;
                    const codeMatch = responseText.match(codeRegex);
                    if (codeMatch && typeof codeMatch[1] === 'string') {
                        codePart = codeMatch[1].trim();
                    }

                    const explRegex = /---LLM_EXPL_BEGIN---([\s\S]*?)---LLM_EXPL_END---/;
                    const explMatch = responseText.match(explRegex);
                    if (explMatch && typeof explMatch[1] === 'string') {
                        explanationPart = explMatch[1].trim();
                    }

                    if (codePart !== null) {
                        if (hasSelection) {
                            editor.session.replace(editor.getSelectionRange(), codePart);
                        } else {
                            editor.setValue(codePart, 1);
                        }
                        toastMessage = 'Code erfolgreich aktualisiert.';
                        toastStatus = 'success';
                    }

                    if (explanationPart !== null) {
                        llmFeedback.textContent = explanationPart;
                        if (!toastMessage) {
                            toastMessage = 'Erklärung erhalten.';
                        }
                    }

                    if (codePart === null && explanationPart === null) {
                        llmFeedback.textContent = responseText.trim();
                        toastMessage = 'Antwort ohne Standard-Formatierung erhalten.';
                        toastStatus = 'warning';
                    } else if (codePart !== null && explanationPart === null) {
                        llmFeedback.textContent = 'Keine Erklärung vom Modell erhalten.';
                    }

                    showToast(toastStatus, toastMessage);
                }

                editor.commands.addCommand({
                    name: 'toggleLlm',
                    bindKey: {win: 'Ctrl-M', mac: 'Command-M'},
                    exec: function(editor) { toggleLlmView(); }
         
                 });
                
                // --- END LLM LOGIC ---


                async function handleSave(withBackup) {
                    saveBtn.disabled = true;
                    backupSaveBtn.disabled = true;
                    
                    const bodyParams = new URLSearchParams({
                        filepath: "<?php echo addslashes($filepath); ?>",
                        content: editor.getValue(),
                        backup: withBackup ? '1' : '0',
           
                         csrf_token: csrfToken
                    });
                    try {
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
                    } catch (e) {
                         showToast('error', 'Speichern fehlgeschlagen: ' + e.message);
                         saveBtn.disabled = false;
                         backupSaveBtn.disabled = false;
                    }
                }

                saveBtn.addEventListener('click', () => handleSave(false));
                backupSaveBtn.addEventListener('click', () => handleSave(true));

                editor.commands.addCommand({
                    name: 'saveFile',
                    bindKey: {win: 'Ctrl-S', mac: 'Command-S'},
                    exec: function(editor) { if (!saveBtn.disabled) saveBtn.click(); }
                });
                editor.commands.addCommand({
                    name: 'saveFileWithBackup',
                    bindKey: {win: 'Ctrl-Shift-S', mac: 'Command-Shift-S'},
                    exec: function(editor) { if (!backupSaveBtn.disabled) backupSaveBtn.click(); }
                });
                
                function showToast(status, message) {
                    const toastTitle = document.getElementById('toastTitle');
                    const toastBody = document.getElementById('toastBody');
                    
                    toastElement.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-white', 'text-dark');
                    if (status === 'success') {
                        toastTitle.textContent = 'Erfolg';
                        toastElement.classList.add('bg-success', 'text-white');
                    } else if (status === 'warning') {
                        toastTitle.textContent = 'Hinweis';
                        toastElement.classList.add('bg-warning', 'text-dark');
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