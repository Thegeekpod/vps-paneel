<?php

namespace App\Services;

use App\Models\Database;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseService
{
    /**
     * Create a new MySQL database & user.
     */
    public function createDatabase(array $data): Database
    {
        $name = Str::slug($data['name'], '_');
        $username = Str::slug($data['username'] ?? $name, '_');
        $password = $data['password'] ?? Str::password(16);

        // If running in production with MySQL connection
        if (config('database.default') === 'mysql') {
            try {
                DB::statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                DB::statement("CREATE USER IF NOT EXISTS '{$username}'@'localhost' IDENTIFIED BY '{$password}';");
                DB::statement("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$username}'@'localhost';");
                DB::statement("FLUSH PRIVILEGES;");
            } catch (\Throwable $e) {
                // Log and continue if already exists or permission issues
                report($e);
            }
        }

        return Database::create([
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'host' => $data['host'] ?? '127.0.0.1',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'size_mb' => 0.05,
            'status' => 'active',
        ]);
    }

    /**
     * Delete a database and drop MySQL schema.
     */
    public function deleteDatabase(Database $database): void
    {
        if (config('database.default') === 'mysql') {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$database->name}`;");
                DB::statement("DROP USER IF EXISTS '{$database->username}'@'localhost';");
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $database->delete();
    }
}
