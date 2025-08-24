<?php
// helpers.php - Zentrale Hilfsfunktionen

// Lade die zentrale Pfad-Konfiguration
require_once __DIR__ . '/config/config.php';
define('SETTINGS_FILE', __DIR__ . '/config/settings.json');
define('LOCKOUT_DIR', __DIR__ . '/data/lockouts/');
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_TIME', 5 * 60); // 5 Minuten in Sekunden

/**
 * Erstellt das Verzeichnis für Lockout-Dateien, falls es nicht existiert.
 */
function ensure_lockout_dir_exists()
{
    if (!is_dir(LOCKOUT_DIR)) {
        mkdir(LOCKOUT_DIR, 0755, true);
    }
}

/**
 * Bereinigt abgelaufene Lockout-Dateien.
 */
function cleanup_expired_lockouts()
{
    ensure_lockout_dir_exists();
    if ($handle = opendir(LOCKOUT_DIR)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != '.' && $entry != '..') {
                $path = LOCKOUT_DIR . $entry;
                if (filemtime($path) < time() - LOCKOUT_TIME) {
                    @unlink($path);
                }
            }
        }
        closedir($handle);
    }
}

/**
 * Überprüft, ob ein Benutzer gesperrt ist.
 * @param string $username
 * @return bool
 */
function is_user_locked_out(string $username): bool
{
    ensure_lockout_dir_exists();
    $lockFile = LOCKOUT_DIR . 'user_' . sha1(strtolower($username)) . '.lock';
    if (file_exists($lockFile)) {
        // Überprüfen, ob das Lock noch gültig ist
        if (filemtime($lockFile) > time() - LOCKOUT_TIME) {
            return true;
        }
        // Abgelaufenes Lock entfernen, falls cleanup nicht gelaufen ist
        @unlink($lockFile);
    }
    return false;
}

/**
 * Vermerkt einen fehlgeschlagenen Login-Versuch und sperrt den Benutzer bei Bedarf.
 * Die Zählung erfolgt nun im Dateisystem, um sie persistent zu machen.
 * @param string $username
 */
function handle_failed_login(string $username)
{
    ensure_lockout_dir_exists();
    $username_key = strtolower($username);
    $counterFile = LOCKOUT_DIR . 'attempt_' . sha1($username_key) . '.count';

    // c+ öffnet zum Lesen/Schreiben, erstellt die Datei, wenn sie nicht existiert.
    $fp = fopen($counterFile, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        if ($fp) fclose($fp);
        return; // Vorgang sicher abbrechen
    }

    $attempts = (int) stream_get_contents($fp);
    $attempts++;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockFile = LOCKOUT_DIR . 'user_' . sha1($username_key) . '.lock';
        @touch($lockFile);
        // Zählerdatei nach der Sperrung entfernen
        flock($fp, LOCK_UN);
        fclose($fp);
        @unlink($counterFile);
    } else {
        // Neuen Zählerstand schreiben
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) $attempts);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Setzt die Zählung der fehlgeschlagenen Login-Versuche für einen Benutzer zurück.
 * Löscht die Zählerdatei aus dem Dateisystem.
 * @param string $username
 */
function clear_failed_login_attempts(string $username)
{
    ensure_lockout_dir_exists();
    $counterFile = LOCKOUT_DIR . 'attempt_' . sha1(strtolower($username)) . '.count';
    if (file_exists($counterFile)) {
        @unlink($counterFile);
    }
}

/**
 * Gibt die Standardeinstellungen für alle Tools zurück.
 * @return array
 */
function getDefaultSettings(): array
{
    global $ROOT_PATH; // Nutze die globale Variable aus config.php
    return [
        // Collector
        'collector_startDir' => $ROOT_PATH,
        'collector_fileTypes' => 'php,html,js,json,css,md,txt',
        'collector_includeSubdirs' => true,
        // Disposer
        'disposer_targetDir' => $ROOT_PATH,
        // Search & Replace
        'sr_startDir' => $ROOT_PATH,
        'sr_searchString' => '',
        'sr_replaceString' => '',
        'sr_includeSubdirs' => true,
        'sr_backupFiles' => true,
        'sr_fileTypes' => 'php,html,js,css,txt,md',
        // Sitemap
        'sitemap_targetDirectory' => $ROOT_PATH,
        // Vergit
        'vergit_storage_path' => realpath(__DIR__ . '/../data/vergit_projects') ?: __DIR__ . '/../data/vergit_projects',
        // LLM Integration
        'gemini_api_key' => '',
        'kimi_api_key' => '',
        'llm_provider' => 'gemini',
        'llm_base_instructions' => "Du bist ein Experte für Webentwicklung und ein hilfreicher Programmier-Assistent. Deine Aufgabe ist es, Quellcode basierend auf den Anweisungen des Benutzers zu modifizieren, zu refaktorisieren oder zu erstellen.

**WICHTIGE AUSGABEREGELN:**
1.  **Struktur:** Deine Antwort MUSS IMMER spezielle Trennzeichen verwenden, um Code und Erklärung voneinander zu trennen.
2.  **Code-Block:** Wenn du Code ausgibst, MUSS dieser Block mit einer Zeile `---LLM_CODE_BEGIN---` beginnen und mit einer Zeile `---LLM_CODE_END---` enden.
3.  **Erklärungs-Block:** Wenn du eine Erklärung ausgibst, MUSS dieser Block mit einer Zeile `---LLM_EXPL_BEGIN---` beginnen und mit einer Zeile `---LLM_EXPL_END---` enden.
4.  **Kombinierte Antwort:** Wenn der Benutzer sowohl Code als auch eine Erklärung anfordert (Standardfall), gib zuerst den vollständigen Code-Block und danach den vollständigen Erklärungs-Block aus.
5.  **Nur Erklärung:** Wenn der Benutzer explizit nur eine Erklärung wünscht, gib NUR den Erklärungs-Block aus.
6.  **Nur Code:** Wenn der Benutzer explizit nur Code wünscht, gib NUR den Code-Block aus.
7.  **Quellcode-Inhalt:** Der Quellcode MUSS immer vollständig und roh (ohne Markdown-Formatierung wie ```) ausgegeben werden. Gib immer den gesamten modifizierten Code-Block zurück, auch wenn nur eine Zeile geändert wurde.
8.  **Erklärungs-Inhalt:** Halte Erklärungen kurz, prägnant und auf Deutsch. Vermeide Füllwörter und Begrüßungen.",
    ];
}

/**
 * Lädt die Einstellungen aus der JSON-Datei.
 * Erstellt die Datei mit Standardwerten, falls sie nicht existiert.
 * @return array
 */
function loadSettings(): array
{
    $defaults = getDefaultSettings();
    if (!file_exists(SETTINGS_FILE)) {
        if (is_writable(dirname(SETTINGS_FILE))) {
            file_put_contents(SETTINGS_FILE, json_encode($defaults, JSON_PRETTY_PRINT));
            return $defaults;
        }
        return $defaults;
    }
    $jsonString = file_get_contents(SETTINGS_FILE);
    $data = json_decode($jsonString, true);
    // Führt Standardwerte mit gespeicherten Werten zusammen, um neue Optionen abzudecken
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

/**
 * Speichert ein Array von Einstellungen in die JSON-Datei.
 * @param array $newSettings
 * @return bool
 */
function saveSettings(array $newSettings): bool
{
    if (!is_writable(dirname(SETTINGS_FILE)) || (file_exists(SETTINGS_FILE) && !is_writable(SETTINGS_FILE))) {
        return false;
    }
    $currentSettings = loadSettings();
    $updatedSettings = array_merge($currentSettings, $newSettings);
    return file_put_contents(SETTINGS_FILE, json_encode($updatedSettings, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Fügt eine formatierte Nachricht zum Log-Array hinzu.
 * @param array &$logArray Das Log-Array (wird per Referenz modifiziert).
 * @param string $message Die Log-Nachricht.
 * @param string $type Typ der Nachricht ('info', 'success', 'error', 'warning', 'special').
 * @param mixed|null $data Zusätzliche Daten, z.B. ein Dateipfad.
 */
function logMsg(array &$logArray, string $message, string $type = 'info', $data = null)
{
    $logArray[] = [
        'time' => date('H:i:s'),
        'message' => $message,
        'type' => $type,
        'data' => $data,
    ];
}

/**
 * Rendert das Log-Array als HTML.
 * @param array $log
 * @param callable|null $formatter Eine optionale Funktion zur benutzerdefinierten Formatierung einer Zeile.
 */
function renderLog(array $log, callable $formatter = null)
{
    if (empty($log)) {
        echo '<p class="text-muted small">Noch keine Ausgabe vorhanden.</p>';
        return;
    }
    echo '<div class="log-entries">';
    foreach ($log as $entry) {
        if ($formatter) {
            echo $formatter($entry);
        } else {
            // Standard-Formatter
            $icon = '';
            $textClass = '';
            switch ($entry['type']) {
                case 'success':
                    $icon = '<i class="fas fa-check-circle text-success fa-fw"></i>';
                    $textClass = 'text-success';
                    break;
                case 'error':
                    $icon = '<i class="fas fa-times-circle text-danger fa-fw"></i>';
                    $textClass = 'text-danger';
                    break;
                case 'warning':
                    $icon = '<i class="fas fa-exclamation-triangle text-warning fa-fw"></i>';
                    break;
                case 'info':
                    $icon = '<i class="fas fa-info-circle text-info fa-fw"></i>';
                    break;
                case 'special':
                    $icon = '<i class="fas fa-star text-primary fa-fw"></i>';
                    break;
            }
            $time = '<code class="text-muted">[' . htmlspecialchars($entry['time']) . ']</code>';
            $message = htmlspecialchars($entry['message']);
            echo "<p class=\"mb-1\">{$time} {$icon} <span class=\"{$textClass}\">{$message}</span></p>";
        }
    }
    echo '</div>';
}

/**
 * Erzeugt einen CSRF-Token, wenn noch keiner in der Session existiert.
 * @return string Der CSRF-Token.
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Überprüft den übergebenen CSRF-Token gegen den in der Session.
 * Bricht das Skript bei einem Fehler ab.
 */
function validate_csrf_token()
{
    $token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? null);
    if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        if (
            strpos($_SERVER['REQUEST_URI'], '/api/') !== false ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        ) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Ungültige Anfrage. Bitte laden Sie die Seite neu. (CSRF-Token-Fehler)',
            ]);
        } else {
            die('Ungültige Anfrage. Bitte laden Sie die Seite neu. (CSRF-Token-Fehler)');
        }
        exit();
    }
}
