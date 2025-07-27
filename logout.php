<?php
session_start();
require_once __DIR__ . '/helpers.php';

// Logout nur per POST mit gültigem CSRF-Token erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

validate_csrf_token();

// Löscht alle Session-Variablen
$_SESSION = [];

// Zerstört die Session-Cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Zerstört die Session
session_destroy();

// Leitet zum Login weiter
header('Location: login.php');
exit;
