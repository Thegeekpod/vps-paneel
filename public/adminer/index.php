<?php

chdir(__DIR__);

// Check if Laravel framework is available to read database credentials
if (!class_exists('Illuminate\Foundation\Application') && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $app = require_once __DIR__ . '/../../bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
}

function adminer_object() {
    class AdminerCustom extends \Adminer\Adminer {
        function name() {
            return '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;">'
                . '<span style="font-weight:800;font-size:15px;letter-spacing:-0.02em;background:linear-gradient(135deg,#818cf8,#c084fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">VPS Paneel</span>'
                . '<span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;background:rgba(99,102,241,0.2);color:#a5b4fc;padding:2px 7px;border-radius:6px;border:1px solid rgba(99,102,241,0.3);">DB Manager</span>'
                . '</div>';
        }

        // Return modern dark theme CSS stylesheet
        function css() {
            return [
                '/adminer/theme.css?v=' . (file_exists(__DIR__ . '/theme.css') ? filemtime(__DIR__ . '/theme.css') : '1') => 'dark',
            ];
        }

        // Inject Google fonts and stylesheet in head
        function head($pc = null) {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
            echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">' . "\n";
            echo '<link rel="stylesheet" href="/adminer/theme.css?v=' . (file_exists(__DIR__ . '/theme.css') ? filemtime(__DIR__ . '/theme.css') : '1') . '">' . "\n";
            return true;
        }

        // Relax CSP to allow external fonts and custom styling
        function csp(array $kc) {
            return [];
        }

        // Disable CSRF token requirement for external one-click auto-login
        function verifyLoginToken() {
            return false;
        }

        function login($login, $password) {
            return true;
        }

        function navigation($missing) {
            echo '<div style="margin-bottom:14px;">';
            echo '<a href="/databases" style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:rgba(99,102,241,0.12);color:#c7d2fe;font-size:12px;font-weight:600;border-radius:8px;border:1px solid rgba(99,102,241,0.25);text-decoration:none;transition:all 0.2s ease;">';
            echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>';
            echo 'Back to VPS Panel';
            echo '</a>';
            echo '</div>';
            return parent::navigation($missing);
        }

        function loginFormField($fieldName, $heading, $fieldHtml) {
            $dbName = $_GET['db'] ?? $_POST['auth']['db'] ?? '';
            $user = $_GET['username'] ?? $_POST['auth']['username'] ?? '';
            $password = '';
            $isPgsql = isset($_GET['pgsql']) || (isset($_POST['auth']['driver']) && $_POST['auth']['driver'] === 'pgsql');

            if (class_exists(\App\Models\Database::class) && ($dbName || $user)) {
                $db = \App\Models\Database::where('name', $dbName)
                    ->orWhere('username', $user)
                    ->first();
                if ($db) {
                    $password = $db->password;
                    if ($db->type === 'postgres') {
                        $isPgsql = true;
                    }
                }
            }

            // Driver selection: automatically pick PostgreSQL or MySQL
            if ($fieldName === 'driver') {
                if ($isPgsql) {
                    return $heading . '<select name="auth[driver]"><option value="pgsql" selected>PostgreSQL</option><option value="server">MySQL / MariaDB</option></select>' . "\n";
                }
                return $heading . '<select name="auth[driver]"><option value="server" selected>MySQL / MariaDB</option><option value="pgsql">PostgreSQL</option></select>' . "\n";
            }

            // Server: always ensure TCP 127.0.0.1 instead of localhost UNIX socket
            if ($fieldName === 'server') {
                $server = $_GET['server'] ?? $_GET['pgsql'] ?? $_POST['auth']['server'] ?? '127.0.0.1';
                return $heading . '<input name="auth[server]" value="' . htmlspecialchars($server ?: '127.0.0.1') . '" title="127.0.0.1" autocapitalize="off">' . "\n";
            }

            // Password: pre-fill with database password
            if ($fieldName === 'password') {
                return $heading . '<input type="password" name="auth[password]" value="' . htmlspecialchars($password) . '" autocomplete="current-password">' . "\n";
            }

            return parent::loginFormField($fieldName, $heading, $fieldHtml);
        }

        function loginForm() {
            parent::loginForm();

            // If username and database are present, trigger seamless auto-login
            $dbName = $_GET['db'] ?? '';
            $user = $_GET['username'] ?? '';
            if ($dbName && $user) {
                echo '<script>
                    setTimeout(function() {
                        var form = document.querySelector("form");
                        if (form && !form.dataset.submitted) {
                            form.dataset.submitted = "true";
                            form.submit();
                        }
                    }, 40);
                </script>';
            }
        }
    }

    return new AdminerCustom;
}

include __DIR__ . '/adminer-core.php';
