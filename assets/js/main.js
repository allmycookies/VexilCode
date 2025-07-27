document.addEventListener('DOMContentLoaded', function () {
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : null;

    /**
     * Normalisiert einen Pfad, indem doppelte Slashes entfernt und Backslashes ersetzt werden.
     * @param {string} path Der zu normalisierende Pfad.
     * @returns {string} Der normalisierte Pfad.
     */
    function normalizePath(path) {
        if (!path) return '';
        // Ersetzt doppelte Slashes (außer im Protokoll-Teil wie http://) und Backslashes
        return path.replace(/([^:])(\/\/+)/g, '$1/').replace(/\\/g, '/');
    }

    // --- Globale Theme-Switcher Logik ---
    const themeSwitcherBtn = document.getElementById('theme-switcher-btn');
    const htmlEl = document.documentElement;
    function setTheme(theme) {
        htmlEl.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        if(themeSwitcherBtn) themeSwitcherBtn.innerHTML = theme === 'dark' ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
    }
    if(themeSwitcherBtn) themeSwitcherBtn.addEventListener('click', () => setTheme(htmlEl.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark'));
    setTheme(localStorage.getItem('theme') || 'light');
    // --- Globale Toast-Funktion ---
    const appToastEl = document.getElementById('appToast');
    const appToast = appToastEl ? new bootstrap.Toast(appToastEl) : null;
    function showAppToast(status, message) {
        if (!appToast) return;
        const toastTitle = document.getElementById('toastTitle');
        appToastEl.classList.remove('bg-success', 'bg-danger', 'bg-info', 'text-white');
        if (status === 'success') { toastTitle.textContent = 'Erfolg'; appToastEl.classList.add('bg-success', 'text-white');
        }
        else if (status === 'error') { toastTitle.textContent = 'Fehler'; appToastEl.classList.add('bg-danger', 'text-white');
        }
        else { toastTitle.textContent = 'Hinweis'; appToastEl.classList.add('bg-info', 'text-white');
        }
        document.getElementById('toastBody').textContent = message;
        appToast.show();
    }

    // --- Allgemeine Funktion zum Speichern der Einstellungen ---
    const settingsForm = document.querySelector('form[data-settings-form]');
    if (settingsForm) {
        settingsForm.addEventListener('change', saveSettings);
        settingsForm.addEventListener('keyup', debounce(saveSettings, 500));
    }

    function saveSettings() {
        const formData = new FormData(settingsForm);
        const settings = {};
        
        // Alle Formularelemente durchgehen
        settingsForm.querySelectorAll('input, textarea, select').forEach(input => {
            const key = input.name;
            if (!key) return;
    
            if (input.type === 'checkbox') {
                if (key.endsWith('[]')) {
              
                     if (!settings[key]) {
                        settings[key] = [];
                    }
                    if (input.checked) {
                        
                        settings[key].push(input.value);
                    }
                } else {
                    settings[key] = input.checked;
                }
            } else {
          
                settings[key] = normalizePath(input.value); // Pfade normalisieren
            }
        });
        const bodyParams = new URLSearchParams();
        for (const key in settings) {
            if (Array.isArray(settings[key])) {
                if (settings[key].length > 0) {
                    settings[key].forEach(val => bodyParams.append(key, val));
                } else {
                    // Wichtig: Sende einen leeren Wert, damit PHP weiß, dass die Checkbox-Gruppe leer ist
                    bodyParams.append(key, '');
                }
            } else {
                bodyParams.append(key, settings[key]);
            }
        }

        if (csrfToken) {
            bodyParams.append('csrf_token', csrfToken);
        }
        
        fetch('index.php?tool=ajax_save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: bodyParams.toString(),
            credentials: 'same-origin'
        }).catch(console.error);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => { clearTimeout(timeout); func(...args); };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // --- Gemeinsame Funktion zum Laden von Verzeichnissen für Picker ---
    async function loadDirectoryForPicker(path, pathElId, contentElId) {
        const pathDisplay = document.getElementById(pathElId);
        const contentDisplay = document.getElementById(contentElId);
        if (!pathDisplay || !contentDisplay) return;

        pathDisplay.textContent = 'Lade...';
        contentDisplay.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>';
        const formData = new FormData();
        formData.append('path', normalizePath(path));
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }

        try {
            const response = await fetch('lib/file_browser_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
            const data = await response.json();

            if (data.status === 'error') {
                showAppToast('error', data.message);
                contentDisplay.innerHTML = `<p class="text-danger p-3">${data.message}</p>`;
                pathDisplay.textContent = 'Fehler!';
                return;
            }

             pathDisplay.textContent = normalizePath(data.path);
             let html = '';
            if (data.parent) {
                html += `<a href="#" class="list-group-item list-group-item-action" data-path="${normalizePath(data.parent)}"><i class="fas fa-arrow-up fa-fw me-2"></i> Übergeordnetes Verzeichnis</a>`;
            }
            data.directories.forEach(dir => {
                html += `<a href="#" class="list-group-item list-group-item-action" data-path="${normalizePath(data.path + '/' + dir)}"><i class="fas fa-folder fa-fw me-2"></i> ${dir}</a>`;
            });
            contentDisplay.innerHTML = html || '<p class="text-muted p-3">Keine Unterverzeichnisse gefunden.</p>';
        } catch (err) {
            showAppToast('error', 'Netzwerkfehler oder ungültige Serverantwort beim Laden des Verzeichnisses. Details: ' + err.message);
            contentDisplay.innerHTML = '<p class="text-danger p-3">Fehler beim Laden des Verzeichnisses. Bitte versuchen Sie es erneut.</p>';
            console.error('Fehler beim Laden des Verzeichnisses:', err);
        }
    }

    // --- Globale Folder Picker Logik ---
    const folderPickerModalEl = document.getElementById('folderPickerModal');
    if (folderPickerModalEl) {
        const folderPickerModal = new bootstrap.Modal(folderPickerModalEl);
        let currentPickerTargetInput = null;
        document.querySelectorAll('.btn-folder-picker').forEach(button => {
            button.addEventListener('click', function () {
                currentPickerTargetInput = document.querySelector(this.dataset.targetInput);
                loadDirectoryForPicker(currentPickerTargetInput.value || '', 'folder-picker-path', 'folder-picker-content');
                folderPickerModal.show();
            });
        });
        document.getElementById('folder-picker-content').addEventListener('click', function (e) {
            e.preventDefault();
            const target = e.target.closest('a');
            if (target && target.dataset.path) loadDirectoryForPicker(target.dataset.path, 'folder-picker-path', 'folder-picker-content');
        });
        document.getElementById('selectFolderBtn').addEventListener('click', function () {
            if (currentPickerTargetInput) {
                currentPickerTargetInput.value = normalizePath(document.getElementById('folder-picker-path').textContent);
                currentPickerTargetInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            folderPickerModal.hide();
        });
    }
    
    // --- Sitemap: In Zwischenablage kopieren ---
    const copySitemapBtn = document.getElementById('copySitemapBtn');
    if (copySitemapBtn) {
        copySitemapBtn.addEventListener('click', function() {
            const sitemapContent = document.getElementById('sitemapOutputContent');
            if (sitemapContent) {
                navigator.clipboard.writeText(sitemapContent.innerText).then(() => {
                    const originalHTML = this.innerHTML;
                  
                     this.innerHTML = '<i class="fas fa-check me-1"></i> Kopiert!';
                    this.classList.add('btn-success'); this.classList.remove('btn-outline-secondary');
                    setTimeout(() => { this.innerHTML = originalHTML; this.classList.remove('btn-success'); this.classList.add('btn-outline-secondary'); }, 2000);
                }).catch(err => {
                    showAppToast('error', 'Kopieren fehlgeschlagen: ' + err);
                });
            }
        });
    }

    // --- Bestätigungsdialoge ---
    document.querySelectorAll('[data-confirm]').forEach(button => {
        button.addEventListener('click', function(e) { 
            e.preventDefault();
            const message = this.dataset.confirm;
            const targetForm = this.closest('form');
            
            showCustomConfirm(message, () => {
       
                 if (targetForm) {
                    const originalButtonName = button.getAttribute('name');
                    const originalButtonValue = button.getAttribute('value');
                    if (originalButtonName) {
                     
                       const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = originalButtonName;
                        hiddenInput.value = originalButtonValue || '1';
              
                         targetForm.appendChild(hiddenInput);
                    }
                    targetForm.submit();
                } else {
                    const href = button.getAttribute('href');
                    if (href) window.location.href = href;
                }
            });
        });
    });
    
    // =================================================================
    // --- File Manager Logik ---
    // =================================================================
    const fileManagerCard = document.getElementById('fileManagerCard');
    if (fileManagerCard) {
        const rootPath = normalizePath(fileManagerCard.dataset.rootPath);
        // --- BUGFIX KORREKTUR: Priorität der Pfad-Ermittlung geändert ---
        // 1. URL-Parameter (?path=...)
        // 2. localStorage (letzter besuchter Pfad)
        // 3. data-start-path Attribut vom Server (sicherer Fallback)
        let currentPath = normalizePath(
            new URLSearchParams(window.location.search).get('path') || 
            localStorage.getItem('fm_last_path') || 
            fileManagerCard.dataset.startPath
 
        );
        
        let selectedItems = new Set();
        let currentSort = { by: 'name', order: 'asc' };
        let currentDirInfo = { file_count: 0, dir_count: 0, total_size: '0 B' };
        const fileListBody = document.getElementById('fm-file-list');
        const breadcrumbNav = document.getElementById('fm-breadcrumb');
        const spinner = document.getElementById('fileManagerSpinner');
        const selectAllCheckbox = document.getElementById('fm-select-all');
        const bulkActionsFooter = document.querySelector('.fm-bulk-actions');
        const selectionInfoSpan = document.getElementById('fm-selection-info');
        const infoBar = document.getElementById('fm-info-bar');
        const refreshBtn = document.getElementById('fm-refresh-btn');
        const createFolderBtn = document.getElementById('fm-create-folder-btn');
        const createFileBtn = document.getElementById('fm-create-file-btn');
        const pathInput = document.getElementById('fm-path-input');
        const pathGoBtn = document.getElementById('fm-path-go-btn');
        const uploadBtn = document.getElementById('fm-upload-btn');
        const sortableHeaders = document.querySelectorAll('.fm-table th.sortable');
        const passPathToSrBtn = document.getElementById('fm-pass-to-sr-btn');
        const passPathToCollectorBtn = document.getElementById('fm-pass-to-collector-btn');
        const passPathToDisposerBtn = document.getElementById('fm-pass-to-disposer-btn');
        const passPathToSitemapBtn = document.getElementById('fm-pass-to-sitemap-btn');
        const renameModal = new bootstrap.Modal(document.getElementById('fm-rename-modal'));
        const zipModal = new bootstrap.Modal(document.getElementById('fm-zip-modal'));
        const moveCopyModal = new bootstrap.Modal(document.getElementById('fm-move-copy-modal'));
        const uploadModal = new bootstrap.Modal(document.getElementById('fm-upload-modal'));
        const chmodModal = new bootstrap.Modal(document.getElementById('fm-chmod-modal'));
        const unzipModal = new bootstrap.Modal(document.getElementById('fm-unzip-modal'));

        let currentDirTimestamp = null;
        let refreshIntervalId = null;
        const REFRESH_INTERVAL_MS = 2000;

        async function getDirectoryTimestamp(path) {
            const formData = new FormData();
            formData.append('action', 'get_dir_timestamp');
            formData.append('path', normalizePath(path));
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }
            try {
                const response = await fetch('lib/file_manager_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
                const data = await response.json();
                if (data.status === 'success') {
                    return data.timestamp;
                }
            } catch (error) {
                console.error('Fehler beim Abrufen des Verzeichnis-Timestamps:', error);
            }
            return null;
        }

        async function autoRefreshFileManager() {
            const newTimestamp = await getDirectoryTimestamp(currentPath);
            if (newTimestamp !== null && newTimestamp !== currentDirTimestamp) {
                currentDirTimestamp = newTimestamp;
                loadFiles(currentPath);
            }
        }

        function startAutoRefresh() {
            if (refreshIntervalId) clearInterval(refreshIntervalId);
            refreshIntervalId = setInterval(autoRefreshFileManager, REFRESH_INTERVAL_MS);
        }

        function stopAutoRefresh() {
            if (refreshIntervalId) {
                clearInterval(refreshIntervalId);
                refreshIntervalId = null;
            }
        }

        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.path) {
                loadFiles(event.state.path);
            }
        });
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tool') === 'filemanager' || !urlParams.has('tool')) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
        
        document.querySelector('.navbar-nav').addEventListener('click', function(e) {
            const link = e.target.closest('.nav-link');
            if (link && new URL(link.href).searchParams.get('tool') !== 'filemanager') {
                stopAutoRefresh();
            }
        });
        async function loadFiles(path, sortBy = currentSort.by, sortOrder = currentSort.order) {
            path = normalizePath(path);
            currentSort = { by: sortBy, order: sortOrder };
            spinner.style.display = 'inline-block';
            fileListBody.innerHTML = '<tr><td colspan="8" class="text-center p-4"><div class="spinner-border" role="status"></div></td></tr>';
            const newUrl = `?tool=filemanager&path=${encodeURIComponent(path)}`;
            if (window.location.search !== newUrl) {
                 history.pushState({path: path}, '', newUrl);
            }

            const url = `lib/file_manager_api.php?action=list&path=${encodeURIComponent(path)}&sort=${sortBy}&order=${sortOrder}`;
            try {
                const response = await fetch(url, { method: 'GET', credentials: 'same-origin' });
                const data = await response.json();
                
                if (data.status === 'success') {
                    currentPath = normalizePath(data.path);
                    localStorage.setItem('fm_last_path', currentPath);
                    pathInput.value = currentPath;
                    currentDirInfo = data.info;
                    currentDirTimestamp = data.dir_modified_timestamp;
                    renderFileList(data.items, data.parent);
                    renderBreadcrumb(data.path, rootPath);
                    updateSelection();
                } else {
                    showAppToast('error', data.message);
                    fileListBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger p-4">${data.message}</td></tr>`;

                    if (data.message.includes('existiert nicht') || data.message.includes('Ungültiger')) {
                        showAppToast('info', 'Ungültiger Pfad. Lade Stammverzeichnis...');
                        localStorage.removeItem('fm_last_path');
                        setTimeout(() => loadFiles(rootPath), 1500);
                    }
                }
            } catch (error) { showAppToast('error', 'Netzwerkfehler beim Laden der Dateiliste.');
            }
            finally { spinner.style.display = 'none';
            }
        }

        function renderFileList(items, parentPath) {
            fileListBody.innerHTML = '';
            if (parentPath) {
                const parentRow = document.createElement('tr');
                parentRow.dataset.path = normalizePath(parentPath);
                parentRow.classList.add('fm-up-level');
                parentRow.innerHTML = `
                    <td></td>
                    <td colspan="7">
                        <a href="#" class="text-decoration-none" data-type="dir">
                          
                           <i class="fas fa-level-up-alt fa-fw me-2 text-secondary"></i>
                            .. (Eine Ebene höher)
                         </a>
                    </td>
                `;
                fileListBody.appendChild(parentRow);
            }

            if (items.length === 0 && !parentPath) {
                fileListBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-4">Dieses Verzeichnis ist leer.</td></tr>';
                return;
            }

            items.forEach(item => {
                const icon = item.is_dir ? 'fa-folder' : 'fa-file-alt';
                let nameHtml;
                if (item.is_dir) {
                    nameHtml = `<a href="#" class="text-decoration-none" data-type="dir"><i class="fas ${icon} fa-fw me-2"></i> ${item.name}</a>`;
                } else if (item.is_editable) {
                    nameHtml = `<a href="?tool=editor&file=${encodeURIComponent(item.path)}" target="_blank" class="text-decoration-none" data-type="file"><i class="fas ${icon} fa-fw me-2"></i> ${item.name}</a>`;
                } else {
                    nameHtml = `<span class="text-body-secondary"><i class="fas ${icon} fa-fw me-2"></i> ${item.name}</span>`;
                }

                const row = document.createElement('tr');
                row.dataset.path = item.path;
                row.dataset.name = item.name;
                row.dataset.isDir = item.is_dir.toString();
           
                     row.dataset.perms = item.perms;
                row.dataset.sizeBytes = item.size_bytes;
                const isZip = !item.is_dir && item.name.endsWith('.zip');
                let actionsHtml = `<div class="btn-group btn-group-sm">`;
                if (item.web_path) {
                    actionsHtml += `<a href="${item.web_path}" target="_blank" class="btn btn-outline-success" title="Im Browser öffnen"><i class="fas fa-eye"></i></a>`;
                }
                if (!item.is_dir) {
                    actionsHtml += `<a href="lib/file_manager_api.php?action=download&path=${encodeURIComponent(item.path)}" class="btn btn-outline-primary" title="Download"><i class="fas fa-download"></i></a>`;
                }
                if (isZip) {
                    actionsHtml += `<button class="btn btn-outline-success" data-action="unzip" title="Entpacken"><i class="fas fa-file-archive"></i></button>`;
                }
                actionsHtml += `<button class="btn btn-outline-secondary" data-action="rename" title="Umbenennen"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-outline-info" data-action="move-copy" title="Kopieren/Verschieben"><i class="fas fa-copy"></i></button>
                                <button class="btn btn-outline-danger" data-action="delete" title="Löschen"><i class="fas fa-trash"></i></button>
                               </div>`;
                row.innerHTML = `
                    <td class="text-center"><input class="form-check-input fm-item-checkbox" type="checkbox" value="${item.path}"></td>
                    <td>${nameHtml}</td>
                    <td>${item.size}</td>
                    <td><a href="#" class="perms-link" data-action="chmod">${item.perms}</a></td>
          
                     <td>${item.owner}</td>
                    <td>${new Date(item.modified * 1000).toLocaleString('de-DE')}</td>
                    <td><small>${item.mime_type}</small></td>
                    <td class="text-end pe-3">${actionsHtml}</td>`;
                fileListBody.appendChild(row);
            });
        }

        function renderBreadcrumb(path, basePath) {
            breadcrumbNav.innerHTML = '';
            const normBasePath = normalizePath(basePath);
            const normPath = normalizePath(path);
            let relativePath = normPath.startsWith(normBasePath) ? normPath.substring(normBasePath.length) : '';
            let parts = relativePath.split('/').filter(p => p);
            let currentBuildPath = normBasePath;

            let homeLink = document.createElement('a');
            homeLink.href = '#';
            homeLink.dataset.path = normBasePath;
            homeLink.innerHTML = '<i class="fas fa-home"></i>';
            breadcrumbNav.appendChild(homeLink);

            parts.forEach((part, index) => {
                currentBuildPath = normalizePath(currentBuildPath + '/' + part);
                
                let separator = document.createElement('span');
                separator.className = 'separator';
                
                separator.innerHTML = '<i class="fas fa-chevron-right"></i>';
                breadcrumbNav.appendChild(separator);

                if (index === parts.length - 1) {
                    let activeSpan = document.createElement('span');
                    activeSpan.className = 'active';
             
                       activeSpan.textContent = part;
                    breadcrumbNav.appendChild(activeSpan);
                } else {
                    let partLink = document.createElement('a');
                    partLink.href = '#';
        
                     partLink.dataset.path = currentBuildPath;
                    partLink.textContent = part;
                    breadcrumbNav.appendChild(partLink);
                }
            });
        }

        function updateSelection() {
            selectedItems.clear();
            const checkedRows = [];
            fileListBody.querySelectorAll('.fm-item-checkbox:checked').forEach(cb => {
                selectedItems.add(cb.value);
                checkedRows.push(cb.closest('tr'));
            });
            if (selectedItems.size > 0) {
                let selectedSize = 0;
                let selectedFileCount = 0;
                let selectedDirCount = 0;
                checkedRows.forEach(row => {
                    if (row.dataset.isDir === 'true') {
                        selectedDirCount++;
                    } else {
                    
                         selectedFileCount++;
                        selectedSize += parseInt(row.dataset.sizeBytes, 10);
                    }
                });
                const selectionText = `${selectedItems.size} Element(e) ausgewählt (${formatBytes(selectedSize)})`;
                selectionInfoSpan.textContent = selectionText;
                infoBar.innerHTML = `
                    <span class="badge bg-primary-subtle text-primary-emphasis fw-normal"><i class="fas fa-check-double fa-fw me-1"></i> ${selectedItems.size} Ausgewählt</span>
                    <span class="badge bg-secondary-subtle text-body-secondary fw-normal"><i class="fas fa-file-alt fa-fw me-1"></i> ${selectedFileCount} Datei(en)</span>
                    <span class="badge bg-secondary-subtle text-body-secondary fw-normal"><i class="fas fa-folder fa-fw me-1"></i> ${selectedDirCount} Ordner</span>
        
                     <span class="badge bg-secondary-subtle text-body-secondary fw-normal"><i class="fas fa-hdd fa-fw me-1"></i> ${formatBytes(selectedSize)}</span>
                `;
            } else {
                infoBar.innerHTML = `
                    <span class="badge bg-secondary-subtle text-body-secondary fw-normal"><i class="fas fa-file-alt fa-fw me-1"></i> ${currentDirInfo.file_count} Datei(en)</span>
                    <span class="badge bg-secondary-subtle text-body-secondary fw-normal"><i class="fas fa-folder fa-fw me-1"></i> ${currentDirInfo.dir_count} Ordner</span>
                    
                    <span class="badge bg-secondary-subtle text-body-secondary fw-normal"><i class="fas fa-hdd fa-fw me-1"></i> ${currentDirInfo.total_size}</span>
                `;
            }
            
            bulkActionsFooter.style.display = selectedItems.size > 0 ? 'table-footer-group' : 'none';
            const allCheckboxes = fileListBody.querySelectorAll('.fm-item-checkbox');
            selectAllCheckbox.checked = allCheckboxes.length > 0 && selectedItems.size === allCheckboxes.length;
            selectAllCheckbox.indeterminate = selectedItems.size > 0 && selectedItems.size < allCheckboxes.length;
            adjustBodyPadding();
        }

        function updateSortIndicators() {
            sortableHeaders.forEach(header => {
                const icon = header.querySelector('i');
                if (header.dataset.sort === currentSort.by) icon.className = currentSort.order === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
                else icon.className = 'fas fa-sort';
       
             });
            updateSortIndicators();
        }

        refreshBtn.addEventListener('click', () => loadFiles(currentPath));
        createFolderBtn.addEventListener('click', async () => { 
            const name = await window.prompt('Name für den neuen Ordner:'); 
            if (name) performApiCall({ action: 'create', type: 'folder', path: currentPath, name: name }); 
        });
        createFileBtn.addEventListener('click', async () => { 
            const name = await window.prompt('Name für die neue Datei:'); 
            if (name) performApiCall({ action: 'create', type: 'file', path: currentPath, name: name }); 
        });
        pathGoBtn.addEventListener('click', () => loadFiles(pathInput.value));
        pathInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') loadFiles(pathInput.value); });
        breadcrumbNav.addEventListener('click', e => {
            e.preventDefault();
            const link = e.target.closest('a');
            if (link && link.dataset.path) {
                loadFiles(link.dataset.path);
            }
        });
        uploadBtn.addEventListener('click', () => {
            document.getElementById('fm-upload-target-dir').textContent = currentPath;
            document.getElementById('fm-upload-files-input').value = '';
            uploadModal.show();
        });
        document.getElementById('fm-upload-confirm-btn').addEventListener('click', async function() {
            const files = document.getElementById('fm-upload-files-input').files;
            if (files.length === 0) { showAppToast('info', 'Bitte wählen Sie zuerst Dateien aus.'); return; }
            const formData = new FormData();
            formData.append('action', 'upload');
            formData.append('path', currentPath);
            for (const file of files) { formData.append('files[]', file); }
            if (csrfToken) { formData.append('csrf_token', csrfToken); }
            uploadModal.hide();
            this.blur();
            await performApiCall(formData);
        });
        fileListBody.addEventListener('click', e => {
            const row = e.target.closest('tr');
            if (!row) return;

            if (!e.target.closest('a, button, input')) {
                const checkbox = row.querySelector('.fm-item-checkbox');
                if (checkbox) {
               
                     checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
            
            const link = e.target.closest('a');
            if (link && link.dataset.type === 'dir') {
                e.preventDefault();
                loadFiles(row.dataset.path);
            }
            
            const actionBtn = e.target.closest('button[data-action], a[data-action]');
            if (actionBtn) {
         
               e.preventDefault();
                handleAction(actionBtn.dataset.action, [row.dataset.path], row.dataset.perms);
            }
        });
        selectAllCheckbox.addEventListener('change', () => { fileListBody.querySelectorAll('.fm-item-checkbox').forEach(cb => cb.checked = selectAllCheckbox.checked); updateSelection(); });
        fileListBody.addEventListener('change', e => { if (e.target.classList.contains('fm-item-checkbox')) updateSelection(); });
        bulkActionsFooter.addEventListener('click', e => {
            const actionBtn = e.target.closest('button[data-action]');
            if (actionBtn) {
                const action = actionBtn.dataset.action;
                if (action === 'deselect-all') { selectAllCheckbox.checked = false; fileListBody.querySelectorAll('.fm-item-checkbox').forEach(cb => cb.checked = false); }
                else if (action === 'invert-selection') { fileListBody.querySelectorAll('.fm-item-checkbox').forEach(cb => cb.checked = !cb.checked); }
                updateSelection();
                handleAction(action, Array.from(selectedItems));
            }
        });
        sortableHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const sortBy = header.dataset.sort;
                const sortOrder = (currentSort.by === sortBy && currentSort.order === 'asc') ? 'desc' : 'asc';
                loadFiles(currentPath, sortBy, sortOrder); 
            });
    
            });

        if (passPathToSrBtn) {
            passPathToSrBtn.addEventListener('click', () => {
                window.location.href = `?tool=search_replace&sr_startDir=${encodeURIComponent(currentPath)}`;
            });
        }
        if (passPathToCollectorBtn) {
            passPathToCollectorBtn.addEventListener('click', () => {
                window.location.href = `?tool=collector&collector_startDir=${encodeURIComponent(currentPath)}`;
            });
        }
        if (passPathToDisposerBtn) {
            passPathToDisposerBtn.addEventListener('click', () => {
                window.location.href = `?tool=disposer&disposer_targetDir=${encodeURIComponent(currentPath)}`;
            });
        }
        if (passPathToSitemapBtn) {
            passPathToSitemapBtn.addEventListener('click', () => {
                window.location.href = `?tool=sitemap&sitemap_targetDirectory=${encodeURIComponent(currentPath)}`;
            });
        }

        function handleAction(action, items, itemData = '') {
            const itemName = items.length === 1 ? items[0].split(/[\\/]/).pop() : '';
            if (items.length === 0 && !['deselect-all', 'invert-selection'].includes(action)) { showAppToast('info', 'Bitte wählen Sie zuerst ein oder mehrere Elemente aus.');
                return; }
            switch(action) {
                case 'delete': 
                    showCustomConfirm(`Sollen die ausgewählten ${items.length} Elemente wirklich gelöscht werden?`, () => {
                        performApiCall({ action: 'delete', path: currentPath, items: items });
       
                     });
                    break;
                case 'rename':
                    document.getElementById('fm-new-name-input').value = itemName;
                    document.getElementById('fm-rename-confirm-btn').onclick = () => { renameModal.hide(); performApiCall({ action: 'rename', item: items[0], newName: document.getElementById('fm-new-name-input').value }); };
                    renameModal.show(); break;
                case 'zip':
                    document.getElementById('fm-zip-name-input').value = '';
                    document.getElementById('fm-zip-confirm-btn').onclick = () => { zipModal.hide(); performApiCall({ action: 'zip', path: currentPath, items: items, zipName: document.getElementById('fm-zip-name-input').value }); };
                    zipModal.show(); break;
                case 'unzip':
                    document.getElementById('fm-unzip-item-name').textContent = itemName;
                    document.getElementById('fm-unzip-here-btn').onclick = () => { unzipModal.hide(); performApiCall({ action: 'unzip', item: items[0], path: currentPath, extractToFolder: '0' }); };
                    document.getElementById('fm-unzip-to-folder-btn').onclick = () => { unzipModal.hide(); performApiCall({ action: 'unzip', item: items[0], path: currentPath, extractToFolder: '1' }); };
                    unzipModal.show(); break;
                case 'move-copy':
                    loadDirectoryForPicker(currentPath, 'fm-move-copy-picker-path', 'fm-move-copy-picker-content');
                    document.getElementById('fm-copy-confirm-btn').onclick = () => { moveCopyModal.hide(); performApiCall({ action: 'move_copy', type: 'copy', items: items, destination: document.getElementById('fm-move-copy-picker-path').textContent }); };
                    document.getElementById('fm-move-confirm-btn').onclick = () => { moveCopyModal.hide(); performApiCall({ action: 'move_copy', type: 'move', items: items, destination: document.getElementById('fm-move-copy-picker-path').textContent }); };
                    moveCopyModal.show(); break;
                case 'chmod':
                    const modalEl = document.getElementById('fm-chmod-modal');
                    modalEl.querySelector('#fm-chmod-item-name').textContent = itemName;
                    const checkboxes = modalEl.querySelectorAll('[data-chmod-val]');
                    let currentPerms = parseInt(itemData, 8);
                    checkboxes.forEach(cb => { cb.checked = (currentPerms & parseInt(cb.dataset.chmodVal)) !== 0; });
                    const updateNumericVal = () => {
                        let total = 0;
                        modalEl.querySelectorAll('[data-chmod-val]:checked').forEach(cb => total += parseInt(cb.dataset.chmodVal));
                        modalEl.querySelector('#fm-chmod-numeric-val').textContent = total.toString(8).padStart(3, '0');
                    };
                    checkboxes.forEach(cb => cb.onchange = updateNumericVal); updateNumericVal();
                    document.getElementById('fm-chmod-confirm-btn').onclick = () => { chmodModal.hide(); performApiCall({ action: 'chmod', item: items[0], perms: modalEl.querySelector('#fm-chmod-numeric-val').textContent }); };
                    chmodModal.show(); break;
            }
        }
        
        async function performApiCall(params) {
            spinner.style.display = 'inline-block';
            let body;
            if (params instanceof FormData) {
                body = params;
            } else {
                body = new FormData();
                for (const key in params) {
                    if (Array.isArray(params[key])) {
                        params[key].forEach(val => body.append(key + '[]', val));
                    } else {
                        body.append(key, params[key]);
                    }
                }
            }
            if (csrfToken) {
                if (body instanceof FormData) {
                    body.append('csrf_token', csrfToken);
                }
            }

            try {
                const response = await fetch('lib/file_manager_api.php', { method: 'POST', body: body, credentials: 'same-origin' });
                const data = await response.json();
                showAppToast(data.status, data.message);
                if (data.status === 'success') {
                    selectedItems.clear();
                    loadFiles(currentPath);
                }
            } catch (error) { showAppToast('error', 'Ein unerwarteter Fehler ist aufgetreten.');
                console.error(error); }
            finally { spinner.style.display = 'none';
            }
        }
        
        document.getElementById('fm-move-copy-picker-content').addEventListener('click', function (e) {
            e.preventDefault(); const target = e.target.closest('a');
            if (target && target.dataset.path) loadDirectoryForPicker(target.dataset.path, 'fm-move-copy-picker-path', 'fm-move-copy-picker-content');
        });
        function formatBytes(bytes, decimals = 2) {
            if (bytes <= 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        loadFiles(currentPath).then(() => {
            updateSortIndicators();
            adjustBodyPadding();
        });
        const body = document.body;
        function adjustBodyPadding() {
            if (bulkActionsFooter && bulkActionsFooter.style.display !== 'none') {
                const footerHeight = bulkActionsFooter.offsetHeight;
                body.style.paddingBottom = footerHeight + 'px';
                body.classList.add('has-fixed-footer');
            } else {
                body.style.paddingBottom = '0';
                body.classList.remove('has-fixed-footer');
            }
        }

        const observer = new MutationObserver(adjustBodyPadding);
        if (bulkActionsFooter) {
            observer.observe(bulkActionsFooter, { attributes: true, attributeFilter: ['style'] });
        }
        window.addEventListener('resize', adjustBodyPadding);
    }

    // --- Custom Confirm Modal (Ersetzt window.confirm) ---
    function showCustomConfirm(message, callback) {
        const confirmModalEl = document.getElementById('customConfirmModal');
        if (!confirmModalEl) {
            console.error('Custom confirm modal element not found!');
            return;
        }
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        document.getElementById('customConfirmModalBody').textContent = message;
        const confirmActionBtn = document.getElementById('customConfirmActionBtn');
        const newConfirmActionBtn = confirmActionBtn.cloneNode(true);
        confirmActionBtn.parentNode.replaceChild(newConfirmActionBtn, confirmActionBtn);
        newConfirmActionBtn.addEventListener('click', function handler() {
            confirmModal.hide();
            if (typeof callback === 'function') {
                callback();
            }
            newConfirmActionBtn.removeEventListener('click', handler);
        });
        confirmModal.show();
    }

    // --- Custom Alert/Prompt Modal (Ersetzt alert()/prompt()) ---
    async function showCustomPrompt(message, defaultValue = '') {
        return new Promise(resolve => {
            const promptModalEl = document.getElementById('customPromptModal');
            if (!promptModalEl) {
                console.error('Custom prompt modal element not found!');
                
                return resolve(null);
            }
            const promptModal = new bootstrap.Modal(promptModalEl);
            document.getElementById('customPromptModalBody').textContent = message;
            const promptInput = document.getElementById('customPromptInput');
            promptInput.value = defaultValue;
            
            const promptConfirmBtn = document.getElementById('customPromptConfirmBtn');
 
                       const newPromptConfirmBtn = promptConfirmBtn.cloneNode(true);
            promptConfirmBtn.parentNode.replaceChild(newPromptConfirmBtn, promptConfirmBtn);
            
            const cancelBtn = promptModalEl.querySelector('[data-bs-dismiss="modal"]');
            const confirmHandler = () => {
                promptModal.hide();
                resolve(promptInput.value);
                cleanup();
            };

            const cancelHandler = () => {
                promptModal.hide();
                resolve(null);
                cleanup();
            };
            
            const keypressHandler = (e) => {
                if (e.key === 'Enter') {
                    confirmHandler();
                }
            };
            function cleanup() {
                newPromptConfirmBtn.removeEventListener('click', confirmHandler);
                cancelBtn.removeEventListener('click', cancelHandler);
                promptInput.removeEventListener('keypress', keypressHandler);
            }

            newPromptConfirmBtn.addEventListener('click', confirmHandler, { once: true });
            cancelBtn.addEventListener('click', cancelHandler, { once: true });
            promptInput.addEventListener('keypress', keypressHandler);

            promptModalEl.addEventListener('shown.bs.modal', () => promptInput.focus(), { once: true });
            promptModal.show();
        });
    }

    window.prompt = showCustomPrompt;

    // --- Vergit Tool Logic (Releases, Instances, Archiving, Diffs) ---
    const vergitCard = document.querySelector('a[href="?tool=vergit"]');
    if (vergitCard || window.location.search.includes('tool=vergit')) {
        
        // Modal-Logik für Release
        const releaseModalEl = document.getElementById('releaseModal');
        if (releaseModalEl) {
            releaseModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const versionId = button.getAttribute('data-version-id');
                const versionNumber = button.getAttribute('data-version-number');
                releaseModalEl.querySelector('#modal_version_id').value = versionId;
                releaseModalEl.querySelector('#release_name').value = versionNumber + '-final';
            });
        }

        // Logik für Kopieren-Button der "Latest URL"
        const copyUrlBtn = document.getElementById('copy-latest-url-btn');
        const urlInput = document.getElementById('latest-version-url');
        if(copyUrlBtn && urlInput) {
            copyUrlBtn.addEventListener('click', function() {
                if(this.disabled) return;
                navigator.clipboard.writeText(urlInput.value).then(() => {
                    showAppToast('success', 'Link in die Zwischenablage kopiert!');
                }).catch(err => showAppToast('error', 'Kopieren fehlgeschlagen: ' + err));
            });
        }

        // Logik für Projekt-Archivierung
        document.body.addEventListener('click', function(e) {
            const archiveBtn = e.target.closest('.btn-archive-project');
            if (archiveBtn) {
                e.preventDefault();
                const projectId = archiveBtn.dataset.projectId;
                const projectName = archiveBtn.dataset.projectName;
                
                showCustomConfirm(`Möchtest du das Projekt '${projectName}' wirklich archivieren? Das Projekt wird als ZIP gesichert und aus der aktiven Liste entfernt.`, () => {
                    archiveBtn.disabled = true;
                    archiveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = '?tool=vergit';
                    form.innerHTML = `
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="action" value="delete_project">
                        <input type="hidden" name="project_id" value="${projectId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                });
            }
        });

        // Logik für Instanzen-Modal
        const instanceModalEl = document.getElementById('instanceModal');
        if (instanceModalEl) {
            let currentVersionIdForInstances = null;
            const instanceModal = new bootstrap.Modal(instanceModalEl);
            const versionNumberSpan = document.getElementById('instance-version-number');
            const listContainer = document.getElementById('instance-list-container');
            const createBtn = document.getElementById('create-instance-btn');

            instanceModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                currentVersionIdForInstances = button.getAttribute('data-version-id');
                versionNumberSpan.textContent = button.getAttribute('data-version-number');
                loadInstances(currentVersionIdForInstances);
            });
    
            createBtn.addEventListener('click', async function() {
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Erstelle...';
                const formData = new FormData();
                formData.append('action', 'create_instance');
                formData.append('version_id', currentVersionIdForInstances);
                formData.append('csrf_token', csrfToken);
                try {
                    const response = await fetch('lib/vergit_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
                    const result = await response.json();
                    if (result.status === 'success') {
                        showAppToast('success', result.message);
                        loadInstances(currentVersionIdForInstances);
                    } else {
                        showAppToast('error', result.message || 'Ein Fehler ist aufgetreten.');
                    }
                } catch (error) {
                    showAppToast('error', 'Netzwerkfehler: ' + error.message);
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-plus me-2"></i>Neue Instanz erstellen';
                }
            });
    
            listContainer.addEventListener('click', async function(e) {
                const deleteButton = e.target.closest('.delete-instance-btn');
                if (!deleteButton) return;
                e.preventDefault();
                const instanceId = deleteButton.dataset.instanceId;
                showCustomConfirm(`Soll die Instanz '${instanceId}' wirklich gelöscht werden?`, async () => {
                    const formData = new FormData();
                    formData.append('action', 'delete_instance');
                    formData.append('instance_id', instanceId);
                    formData.append('csrf_token', csrfToken);
                    try {
                        const response = await fetch('lib/vergit_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
                        const result = await response.json();
                        if (result.status === 'success') {
                            showAppToast('success', result.message);
                            loadInstances(currentVersionIdForInstances);
                        } else {
                            showAppToast('error', result.message || 'Ein Fehler ist aufgetreten.');
                        }
                    } catch (error) {
                        showAppToast('error', 'Netzwerkfehler: ' + error.message);
                    }
                });
            });
    
            async function loadInstances(versionId) {
                listContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>';
                const formData = new FormData();
                formData.append('action', 'get_instances');
                formData.append('version_id', versionId);
                formData.append('csrf_token', csrfToken);
                try {
                    const response = await fetch('lib/vergit_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
                    const result = await response.json();
                    if (result.status === 'success') {
                        let html = '';
                        if (result.instances.length > 0) {
                            html = '<ul class="list-group">';
                            result.instances.forEach(inst => {
                                const urlHtml = inst.url ? `<a href="${inst.url}" target="_blank" class="text-success text-break">${inst.url} <i class="fas fa-external-link-alt fa-xs"></i></a>` : '<span class="text-muted">Nicht im Web-Root</span>';
                                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="font-monospace">${inst.id}</strong>
                                        <div class="small text-muted">Erstellt: ${inst.created_at}</div>
                                        <div class="small">${urlHtml}</div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger delete-instance-btn" data-instance-id="${inst.id}" title="Instanz löschen"><i class="fas fa-trash"></i></button>
                                </li>`;
                            });
                            html += '</ul>';
                        } else {
                            html = '<div class="alert alert-light text-center border">Für diese Version existieren noch keine Instanzen.</div>';
                        }
                        listContainer.innerHTML = html;
                    } else {
                        listContainer.innerHTML = `<div class="alert alert-danger">${result.message || 'Fehler beim Laden.'}</div>`;
                    }
                } catch (error) {
                    listContainer.innerHTML = `<div class="alert alert-danger">Netzwerkfehler: ${error.message}</div>`;
                }
            }
        }

        // Logik zum Laden und Anzeigen von Diffs
        const diffButtons = document.querySelectorAll('.btn-show-diff');
        diffButtons.forEach(button => {
            button.addEventListener('click', async function() {
                const targetId = this.dataset.bsTarget;
                const container = document.querySelector(targetId + ' .diff-container-wrapper');
                
                if (container.dataset.loaded) return;

                container.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted small">Suche nach Änderungen...</p></div>';
                
                const versionPath = this.dataset.versionPath;
                const formData = new FormData();
                formData.append('path', versionPath);
                formData.append('csrf_token', csrfToken);
                
                try {
                    const response = await fetch('lib/diff_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
                    const result = await response.json();

                    if (result.status === 'success') {
                        if (result.diffs.length > 0) {
                            let accordionHtml = '<div class="accordion" id="accordion-diff-' + targetId.substring(1) + '">';
                            result.diffs.forEach((diff, index) => {
                                const collapseId = 'collapse-diff-' + targetId.substring(1) + '-' + index;
                                accordionHtml += `
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false">
                                                <i class="fas fa-file-code me-2"></i> ${diff.file}
                                            </button>
                                        </h2>
                                        <div id="${collapseId}" class="accordion-collapse collapse" data-bs-parent="#accordion-diff-${targetId.substring(1)}">
                                            <div class="accordion-body p-0">${diff.html}</div>
                                        </div>
                                    </div>
                                `;
                            });
                            accordionHtml += '</div>';
                            container.innerHTML = accordionHtml;
                        } else {
                            container.innerHTML = '<p class="text-center text-muted p-3">Keine `.srbkup`-Dateien für einen Vergleich gefunden.</p>';
                        }
                    } else {
                        container.innerHTML = `<div class="alert alert-danger mb-0">${result.message || 'Fehler beim Laden der Diffs.'}</div>`;
                    }
                } catch (error) {
                    container.innerHTML = `<div class="alert alert-danger mb-0">Netzwerkfehler: ${error.message}</div>`;
                } finally {
                    container.dataset.loaded = 'true';
                }
            });
        });
    }
});