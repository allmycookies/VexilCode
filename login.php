<?php
session_start();

define('USERS_FILE', __DIR__ . '/config/users.php');
require_once __DIR__ . '/helpers.php';
// Generiert einen CSRF-Token für die Formulare
generate_csrf_token();
// Bereinigt bei jedem Aufruf der Login-Seite abgelaufene Sperren
cleanup_expired_lockouts();
// Setzt den Zeitstempel für die Time-Trap, wenn die Seite geladen wird
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['login_form_load_time'] = time();
}

// Prüfen, ob die Konfigurationsdatei existiert und beschreibbar ist
$setup_required = false;
$config_writable = is_writable(USERS_FILE);
$users = [];
if (file_exists(USERS_FILE)) {
    require USERS_FILE;
    if (empty($users)) {
        $setup_required = true;
    }
} else {
    $setup_required = true;
}

if (isset($_SESSION['webtool_logged_in']) && $_SESSION['webtool_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_bot = false;
    // 1. Time-Trap Prüfung: Zeit auf 1 Sekunde reduziert
    $load_time = $_SESSION['login_form_load_time'] ?? 0;
    $submit_duration = time() - $load_time;
    if ($submit_duration < 1) { // Wenn das Formular in weniger als 1 Sekunde abgeschickt wird
        $is_bot = true;
    }

    // 2. Statisches Honeypot-Feld-Prüfung
    if (!empty($_POST['email_confirm'])) {
        $is_bot = true;
    }
    
    // Wenn ein Bot erkannt wurde, zeige eine generische Fehlermeldung und beende das Skript
    if ($is_bot) {
        $error = 'Ungültiger Benutzername oder Passwort.';
    } else {
        validate_csrf_token();
        // --- Logik für das Setup des ersten Benutzers ---
        if ($setup_required && isset($_POST['setup_user'])) {
            if (!$config_writable) {
                $error = "FEHLER: Die Datei config/users.php ist nicht beschreibbar. Bitte passen Sie die Dateiberechtigungen an (z.B. chmod 664).";
            } else {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';

                if (!empty($username) && !empty($password)) {
                    if (preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $users = [$username => $hashed_password];
                        $file_content = "<?php\n\n\$users = " . var_export($users, true) . ";\n";
                        if (file_put_contents(USERS_FILE, $file_content) !== false) {
                            $_SESSION['webtool_logged_in'] = true;
                            $_SESSION['username'] = $username;
                            session_regenerate_id(true);
                            header('Location: index.php');
                            exit;
                        } else {
                            $error = "Konnte die Benutzerdatei nicht schreiben.";
                        }
                    } else {
                        $error = "Benutzername ist ungültig. Erlaubt sind 3-20 Zeichen (a-z, A-Z, 0-9, _).";
                    }
                } else {
                    $error = 'Benutzername und Passwort dürfen nicht leer sein.';
                }
            }
        }
        // --- Logik für den normalen Login ---
        elseif (!$setup_required && isset($_POST['login'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if (is_user_locked_out($username)) {
                $error = 'Dieser Account ist aufgrund zu vieler Fehlversuche temporär gesperrt.
 Bitte warten Sie 5 Minuten.';
            } elseif (!empty($username) && !empty($password) && isset($users[$username])) {
                if (password_verify($password, $users[$username])) {
                    clear_failed_login_attempts($username);
                    $_SESSION['webtool_logged_in'] = true;
                    $_SESSION['username'] = $username;
                    session_regenerate_id(true);
                    header('Location: index.php');
                    exit;
                } else {
                    handle_failed_login($username);
                    $error = 'Ungültiger Benutzername oder Passwort.';
                }
            } else {
                handle_failed_login($username);
                $error = 'Ungültiger Benutzername oder Passwort.';
            }
        }
    }
    // Session-Variable für die Ladezeit nach der Prüfung zurücksetzen
    unset($_SESSION['login_form_load_time']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $setup_required ?
 'Setup' : 'Login'; ?> - VexilCode Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        html, body { height: 100%;
        }
        body { display: flex; align-items: center; justify-content: center;
        }
        .login-container { max-width: 450px; width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card shadow-lg">
            <div class="card-body p-4 p-md-5">
                <?php if ($setup_required): ?>
                    <h2 class="text-center mb-4"><i class="fas fa-user-plus me-2"></i>Ersten Benutzer anlegen</h2>
                    
                    <?php if (!$config_writable): ?>
                        <div class="alert alert-danger">
                            <strong>Achtung!</strong> Die Datei <code>config/users.php</code> ist nicht beschreibbar.
                            Bitte ändern Sie die Berechtigungen, bevor Sie fortfahren.
                        </div>
                    <?php endif;
 ?>
                    <form method="post" action="login.php" id="setupForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="setup_user" value="1">
                    
                         <div class="form-floating mb-3">
                            <input type="text" name="username" id="username" class="form-control" placeholder="Benutzername" required autofocus <?php if (!$config_writable) echo 'disabled';
 ?>>
                            <label for="username">Benutzername</label>
                        </div>
                        <div class="form-floating mb-3">
                     
                           <input type="password" name="password" id="password" class="form-control" placeholder="Passwort" required <?php if (!$config_writable) echo 'disabled';
 ?>>
                            <label for="password">Passwort</label>
                        </div>
                        <div class="hp-email-field">
                            <label for="email_confirm">Bitte bestätigen Sie Ihre E-Mail</label>
                            <input type="email" name="email_confirm" id="email_confirm" tabindex="-1" autocomplete="off">
                        </div>
     
                         <?php if ($error): ?>
                            <div class="alert alert-danger py-2"><?php echo $error;
 ?></div>
                        <?php endif;
 ?>
                        <button type="submit" class="btn btn-primary w-100 py-2" <?php if (!$config_writable) echo 'disabled';
 ?>>Benutzer anlegen & Einloggen</button>
                    </form>
                <?php else: ?>
                    <h2 class="text-center mb-4"><i class="fas fa-shield-alt me-2"></i>VexilCode Login</h2>
                    <form method="post" action="login.php" id="loginForm">
          
                         <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="login" value="1">
                        <div class="form-floating mb-3">
                           
                             <input type="text" name="username" id="username" class="form-control" placeholder="Benutzername" required autofocus>
                            <label for="username">Benutzername</label>
                        </div>
                        <div class="form-floating mb-3">
             
                             <input type="password" name="password" id="password" class="form-control" placeholder="Passwort" required>
                            <label for="password">Passwort</label>
                        </div>
                        <div class="hp-email-field">
                            <label for="email_confirm">Bitte bestätigen Sie Ihre E-Mail</label>
                            <input type="email" name="email_confirm" id="email_confirm" tabindex="-1" autocomplete="off">
        
                         </div>
                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2"><?php echo $error;
 ?></div>
                        <?php endif;
 ?>
                        <button type="submit" class="btn btn-primary w-100 py-2">Anmelden</button>
                    </form>
                <?php endif;
 ?>
            </div>
        </div>
    </div>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
</body>
</html>