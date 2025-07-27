<?php // lib/file_manager_api.php
session_start();
if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../helpers.php';

// CSRF-Schutz für alle schreibenden Aktionen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
}

require_once __DIR__ . '/../config/config.php';

$baseDir = realpath($ROOT_PATH);
$webRootDir = realpath($WEB_ROOT_PATH);

function is_safe_path($path, $baseDir) {
    // Erlaubt auch Pfade, die noch nicht existieren (z.B. für 'create' Aktionen)
    // indem der Pfad normalisiert wird.
    $realBaseDir = rtrim(realpath($baseDir), DIRECTORY_SEPARATOR);
    $realPath = realpath($path);

    if ($realPath) { // Wenn der Pfad existiert
        return strpos($realPath, $realBaseDir) === 0;
    }
    
    // Wenn der Pfad nicht existiert (z.B. neue Datei/Ordner), prüfe den übergeordneten Ordner
    $parent = dirname($path);
    $realParent = realpath($parent);
    if ($realParent) {
        return strpos($realParent, $realBaseDir) === 0;
    }
    
    return false;
}


function format_bytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = floor(log($bytes, 1024));
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

function recursive_delete($path) {
    if (is_dir($path)) {
        $objects = scandir($path);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($path . "/" . $object) && !is_link($path . "/" . $object)) {
                    recursive_delete($path . "/" . $object);
                } else {
                    @unlink($path . "/" . $object);
                }
            }
        }
        return @rmdir($path);
    } elseif (is_file($path)) {
        return @unlink($path);
    }
    return false;
}

function recursive_copy($src, $dst) {
    if (!is_dir($dst)) { mkdir($dst, 0755, true); }
    $dir = opendir($src);
    while (($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recursive_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function get_unique_filename($path) {
    if (!file_exists($path)) { return $path; }
    $path_info = pathinfo($path);
    $filename = $path_info['filename'];
    $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';
    $dirname = $path_info['dirname'];
    $counter = 1;
    while (file_exists($dirname . '/' . $filename . '_' . $counter . $extension)) {
        $counter++;
    }
    return $dirname . '/' . $filename . '_' . $counter . $extension;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$path = $_POST['path'] ?? $_GET['path'] ?? '';
$items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

if (empty($path)) {
    $path = $baseDir;
}

// --- BUGFIX-LOGIK ---
// Explizite Prüfung, ob der Pfad für Lese-Aktionen existiert.
if ($action === 'list' || $action === 'get_dir_timestamp') {
    if (!is_dir($path)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Verzeichnis existiert nicht: ' . htmlspecialchars($path)]);
        exit;
    }
}
// --- ENDE BUGFIX-LOGIK ---

if (!is_safe_path($path, $baseDir)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Ungültiger oder unsicherer Pfad.']);
    exit;
}

$editable_extensions = ['txt', 'php', 'html', 'css', 'js', 'json', 'md', 'xml', 'ini', 'sh', 'yaml', 'sql', 'log', 'htaccess', 'env', 'srbkup'];

switch ($action) {
    case 'download':
        $file_path = $_GET['path'] ?? '';
        if (empty($file_path) || !is_safe_path($file_path, $baseDir) || !is_file($file_path) || !is_readable($file_path)) {
            http_response_code(404);
            die('Datei nicht gefunden oder nicht lesbar.');
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        flush(); 
        readfile($file_path);
        exit;
    
    case 'get_dir_timestamp':
        $dir_timestamp = filemtime($path);
        echo json_encode(['status' => 'success', 'timestamp' => $dir_timestamp]);
        exit;
    default:
        header('Content-Type: application/json');
}

switch ($action) {
    case 'list':
        $sortBy = $_GET['sort'] ?? 'name';
        $sortOrder = $_GET['order'] ?? 'asc';
        try {
            $real_path = realpath($path);
            $parent_path = null;
            if ($real_path !== false && $baseDir !== false && $real_path !== $baseDir) {
                $parent_path = dirname($real_path);
            }

            $all_items = [];
            $dir_count = 0;
            $file_count = 0;
            $total_size = 0;
            $iterator = new DirectoryIterator($path);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isDot()) continue;
                $owner_info = function_exists('posix_getpwuid') ? posix_getpwuid($fileinfo->getOwner()) : ['name' => $fileinfo->getOwner()];
                $extension = strtolower($fileinfo->getExtension());
                $real_item_path = $fileinfo->getRealPath();
                $web_path = null;
                if (!$fileinfo->isDir() && strpos($real_item_path, $webRootDir) === 0) {
                    $web_path = str_replace($webRootDir, '', $real_item_path);
                    $web_path = str_replace('\\', '/', $web_path);
                }

                if ($fileinfo->isDir()) {
                    $dir_count++;
                } else {
                    $file_count++;
                    $total_size += $fileinfo->getSize();
                }

                $all_items[] = [
                    'name' => $fileinfo->getFilename(),
                    'path' => $real_item_path,
                    'is_dir' => $fileinfo->isDir(),
                    'is_editable' => !$fileinfo->isDir() && in_array($extension, $editable_extensions),
                    'web_path' => $web_path,
                    'size_bytes' => $fileinfo->isDir() ? -1 : $fileinfo->getSize(),
                    'size' => $fileinfo->isDir() ? 'Ordner' : format_bytes($fileinfo->getSize()),
                    'modified' => $fileinfo->getMTime(),
                    'perms' => substr(sprintf('%o', $fileinfo->getPerms()), -4),
                    'owner' => $owner_info['name'] ?? $fileinfo->getOwner(),
                    'mime_type' => $fileinfo->isDir() ? 'Verzeichnis' : (mime_content_type($real_item_path) ?: 'Unbekannt'),
                ];
            }
            usort($all_items, function($a, $b) use ($sortBy, $sortOrder) {
                if ($a['is_dir'] !== $b['is_dir']) { return $a['is_dir'] ? -1 : 1; }
                $val_a = $a[$sortBy] ?? ''; $val_b = $b[$sortBy] ?? ''; $cmp = 0;
                if (in_array($sortBy, ['size_bytes', 'modified'])) { $cmp = $val_a <=> $val_b; }
                else { $cmp = strnatcasecmp($val_a, $val_b); }
                return $sortOrder === 'asc' ? $cmp : -$cmp;
            });
            echo json_encode([
                'status' => 'success', 
                'path' => $real_path, 
                'parent' => $parent_path,
                'items' => $all_items,
                'info' => [
                    'dir_count' => $dir_count,
                    'file_count' => $file_count,
                    'total_size' => format_bytes($total_size)
                ],
                'dir_modified_timestamp' => filemtime($real_path)
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Verzeichnis konnte nicht gelesen werden: ' . $e->getMessage()]);
        }
        break;

    case 'chmod':
        $itemPath = $_POST['item'] ?? '';
        $perms = $_POST['perms'] ?? '';
        if (empty($itemPath) || empty($perms) || !preg_match('/^[0-7]{3,4}$/', $perms)) { echo json_encode(['status' => 'error', 'message' => 'Ungültige Datei oder Berechtigungen.']); exit; }
        if (!is_safe_path($itemPath, $baseDir)) { echo json_encode(['status' => 'error', 'message' => 'Ungültiger Pfad.']); exit; }
        if (@chmod($itemPath, octdec("0" . $perms))) { echo json_encode(['status' => 'success', 'message' => 'Berechtigungen erfolgreich geändert.']);
        } else { echo json_encode(['status' => 'error', 'message' => 'Ändern der Berechtigungen fehlgeschlagen.']); }
        break;

    case 'create':
        $type = $_POST['type'] ?? '';
        $name = $_POST['name'] ?? '';
        if (empty($type) || empty($name) || strpos($name, '/') !== false || strpos($name, '\\') !== false) { echo json_encode(['status' => 'error', 'message' => 'Ungültiger Typ oder Name.']); exit; }
        $newPath = rtrim($path, '/') . '/' . $name;
        if (!is_safe_path($newPath, $baseDir)) { echo json_encode(['status' => 'error', 'message' => 'Ungültiger Pfad.']); exit; }
        if (file_exists($newPath)) { echo json_encode(['status' => 'error', 'message' => 'Eine Datei oder ein Ordner mit diesem Namen existiert bereits.']); exit; }
        $success = false;
        if ($type === 'file') { if (@touch($newPath)) $success = true; }
        elseif ($type === 'folder') { if (@mkdir($newPath)) $success = true; }
        if ($success) { echo json_encode(['status' => 'success', 'message' => ($type === 'file' ? 'Datei' : 'Ordner') . ' erfolgreich erstellt.']);
        } else { echo json_encode(['status' => 'error', 'message' => 'Erstellen fehlgeschlagen. Prüfen Sie die Berechtigungen.']); }
        break;

    case 'delete':
        if (empty($items)) { echo json_encode(['status' => 'error', 'message' => 'Keine Elemente zum Löschen ausgewählt.']); exit; }
        $errors = [];
        foreach ($items as $itemPath) {
            if (!is_safe_path($itemPath, $baseDir)) { $errors[] = "Ungültiger Pfad: " . htmlspecialchars($itemPath); continue; }
            if (!file_exists($itemPath)) { $errors[] = "Nicht gefunden: " . htmlspecialchars(basename($itemPath)); continue; }
            if (!recursive_delete($itemPath)) { $errors[] = "Fehler beim Löschen: " . htmlspecialchars(basename($itemPath)); }
        }
        if (empty($errors)) { echo json_encode(['status' => 'success', 'message' => 'Ausgewählte Elemente erfolgreich gelöscht.']);
        } else { echo json_encode(['status' => 'error', 'message' => "Einige Elemente konnten nicht gelöscht werden:\n" . implode("\n", $errors)]); }
        break;

    case 'rename':
        $oldPath = $_POST['item'] ?? '';
        $newName = $_POST['newName'] ?? '';
        if (empty($oldPath) || empty($newName) || strpos($newName, '/') !== false || strpos($newName, '\\') !== false) { echo json_encode(['status' => 'error', 'message' => 'Ungültiger alter Pfad oder neuer Name.']); exit; }
        if (!is_safe_path($oldPath, $baseDir)) { echo json_encode(['status' => 'error', 'message' => 'Ungültiger Pfad.']); exit; }
        $newPath = dirname($oldPath) . '/' . $newName;
        if (file_exists($newPath)) { echo json_encode(['status' => 'error', 'message' => 'Eine Datei oder ein Ordner mit diesem Namen existiert bereits.']); exit; }
        if (rename($oldPath, $newPath)) { echo json_encode(['status' => 'success', 'message' => 'Erfolgreich umbenannt.']);
        } else { echo json_encode(['status' => 'error', 'message' => 'Umbenennen fehlgeschlagen.']); }
        break;

    case 'move_copy':
        $destination = $_POST['destination'] ?? '';
        $type = $_POST['type'] ?? 'copy';
        if (empty($items) || empty($destination)) { echo json_encode(['status' => 'error', 'message' => 'Keine Quelldateien oder kein Zielordner angegeben.']); exit; }
        if (!is_safe_path($destination, $baseDir) || !is_dir($destination)) { echo json_encode(['status' => 'error', 'message' => 'Ungültiges oder nicht existierendes Zielverzeichnis.']); exit; }
        $errors = [];
        foreach ($items as $itemPath) {
            if (!is_safe_path($itemPath, $baseDir)) { $errors[] = "Ungültiger Pfad: " . basename($itemPath); continue; }
            $destPath = rtrim($destination, '/') . '/' . basename($itemPath);
            if ($type === 'move' && realpath($itemPath) == realpath($destPath)) { continue; }
            if ($type === 'copy') {
                $finalDestPath = get_unique_filename($destPath);
                if (is_dir($itemPath)) { recursive_copy($itemPath, $finalDestPath); }
                else { if (!copy($itemPath, $finalDestPath)) $errors[] = "Kopieren fehlgeschlagen: " . basename($itemPath); }
            } elseif ($type === 'move') {
                if(file_exists($destPath)) { $errors[] = "Verschieben fehlgeschlagen: Zieldatei '" . basename($itemPath) . "' existiert bereits."; continue; }
                if (!rename($itemPath, $destPath)) $errors[] = "Verschieben fehlgeschlagen: " . basename($itemPath);
            }
        }
        if (empty($errors)) { echo json_encode(['status' => 'success', 'message' => 'Elemente erfolgreich ' . ($type === 'copy' ? 'kopiert.' : 'verschoben.')]);
        } else { echo json_encode(['status' => 'error', 'message' => "Einige Fehler sind aufgetreten:\n" . implode("\n", $errors)]); }
        break;

    case 'zip':
        $zipFilename = $_POST['zipName'] ?? 'compressed_' . date('Ymd_His') . '.zip';
        if (empty($zipFilename)) { $zipFilename = 'compressed_' . date('Ymd_His') . '.zip'; }
        if (pathinfo($zipFilename, PATHINFO_EXTENSION) !== 'zip') { $zipFilename .= '.zip'; }
        $zipPath = rtrim($path, '/') . '/' . $zipFilename;
        if (empty($items)) { echo json_encode(['status' => 'error', 'message' => 'Keine Elemente zum Zippen ausgewählt.']); exit; }
        if (file_exists($zipPath)) { echo json_encode(['status' => 'error', 'message' => 'Eine ZIP-Datei mit diesem Namen existiert bereits.']); exit; }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) { echo json_encode(['status' => 'error', 'message' => 'ZIP-Archiv konnte nicht erstellt werden.']); exit; }
        foreach ($items as $itemPath) {
            if (!is_safe_path($itemPath, $baseDir)) continue;
            if (is_dir($itemPath)) {
                $filesInDir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($itemPath, RecursiveIteratorIterator::LEAVES_ONLY), RecursiveIteratorIterator::SELF_FIRST);
                $baseInZip = basename($itemPath);
                $zip->addEmptyDir($baseInZip);
                foreach ($filesInDir as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = $baseInZip . '/' . substr($filePath, strlen($itemPath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            } else { $zip->addFile($itemPath, basename($itemPath)); }
        }
        $zip->close();
        echo json_encode(['status' => 'success', 'message' => 'Dateien erfolgreich als ' . htmlspecialchars($zipFilename) . ' verpackt.']);
        break;
    
    case 'unzip':
        $zipFile = $_POST['item'] ?? '';
        $extractToFolder = $_POST['extractToFolder'] ?? '0';
        if (empty($zipFile) || !is_safe_path($zipFile, $baseDir) || pathinfo($zipFile, PATHINFO_EXTENSION) !== 'zip') {
            echo json_encode(['status' => 'error', 'message' => 'Ungültige ZIP-Datei angegeben.']);
            exit;
        }
        $destination = $path;
        if ($extractToFolder === '1') {
            $folderName = pathinfo($zipFile, PATHINFO_FILENAME);
            $destination = rtrim($path, '/') . '/' . $folderName;
            if (!is_dir($destination)) {
                if (!mkdir($destination, 0755, true)) {
                    echo json_encode(['status' => 'error', 'message' => 'Konnte Zielordner nicht erstellen.']);
                    exit;
                }
            }
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($destination);
            $zip->close();
            echo json_encode(['status' => 'success', 'message' => 'Archiv erfolgreich entpackt.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ZIP-Archiv konnte nicht geöffnet werden.']);
        }
        break;

    case 'upload':
        if (empty($_FILES['files'])) {
            echo json_encode(['status' => 'error', 'message' => 'Keine Dateien für den Upload empfangen.']);
            exit;
        }
        $errors = [];
        $fileCount = count($_FILES['files']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $filename = basename($_FILES['files']['name'][$i]);
            $destination = rtrim($path, '/') . '/' . $filename;
            if (!is_safe_path($destination, $baseDir)) { $errors[] = "Ungültiger Zielpfad für: " . htmlspecialchars($filename); continue; }
            $finalDestination = get_unique_filename($destination);
            if (!move_uploaded_file($_FILES['files']['tmp_name'][$i], $finalDestination)) {
                $errors[] = "Upload fehlgeschlagen für: " . htmlspecialchars($filename);
            }
        }
        if (empty($errors)) {
            echo json_encode(['status' => 'success', 'message' => $fileCount . ' Datei(en) erfolgreich hochgeladen.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Einige Fehler sind aufgetreten:\n" . implode("\n", $errors)]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unbekannte Aktion.']);
        break;
}