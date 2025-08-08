<?php
/**
 * vergit.class.php
 * Kernlogik für das Ver-Git Versionskontroll-Modul.
 */

class Vergit
{
    private $config;
    private $dbPath;
    private $db;
    public function __construct()
    {
        $this->loadConfig();
        $this->loadDatabase();
    }

    private function loadConfig()
    {
        // Standardkonfiguration
        $this->config = [
            'storage_path' => realpath(__DIR__ . '/data/vergit_projects'),
            'archive_path' => realpath(__DIR__ . '/../data/vergit_archives')
        ];
        // Lade benutzerspezifische Einstellungen, falls vorhanden
        $settingsFile = __DIR__ . '/../config/settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            if (isset($settings['vergit_storage_path']) && !empty($settings['vergit_storage_path'])) {
                $this->config['storage_path'] = $settings['vergit_storage_path'];
            }
        }

        // Sicherstellen, dass die Verzeichnisse existieren
        if (!is_dir($this->config['storage_path'])) {
            mkdir($this->config['storage_path'], 0775, true);
        }
        if (!is_dir($this->config['archive_path'])) {
            mkdir($this->config['archive_path'], 0775, true);
        }
    }

    private function loadDatabase()
    {
        $this->dbPath = __DIR__ . '/../config/vergit_db.json';
        if (!file_exists($this->dbPath)) {
            $this->db = ['projects' => [], 'versions' => [], 'instances' => [], 'archives' => []];
            $this->saveDatabase();
        } else {
            $this->db = json_decode(file_get_contents($this->dbPath), true);
            // Sicherstellen, dass alle Schlüssel für ältere DBs existieren
            if (!isset($this->db['instances'])) $this->db['instances'] = [];
            if (!isset($this->db['archives'])) $this->db['archives'] = [];
        }
    }

    private function saveDatabase()
    {
        file_put_contents($this->dbPath, json_encode($this->db, JSON_PRETTY_PRINT));
    }

    public function getProjects()
    {
        $projects = $this->db['projects'] ?? [];
        uasort($projects, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $projects;
    }

    public function createProject($name)
    {
        if (empty(trim($name))) {
            throw new Exception("Projektname darf nicht leer sein.");
        }

        foreach ($this->db['projects'] as $project) {
            if (strtolower($project['name']) === strtolower($name)) {
                throw new Exception("Ein Projekt mit diesem Namen existiert bereits.");
            }
        }

        $id = uniqid('proj_');
        $projectPath = $this->config['storage_path'] . '/' . $id;

        if (!mkdir($projectPath, 0775, true)) {
            throw new Exception("Projektverzeichnis konnte nicht erstellt werden. Prüfen Sie die Berechtigungen.");
        }

        mkdir($projectPath . '/master', 0775, true);
        mkdir($projectPath . '/latest', 0775, true);
        mkdir($projectPath . '/release', 0775, true);
        mkdir($projectPath . '/instances', 0775, true);
        $this->db['projects'][$id] = [
            'id' => $id,
            'name' => $name,
            'path' => $projectPath,
            'beta_version_id' => null,
            'stable_version_id' => null,
        ];
        $this->saveDatabase();

        return $this->db['projects'][$id];
    }

    public function getProject($id)
    {
        return $this->db['projects'][$id] ?? null;
    }

    public function getVersion($id)
    {
        return $this->db['versions'][$id] ?? null;
    }

    public function getVersions($projectId)
    {
        $versions = array_filter($this->db['versions'] ?? [], function ($version) use ($projectId) {
            return $version['project_id'] === $projectId;
        });
        usort($versions, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        return $versions;
    }

    public function getLastVersion($projectId)
    {
        $versions = $this->getVersions($projectId);
        return $versions[0] ?? null;
    }

    public function createVersion($projectId, $versionNumber, $copyLast)
    {
        $project = $this->getProject($projectId);
        if (!$project) {
            throw new Exception("Projekt nicht gefunden.");
        }

        if (empty(trim($versionNumber))) {
            throw new Exception("Versionsnummer darf nicht leer sein.");
        }

        if (!preg_match('/.*[0-9]$/', $versionNumber)) {
            throw new Exception("Die Versionsnummer muss mit einer Zahl enden.");
        }

        $versionPath = $project['path'] . '/master/' . $versionNumber;
        if (is_dir($versionPath)) {
            throw new Exception("Eine Version mit dieser Nummer existiert bereits.");
        }

        if (!mkdir($versionPath, 0775, true)) {
            throw new Exception("Versionsverzeichnis konnte nicht erstellt werden.");
        }

        // 'current' Symlink immer auf die neueste Version aktualisieren
        $latestLink = $project['path'] . '/latest/current';
        if (file_exists($latestLink) || is_link($latestLink)) {
            unlink($latestLink);
        }
        symlink($versionPath, $latestLink);

        $lastVersion = $this->getLastVersion($projectId);
        $versionId = uniqid('ver_');
        $this->db['versions'][$versionId] = [
            'id' => $versionId,
            'project_id' => $projectId,
            'version_number' => $versionNumber,
            'path' => $versionPath,
            'created_at' => date('Y-m-d H:i:s'),
            'is_release' => false
        ];
        if ($copyLast) {
            if ($lastVersion) {
                $this->copyDirectory($lastVersion['path'], $versionPath);
            } else {
                throw new Exception("Kopieren fehlgeschlagen: Keine vorherige Version zum Kopieren gefunden.");
            }
        }

        $this->saveDatabase();
        return $this->db['versions'][$versionId];
    }

    public function createReleaseZip($versionId, $releaseName)
    {
        if (!isset($this->db['versions'][$versionId])) {
            throw new Exception("Version nicht gefunden.");
        }
        if (empty(trim($releaseName))) {
            throw new Exception("Release-Name darf nicht leer sein.");
        }

        $version = $this->db['versions'][$versionId];
        $project = $this->getProject($version['project_id']);

        // 1. Temporäres Verzeichnis für die Bereinigung erstellen
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vergit_release_' . uniqid();
        if (!@mkdir($tempDir, 0777, true)) {
            throw new Exception("Konnte temporäres Verzeichnis nicht erstellen.");
        }

        try {
            // 2. Originalversion in das temporäre Verzeichnis kopieren
            $this->copyDirectory($version['path'], $tempDir);

            // 3. .srbkup-Dateien aus dem temporären Verzeichnis bereinigen
            $this->recursiveDeleteSrbkup($tempDir);

            // 4. ZIP-Archiv erstellen
            $releaseDir = $project['path'] . DIRECTORY_SEPARATOR . 'release';
            if (!is_dir($releaseDir)) @mkdir($releaseDir, 0775, true);
            
            $safeReleaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $releaseName);
            $zipPath = $releaseDir . DIRECTORY_SEPARATOR . $safeReleaseName . '.zip';

            if (file_exists($zipPath)) {
                throw new Exception("Ein Release-Archiv mit dem Namen '{$safeReleaseName}.zip' existiert bereits.");
            }
            
            $this->zipDirectory($tempDir, $zipPath);

        } finally {
            // 5. Temporäres Verzeichnis immer aufräumen
            $this->recursiveDelete($tempDir);
        }

        // 6. Version als "released" markieren (optional, aber gut für die UI)
        $this->db['versions'][$versionId]['is_release'] = true;
        $this->saveDatabase();

        return $zipPath;
    }

    public function deleteVersion($versionId)
    {
        if (!isset($this->db['versions'][$versionId])) {
            throw new Exception("Zu löschende Version nicht gefunden.");
        }
        $version = $this->db['versions'][$versionId];
        $project = $this->getProject($version['project_id']);

        // Channel-Verknüpfungen aufheben, falls diese Version aktiv war
        if (($project['beta_version_id'] ?? null) === $versionId) $this->unsetChannel($project['id'], 'beta');
        if (($project['stable_version_id'] ?? null) === $versionId) $this->unsetChannel($project['id'], 'stable');
        
        // Verzeichnis löschen
        if (is_dir($version['path'])) {
            $this->recursiveDelete($version['path']);
        }

        // Instanzen löschen
        $instances = $this->getInstances($versionId);
        foreach ($instances as $instance) {
            $this->deleteInstance($instance['id']);
        }

        // 'current' Symlink prüfen und ggf. auf vorherige Version setzen
        $latestLink = $project['path'] . '/latest/current';
        if (is_link($latestLink) && readlink($latestLink) == $version['path']) {
            unlink($latestLink);
        }

        unset($this->db['versions'][$versionId]);
        $lastVersion = $this->getLastVersion($project['id']);
        if ($lastVersion) {
            if (!file_exists($latestLink)) {
                symlink($lastVersion['path'], $latestLink);
            }
        }

        $this->saveDatabase();
    }
    
    public function getInstances($versionId)
    {
        if (empty($this->db['instances'])) return [];
        $instances = array_filter($this->db['instances'], function ($instance) use ($versionId) {
            return $instance['version_id'] === $versionId;
        });
        usort($instances, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        return $instances;
    }

    public function createInstance($versionId)
    {
        if (!isset($this->db['versions'][$versionId])) {
            throw new Exception("Version nicht gefunden, um eine Instanz zu erstellen.");
        }
        $version = $this->db['versions'][$versionId];
        $project = $this->getProject($version['project_id']);

        $instancesDir = $project['path'] . '/instances';
        if (!is_dir($instancesDir)) {
            if (!mkdir($instancesDir, 0775, true)) {
                throw new Exception("Instanz-Verzeichnis konnte nicht erstellt werden.");
            }
        }

        $instanceId = 'inst_' . bin2hex(random_bytes(8));
        $instancePath = $instancesDir . '/' . $instanceId;

        $this->copyDirectory($version['path'], $instancePath);
        $this->db['instances'][$instanceId] = [
            'id' => $instanceId,
            'project_id' => $version['project_id'],
            'version_id' => $versionId,
            'path' => $instancePath,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->saveDatabase();

        return $this->db['instances'][$instanceId];
    }

    public function deleteInstance($instanceId)
    {
        if (!isset($this->db['instances'][$instanceId])) {
            throw new Exception("Zu löschende Instanz nicht gefunden.");
        }
        $instance = $this->db['instances'][$instanceId];
        if (is_dir($instance['path'])) {
            $this->recursiveDelete($instance['path']);
        }

        unset($this->db['instances'][$instanceId]);
        $this->saveDatabase();
    }
    
    public function getArchives()
    {
        $archives = $this->db['archives'] ?? [];
        uasort($archives, function ($a, $b) {
            return strtotime($b['archived_at']) - strtotime($a['archived_at']);
        });
        return $archives;
    }

    public function archiveProject($projectId)
    {
        if (!isset($this->db['projects'][$projectId])) {
            throw new Exception("Zu archivierendes Projekt nicht gefunden.");
        }
        $project = $this->db['projects'][$projectId];
        $versions = $this->getVersions($projectId);
        $instances = [];
        foreach ($this->db['instances'] as $instance) {
            if ($instance['project_id'] === $projectId) {
                $instances[] = $instance;
            }
        }

        $archiveDir = $this->config['archive_path'];
        if (!is_writable($archiveDir)) {
            throw new Exception("Archiv-Verzeichnis ist nicht beschreibbar: " . $archiveDir);
        }
        $zipFilename = 'archive_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']) . '_' . date('Ymd_His') . '.zip';
        $zipPath = $archiveDir . '/' . $zipFilename;

        $this->zipDirectory($project['path'], $zipPath);

        $archiveId = uniqid('arch_');
        $this->db['archives'][$archiveId] = [
            'id' => $archiveId,
            'zip_path' => $zipPath,
            'archived_at' => date('Y-m-d H:i:s'),
            'original_project_data' => $project,
            'original_versions_data' => $versions,
            'original_instances_data' => $instances
        ];
        $this->recursiveDelete($project['path']);

        foreach ($versions as $version) {
            unset($this->db['versions'][$version['id']]);
        }
        foreach ($instances as $instance) {
            unset($this->db['instances'][$instance['id']]);
        }
        unset($this->db['projects'][$projectId]);

        $this->saveDatabase();
    }
    
    public function restoreProject($archiveId)
    {
        if (!isset($this->db['archives'][$archiveId])) {
            throw new Exception("Archiv zur Wiederherstellung nicht gefunden.");
        }
        $archiveData = $this->db['archives'][$archiveId];
        $projectData = $archiveData['original_project_data'];
        if (isset($this->db['projects'][$projectData['id']])) {
            throw new Exception("Ein Projekt mit der gleichen ID existiert bereits.");
        }
        if (is_dir($projectData['path'])) {
            throw new Exception("Ein Ordner am ursprünglichen Speicherort existiert bereits. Bitte manuell bereinigen.");
        }

        $zip = new ZipArchive;
        if ($zip->open($archiveData['zip_path']) === TRUE) {
            if (!$zip->extractTo(dirname($projectData['path']))) {
                 $zip->close();
                throw new Exception("ZIP-Archiv konnte nicht entpackt werden. Prüfen Sie die Berechtigungen.");
            }
            $zip->close();
        } else {
            throw new Exception("ZIP-Archiv konnte nicht geöffnet werden.");
        }

        $this->db['projects'][$projectData['id']] = $projectData;
        foreach ($archiveData['original_versions_data'] as $version) {
            $this->db['versions'][$version['id']] = $version;
        }
        foreach ($archiveData['original_instances_data'] as $instance) {
            $this->db['instances'][$instance['id']] = $instance;
        }

        unlink($archiveData['zip_path']);
        unset($this->db['archives'][$archiveId]);

        $this->saveDatabase();
    }

    public function deleteArchivePermanently($archiveId)
    {
        if (!isset($this->db['archives'][$archiveId])) {
            throw new Exception("Zu löschendes Archiv nicht gefunden.");
        }
        $archiveData = $this->db['archives'][$archiveId];
        if (file_exists($archiveData['zip_path'])) {
            if(!@unlink($archiveData['zip_path'])) {
                 throw new Exception("ZIP-Datei des Archivs konnte nicht gelöscht werden.");
            }
        }

        unset($this->db['archives'][$archiveId]);
        $this->saveDatabase();
    }

    public function repairProject($projectId)
    {
        $project = $this->getProject($projectId);
        if (!$project) {
            throw new Exception("Zu reparierendes Projekt nicht gefunden.");
        }

        $projectPath = $project['path'];
        $repairedDirs = [];
        $repairedLinks = [];
        $messageParts = [];

        // 1. Erforderliche Verzeichnisse prüfen und erstellen
        $requiredDirs = ['master', 'latest', 'release', 'instances'];
        foreach ($requiredDirs as $dirName) {
            $dirPath = $projectPath . DIRECTORY_SEPARATOR . $dirName;
            if (!is_dir($dirPath)) {
                if (@mkdir($dirPath, 0775, true)) {
                    $repairedDirs[] = $dirName;
                } else {
                    throw new Exception("Konnte das notwendige Verzeichnis nicht erstellen: {$dirName}. Bitte Berechtigungen prüfen.");
                }
            }
        }
        if (!empty($repairedDirs)) {
            $messageParts[] = "Fehlende Verzeichnisse erstellt: " . implode(', ', $repairedDirs) . ".";
        }

        // 2. 'latest/current' Symlink reparieren
        $lastVersion = $this->getLastVersion($projectId);
        if ($lastVersion) {
            $latestLink = $projectPath . DIRECTORY_SEPARATOR . 'latest' . DIRECTORY_SEPARATOR . 'current';
            $targetPath = $lastVersion['path'];
            $linkNeedsRepair = false;

            if (is_link($latestLink)) {
                if (readlink($latestLink) !== $targetPath) {
                    @unlink($latestLink);
                    $linkNeedsRepair = true;
                }
            } else {
                if(file_exists($latestLink)) @unlink($latestLink);
                $linkNeedsRepair = true;
            }

            if ($linkNeedsRepair) {
                 if (@symlink($targetPath, $latestLink)) {
                    $repairedLinks[] = "'latest/current' -> '" . basename($targetPath) . "'";
                 }
            }
        }
        
        // 3. 'beta' and 'stable' Symlinks reparieren
        $channels = ['beta', 'stable'];
        foreach ($channels as $channel) {
            $versionIdKey = $channel . '_version_id';
            $targetVersionId = $project[$versionIdKey] ?? null;
            $channelLink = $projectPath . DIRECTORY_SEPARATOR . 'latest' . DIRECTORY_SEPARATOR . $channel;

            if ($targetVersionId) {
                // Eine Version ist im DB-Eintrag für diesen Kanal hinterlegt
                $targetVersion = $this->getVersion($targetVersionId);
                if ($targetVersion) {
                    $targetPath = $targetVersion['path'];
                    $linkNeedsRepair = false;

                    if (is_link($channelLink)) {
                        if (readlink($channelLink) !== $targetPath) {
                            @unlink($channelLink);
                            $linkNeedsRepair = true;
                        }
                    } else {
                        if (file_exists($channelLink)) @unlink($channelLink);
                        $linkNeedsRepair = true;
                    }

                    if ($linkNeedsRepair) {
                        if (@symlink($targetPath, $channelLink)) {
                            $repairedLinks[] = "'latest/{$channel}' -> '" . basename($targetPath) . "'";
                        }
                    }
                } else {
                    // DB hat eine ID, aber die Version existiert nicht mehr -> verwaister Link
                    if (file_exists($channelLink) || is_link($channelLink)) {
                        @unlink($channelLink);
                        $repairedLinks[] = "verwaister 'latest/{$channel}' Link entfernt";
                    }
                }
            } else {
                // Keine Version im DB-Eintrag -> der Link sollte nicht existieren
                if (file_exists($channelLink) || is_link($channelLink)) {
                    @unlink($channelLink);
                    $repairedLinks[] = "überflüssiger 'latest/{$channel}' Link entfernt";
                }
            }
        }

        if (!empty($repairedLinks)) {
            $uniqueRepairedLinks = array_unique($repairedLinks);
            $messageParts[] = "Symlink(s) repariert: " . implode(', ', $uniqueRepairedLinks) . ".";
        }

        if (empty($messageParts)) {
            return "Projekt '" . htmlspecialchars($project['name']) . "': Struktur und Symlinks scheinen korrekt zu sein. Keine Änderungen vorgenommen.";
        }

        return "Projekt '" . htmlspecialchars($project['name']) . "' repariert. " . implode(' ', $messageParts);
    }
    
    public function setChannel($projectId, $versionId, $channel) {
        if ($channel !== 'beta' && $channel !== 'stable') {
            throw new Exception("Ungültiger Kanal angegeben.");
        }
        $project = $this->getProject($projectId);
        $version = $this->getVersion($versionId);
        if (!$project || !$version) {
            throw new Exception("Projekt oder Version nicht gefunden.");
        }

        $linkPath = $project['path'] . '/latest/' . $channel;
        if (file_exists($linkPath) || is_link($linkPath)) {
            unlink($linkPath);
        }

        if (!symlink($version['path'], $linkPath)) {
            throw new Exception("Symlink für Kanal '{$channel}' konnte nicht erstellt werden.");
        }

        $this->db['projects'][$projectId][$channel . '_version_id'] = $versionId;
        $this->saveDatabase();
    }

    public function unsetChannel($projectId, $channel) {
        if ($channel !== 'beta' && $channel !== 'stable') {
            throw new Exception("Ungültiger Kanal angegeben.");
        }
        $project = $this->getProject($projectId);
        if (!$project) {
            throw new Exception("Projekt nicht gefunden.");
        }

        $linkPath = $project['path'] . '/latest/' . $channel;
        if (file_exists($linkPath) || is_link($linkPath)) {
            unlink($linkPath);
        }

        $this->db['projects'][$projectId][$channel . '_version_id'] = null;
        $this->saveDatabase();
    }

    public function deleteReleaseFile($projectId, $fileName) {
        $project = $this->getProject($projectId);
        if (!$project) {
            throw new Exception("Projekt nicht gefunden.");
        }
        $filePath = $project['path'] . '/release/' . basename($fileName);

        if (!file_exists($filePath)) {
            throw new Exception("Release-Datei nicht gefunden.");
        }
        if (!is_writable($filePath)) {
            throw new Exception("Keine Berechtigung zum Löschen der Release-Datei.");
        }
        if (!@unlink($filePath)) {
            throw new Exception("Release-Datei konnte nicht gelöscht werden.");
        }
    }

    private function recursiveDeleteSrbkup($dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.srbkup(\d*)$/i', $file->getFilename())) {
                @unlink($file->getPathname());
            }
        }
    }

    private function copyDirectory($source, $destination) {
        if (!is_dir($source)) return;
        $dir = opendir($source);
        @mkdir($destination, 0775, true);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($source . '/' . $file)) {
                    $this->copyDirectory($source . '/' . $file, $destination . '/' . $file);
                } else {
                    copy($source . '/' . $file, $destination . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function recursiveDelete($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir) || is_link($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->recursiveDelete($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }

    private function zipDirectory($source, $destination)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            throw new Exception("ZIP-Erweiterung nicht geladen oder Quell-Verzeichnis nicht gefunden.");
        }
        $source = realpath($source);
        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("ZIP-Archiv konnte nicht erstellt werden: " . $destination);
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($source) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
    }

    public function fixPathOnStartup($lastPath, $rootPath)
    {
        if (!empty($lastPath) && is_dir($lastPath)) {
            return $lastPath;
        }
        return $rootPath;
    }
}