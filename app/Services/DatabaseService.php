<?php

namespace App\Services;

use App\Models\Database;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseService
{
    /**
     * Create a new database & user (MySQL or PostgreSQL).
     */
    public function createDatabase(array $data): Database
    {
        $type = in_array($data['type'] ?? 'mysql', ['mysql', 'postgres']) ? $data['type'] : 'mysql';
        $name = Str::slug($data['name'], '_');
        $username = Str::slug($data['username'] ?? $name, '_');
        $password = $data['password'] ?? Str::password(16);
        $port = $type === 'postgres' ? 5432 : 3306;

        if ($type === 'mysql') {
            $this->createMysqlDatabase($name, $username, $password);
        } else {
            $this->createPostgresDatabase($name, $username, $password);
        }

        return Database::create([
            'name' => $name,
            'type' => $type,
            'username' => $username,
            'password' => $password,
            'host' => $data['host'] ?? '127.0.0.1',
            'port' => $port,
            'charset' => $type === 'postgres' ? 'UTF8' : 'utf8mb4',
            'collation' => $type === 'postgres' ? 'en_US.utf8' : 'utf8mb4_unicode_ci',
            'size_mb' => 0.05,
            'status' => 'active',
        ]);
    }

    private function createMysqlDatabase(string $name, string $username, string $password): void
    {
        // Try direct PDO connection with root or default
        try {
            $pdo = new \PDO("mysql:host=127.0.0.1;port=3306", "root", "");
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'127.0.0.1' IDENTIFIED BY '{$password}';");
            $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'localhost' IDENTIFIED BY '{$password}';");
            $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'%' IDENTIFIED BY '{$password}';");
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$username}'@'127.0.0.1';");
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$username}'@'localhost';");
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$username}'@'%';");
            $pdo->exec("FLUSH PRIVILEGES;");
            return;
        } catch (\Throwable $e) {
            // Fallback to DB facade or CLI
        }

        if (config('database.default') === 'mysql') {
            try {
                DB::statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                DB::statement("CREATE USER IF NOT EXISTS '{$username}'@'127.0.0.1' IDENTIFIED BY '{$password}';");
                DB::statement("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$username}'@'127.0.0.1';");
                DB::statement("FLUSH PRIVILEGES;");
            } catch (\Throwable $e) {
                report($e);
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            @shell_exec("mysql -e \"CREATE DATABASE IF NOT EXISTS \`{$name}\`; CREATE USER IF NOT EXISTS '{$username}'@'127.0.0.1' IDENTIFIED BY '{$password}'; GRANT ALL PRIVILEGES ON \`{$name}\`.* TO '{$username}'@'127.0.0.1'; FLUSH PRIVILEGES;\" 2>&1");
        }
    }

    private function createPostgresDatabase(string $name, string $username, string $password): void
    {
        // 1. Try PDO superuser connection (cross-platform: macOS Herd, Linux, Docker)
        try {
            $pdo = new \PDO("pgsql:host=127.0.0.1;port=5432;dbname=postgres", "postgres", "");
            $roleExists = $pdo->query("SELECT 1 FROM pg_roles WHERE rolname = " . $pdo->quote($username))->fetchColumn();
            if (!$roleExists) {
                $escapedPass = str_replace("'", "''", $password);
                $pdo->exec("CREATE ROLE \"{$username}\" WITH LOGIN PASSWORD '{$escapedPass}';");
            }
            $dbExists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($name))->fetchColumn();
            if (!$dbExists) {
                $pdo->exec("CREATE DATABASE \"{$name}\" OWNER \"{$username}\";");
            }
            $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE \"{$name}\" TO \"{$username}\";");
            return;
        } catch (\Throwable $e) {
            // Continue to CLI fallback
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $escapedPass = escapeshellarg($password);
            @shell_exec("sudo -u postgres psql -c \"CREATE USER \\\"{$username}\\\" WITH ENCRYPTED PASSWORD {$escapedPass};\" 2>&1");
            @shell_exec("sudo -u postgres psql -c \"CREATE DATABASE \\\"{$name}\\\" OWNER \\\"{$username}\\\";\" 2>&1");
            @shell_exec("sudo -u postgres psql -c \"GRANT ALL PRIVILEGES ON DATABASE \\\"{$name}\\\" TO \\\"{$username}\\\";\" 2>&1");
        }
    }

    /**
     * Get a PDO connection for a specific Database model.
     */
    public function getConnection(Database $database): \PDO
    {
        $driver = $database->type === 'postgres' ? 'pgsql' : 'mysql';
        $host = $database->host ?: '127.0.0.1';
        $port = $database->port ?: ($driver === 'pgsql' ? 5432 : 3306);
        $dbname = $database->name;

        // Try connecting with the database's dedicated credentials
        try {
            if ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            }
            $pdo = new \PDO($dsn, $database->username, $database->password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 3,
            ]);
            return $pdo;
        } catch (\Throwable $e) {
            // Development fallback to superuser (root / postgres)
            if ($driver === 'pgsql') {
                $dsn = "pgsql:host=127.0.0.1;port=5432;dbname={$dbname}";
                return new \PDO($dsn, "postgres", "", [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
            } else {
                $dsn = "mysql:host=127.0.0.1;port=3306;dbname={$dbname};charset=utf8mb4";
                return new \PDO($dsn, "root", "", [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
            }
        }
    }

    /**
     * Get all tables in the database with row counts.
     */
    public function getTables(Database $database): array
    {
        try {
            $pdo = $this->getConnection($database);
            $tables = [];

            if ($database->type === 'postgres') {
                $stmt = $pdo->query("
                    SELECT 
                        c.relname as table_name,
                        c.reltuples::bigint as estimated_rows,
                        pg_total_relation_size(c.oid) as total_bytes
                    FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = 'public' 
                      AND c.relkind = 'r'
                    ORDER BY c.relname ASC
                ");
                $rows = $stmt->fetchAll();
                foreach ($rows as $r) {
                    $tables[] = [
                        'name' => $r['table_name'],
                        'rows' => max(0, (int)$r['estimated_rows']),
                        'size' => round(($r['total_bytes'] ?? 0) / 1024, 1) . ' KB',
                    ];
                }
            } else {
                $stmt = $pdo->query("SHOW TABLE STATUS FROM `{$database->name}`");
                $rows = $stmt->fetchAll();
                foreach ($rows as $r) {
                    $sizeBytes = ($r['Data_length'] ?? 0) + ($r['Index_length'] ?? 0);
                    $tables[] = [
                        'name' => $r['Name'],
                        'rows' => (int)($r['Rows'] ?? 0),
                        'engine' => $r['Engine'] ?? 'InnoDB',
                        'size' => round($sizeBytes / 1024, 1) . ' KB',
                    ];
                }
            }

            return $tables;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get column structure of a table.
     */
    public function getTableStructure(Database $database, string $table): array
    {
        try {
            $pdo = $this->getConnection($database);
            $columns = [];

            if ($database->type === 'postgres') {
                $stmt = $pdo->prepare("
                    SELECT 
                        column_name, 
                        data_type, 
                        character_maximum_length,
                        is_nullable, 
                        column_default
                    FROM information_schema.columns 
                    WHERE table_schema = 'public' AND table_name = :tbl
                    ORDER BY ordinal_position ASC
                ");
                $stmt->execute(['tbl' => $table]);
                foreach ($stmt->fetchAll() as $col) {
                    $type = $col['data_type'];
                    if ($col['character_maximum_length']) {
                        $type .= "({$col['character_maximum_length']})";
                    }
                    $columns[] = [
                        'field' => $col['column_name'],
                        'type' => $type,
                        'null' => $col['is_nullable'] === 'YES',
                        'key' => '',
                        'default' => $col['column_default'],
                    ];
                }
            } else {
                $stmt = $pdo->query("DESCRIBE `{$table}`");
                foreach ($stmt->fetchAll() as $col) {
                    $columns[] = [
                        'field' => $col['Field'],
                        'type' => $col['Type'],
                        'null' => $col['Null'] === 'YES',
                        'key' => $col['Key'],
                        'default' => $col['Default'],
                        'extra' => $col['Extra'],
                    ];
                }
            }

            return $columns;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get paginated rows from a table.
     */
    public function getTableRows(Database $database, string $table, int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        try {
            $pdo = $this->getConnection($database);
            $offset = max(0, ($page - 1) * $perPage);

            // Safe identifier quoting
            $quotedTable = $database->type === 'postgres' ? "\"{$table}\"" : "`{$table}`";

            // Count total rows
            $countStmt = $pdo->query("SELECT COUNT(*) FROM {$quotedTable}");
            $total = (int)$countStmt->fetchColumn();

            // Fetch rows
            $query = "SELECT * FROM {$quotedTable} LIMIT {$perPage} OFFSET {$offset}";
            $stmt = $pdo->query($query);
            $rows = $stmt->fetchAll();

            $columns = [];
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
            } else {
                $structure = $this->getTableStructure($database, $table);
                $columns = array_column($structure, 'field');
            }

            return [
                'columns' => $columns,
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ];
        } catch (\Throwable $e) {
            return [
                'columns' => [],
                'rows' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute arbitrary SQL query and return results or rows affected.
     */
    public function runQuery(Database $database, string $sql): array
    {
        $start = microtime(true);
        try {
            $pdo = $this->getConnection($database);
            $trimmed = trim($sql);
            $isSelect = preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $trimmed);

            if ($isSelect) {
                $stmt = $pdo->query($trimmed);
                $rows = $stmt->fetchAll();
                $executionTime = round((microtime(true) - $start) * 1000, 2);
                $columns = !empty($rows) ? array_keys($rows[0]) : [];

                return [
                    'success' => true,
                    'type' => 'select',
                    'columns' => $columns,
                    'rows' => $rows,
                    'count' => count($rows),
                    'time_ms' => $executionTime,
                ];
            } else {
                $affected = $pdo->exec($trimmed);
                $executionTime = round((microtime(true) - $start) * 1000, 2);

                return [
                    'success' => true,
                    'type' => 'exec',
                    'affected' => $affected !== false ? $affected : 0,
                    'time_ms' => $executionTime,
                ];
            }
        } catch (\Throwable $e) {
            $executionTime = round((microtime(true) - $start) * 1000, 2);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'time_ms' => $executionTime,
            ];
        }
    }

    /**
     * Delete a database and drop user schema.
     */
    public function deleteDatabase(Database $database): void
    {
        if ($database->type === 'mysql') {
            try {
                $pdo = new \PDO("mysql:host=127.0.0.1;port=3306", "root", "");
                $pdo->exec("DROP DATABASE IF EXISTS `{$database->name}`;");
                $pdo->exec("DROP USER IF EXISTS '{$database->username}'@'127.0.0.1';");
                $pdo->exec("DROP USER IF EXISTS '{$database->username}'@'localhost';");
            } catch (\Throwable $e) {}

            if (config('database.default') === 'mysql') {
                try {
                    DB::statement("DROP DATABASE IF EXISTS `{$database->name}`;");
                    DB::statement("DROP USER IF EXISTS '{$database->username}'@'localhost';");
                } catch (\Throwable $e) {
                    report($e);
                }
            } elseif (PHP_OS_FAMILY === 'Linux') {
                @shell_exec("mysql -e \"DROP DATABASE IF EXISTS \`{$database->name}\`; DROP USER IF EXISTS '{$database->username}'@'localhost';\" 2>&1");
            }
        } elseif ($database->type === 'postgres') {
            try {
                $pdo = new \PDO("pgsql:host=127.0.0.1;port=5432;dbname=postgres", "postgres", "");
                $pdo->exec("DROP DATABASE IF EXISTS \"{$database->name}\";");
                $pdo->exec("DROP ROLE IF EXISTS \"{$database->username}\";");
            } catch (\Throwable $e) {
                if (PHP_OS_FAMILY === 'Linux') {
                    @shell_exec("sudo -u postgres psql -c \"DROP DATABASE IF EXISTS \\\"{$database->name}\\\";\" 2>&1");
                    @shell_exec("sudo -u postgres psql -c \"DROP USER IF EXISTS \\\"{$database->username}\\\";\" 2>&1");
                }
            }
        }

        $database->delete();
    }
}
