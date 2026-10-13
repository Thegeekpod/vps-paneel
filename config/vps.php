<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server Agent Configuration
    |--------------------------------------------------------------------------
    |
    | The privileged server agent is executed via sudo with a strict allowlist
    | of operations. Arbitrary shell commands are strictly forbidden.
    |
    */
    'agent_path' => env('VPS_AGENT_PATH', file_exists('/usr/local/bin/vps-agent') ? '/usr/local/bin/vps-agent' : base_path('bin/vps-agent')),
    'use_sudo' => env('VPS_AGENT_USE_SUDO', PHP_OS_FAMILY === 'Linux'),
    'simulate' => env('VPS_AGENT_SIMULATE', PHP_OS_FAMILY !== 'Linux'),

    /*
    |--------------------------------------------------------------------------
    | Paths & System Directories
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'base_web_root' => env('VPS_WEB_ROOT', '/home'),
        'nginx_sites_available' => env('VPS_NGINX_AVAILABLE', '/etc/nginx/sites-available'),
        'nginx_sites_enabled' => env('VPS_NGINX_ENABLED', '/etc/nginx/sites-enabled'),
        'php_fpm_pool_dir' => env('VPS_PHP_POOL_DIR', '/etc/php/:version/fpm/pool.d'),
        'ssl_certs_dir' => env('VPS_SSL_CERTS_DIR', '/etc/letsencrypt/live'),
        'vmail_base_dir' => env('VPS_VMAIL_DIR', '/var/vmail'),
        'restic_repo_dir' => env('VPS_RESTIC_REPO', '/var/backups/restic'),
        'restic_password_file' => env('VPS_RESTIC_PW_FILE', '/etc/vps-panel/restic-password'),
        'agent_log' => env('VPS_AGENT_LOG', '/var/log/vps-panel/agent.log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported PHP Versions
    |--------------------------------------------------------------------------
    */
    'supported_php_versions' => ['8.2', '8.3', '8.4'],
    'default_php_version' => env('VPS_DEFAULT_PHP_VERSION', '8.3'),

    /*
    |--------------------------------------------------------------------------
    | Managed System Services
    |--------------------------------------------------------------------------
    */
    'services' => [
        'nginx' => 'Nginx Web Server',
        'mariadb' => 'MariaDB Database Server',
        'php8.3-fpm' => 'PHP 8.3 FPM',
        'php8.4-fpm' => 'PHP 8.4 FPM',
        'pdns' => 'PowerDNS Authoritative Server',
        'postfix' => 'Postfix Mail Transfer Agent',
        'dovecot' => 'Dovecot IMAP/POP3 Server',
        'ufw' => 'Uncomplicated Firewall',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved System Usernames (cannot be assigned to hosted websites)
    |--------------------------------------------------------------------------
    */
    'reserved_usernames' => [
        'root', 'bin', 'daemon', 'sys', 'adm', 'tty', 'disk', 'lp', 'mail',
        'news', 'uucp', 'man', 'proxy', 'kmem', 'dialout', 'fax', 'voice',
        'cdrom', 'floppy', 'tape', 'sudo', 'audio', 'dip', 'www-data', 'backup',
        'operator', 'list', 'irc', 'src', 'gnats', 'shadow', 'utmp', 'video',
        'sasl', 'plugdev', 'staff', 'games', 'users', 'nogroup', 'systemd',
        'messagebus', 'ssh', 'sshd', 'vpspanel', 'vmail', 'dovecot', 'dovenull',
        'postfix', 'mysql', 'mariadb', 'pdns', 'admin', 'administrator',
    ],
];
