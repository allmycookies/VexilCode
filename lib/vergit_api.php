<?php // lib/vergit_api.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vergit.class.php';

validate_csrf_token();

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Unbekannte Aktion.'];

try {
    $vergit = new Vergit();

    switch ($action) {
        case 'get_instances':
            $versionId = $_POST['version_id'] ?? null;
            if (!$versionId) throw new Exception('Keine Versions-ID angegeben.');
            
            $instances = $vergit->getInstances($versionId);
            $instancesWithUrls = [];
            foreach ($instances as $instance) {
                // Erstelle den Web-Link für jede Instanz
                $realWebRoot = realpath($WEB_ROOT_PATH);
                $realInstancePath = realpath($instance['path']);

                if ($realWebRoot !== false && $realInstancePath !== false && strpos($realInstancePath, $realWebRoot) === 0) {
                    $relativePath = str_replace($realWebRoot, '', $realInstancePath);
                    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $domain = $_SERVER['HTTP_HOST'];
                    $instance['url'] = $protocol . $domain . $relativePath;
                } else {
                    $instance['url'] = null; // Pfad ist nicht über das Web erreichbar
                }
                $instancesWithUrls[] = $instance;
            }
            
            $response = ['status' => 'success', 'instances' => $instancesWithUrls];
            break;

        case 'create_instance':
            $versionId = $_POST['version_id'] ?? null;
            if (!$versionId) throw new Exception('Keine Versions-ID angegeben.');
            
            $newInstance = $vergit->createInstance($versionId);
            $response = ['status' => 'success', 'message' => 'Neue Instanz erfolgreich erstellt.', 'instance' => $newInstance];
            break;

        case 'delete_instance':
            $instanceId = $_POST['instance_id'] ?? null;
            if (!$instanceId) throw new Exception('Keine Instanz-ID angegeben.');

            $vergit->deleteInstance($instanceId);
            $response = ['status' => 'success', 'message' => 'Instanz erfolgreich gelöscht.'];
            break;

        default:
            http_response_code(400);
            $response['message'] = 'Ungültige Aktion angegeben.';
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
// Ende der Datei: lib/vergit_api.php