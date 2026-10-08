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
        if (config('database.default') === 'mysql') {
            try {
                DB::statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                DB::statement("CREATE USER IF NOT EXISTS '{$username}'@'localhost' IDENTIFIED BY '{$password}';");
                DB::statement("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$username}'@'localhost';");
                DB::statement("FLUSH PRIVILEGES;");
            } catch (\Throwable $e) {
                report($e);
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            @shell_exec("mysql -e \"CREATE DATABASE IF NOT EXISTS \`{$name}\`; CREATE USER IF NOT EXISTS '{$username}'@'localhost' IDENTIFIED BY '{$password}'; GRANT ALL PRIVILEGES ON \`{$name}\`.* TO '{$username}'@'localhost'; FLUSH PRIVILEGES;\" 2>&1");
        }
    }

    private function createPostgresDatabase(string $name, string $username, string $password): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $escapedPass = escapeshellarg($password);
            @shell_exec("sudo -u postgres psql -c \"CREATE USER \\\"{$username}\\\" WITH ENCRYPTED PASSWORD {$escapedPass};\" 2>&1");
            @shell_exec("sudo -u postgres psql -c \"CREATE DATABASE \\\"{$name}\\\" OWNER \\\"{$username}\\\";\" 2>&1");
            @shell_exec("sudo -u postgres psql -c \"GRANT ALL PRIVILEGES ON DATABASE \\\"{$name}\\\" TO \\\"{$username}\\\";\" 2>&1");
        }
    }

    /**
     * Delete a database and drop user schema.
     */
    public function deleteDatabase(Database $database): void
    {
        if ($database->type === 'mysql') {
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
            if (PHP_OS_FAMILY === 'Linux') {
                @shell_exec("sudo -u postgres psql -c \"DROP DATABASE IF EXISTS \\\"{$database->name}\\\";\" 2>&1");
                @shell_exec("sudo -u postgres psql -c \"DROP USER IF EXISTS \\\"{$database->username}\\\";\" 2>&1");
            }
        }

        $database->delete();
    }
}
