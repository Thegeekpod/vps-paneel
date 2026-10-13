<?php

namespace App\Services;

use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Website;
use Illuminate\Support\Str;

class DatabaseManager
{
    public function __construct(
        protected ServerAgentService $agent
    ) {}

    /**
     * Create a new MariaDB database and optional database user.
     */
    public function createDatabase(string $name, ?Website $website = null, ?string $charset = 'utf8mb4', ?string $collation = 'utf8mb4_unicode_ci'): Database
    {
        $dbName = Str::slug($name, '_');

        $database = Database::create([
            'website_id' => $website?->id,
            'name' => $dbName,
            'charset' => $charset,
            'collation' => $collation,
            'size_mb' => 0,
        ]);

        $this->agent->execute('CreateDatabase', [
            'name' => $dbName,
            'charset' => $charset,
            'collation' => $collation,
        ], 'Database', (string) $database->id);

        return $database;
    }

    /**
     * Create a restricted database user and grant privileges.
     */
    public function createUser(string $username, string $password, ?Database $database = null, string $host = 'localhost'): DatabaseUser
    {
        $cleanUser = Str::slug($username, '_');

        $dbUser = DatabaseUser::firstOrCreate(
            ['username' => $cleanUser],
            ['host' => $host]
        );

        $payload = [
            'username' => $cleanUser,
            'password' => $password,
            'host' => $host,
        ];

        if ($database) {
            $payload['database'] = $database->name;
        }

        $this->agent->execute('CreateDatabaseUser', $payload, 'DatabaseUser', (string) $dbUser->id);

        if ($database) {
            $database->users()->syncWithoutDetaching([$dbUser->id => ['privileges' => 'ALL PRIVILEGES']]);
        }

        return $dbUser;
    }

    /**
     * Drop a database.
     */
    public function dropDatabase(Database $database): void
    {
        $this->agent->execute('DeleteDatabase', [
            'name' => $database->name,
        ], 'Database', (string) $database->id);

        $database->delete();
    }

    /**
     * Drop a database user.
     */
    public function dropUser(DatabaseUser $user): void
    {
        $this->agent->execute('DeleteDatabaseUser', [
            'username' => $user->username,
            'host' => $user->host,
        ], 'DatabaseUser', (string) $user->id);

        $user->delete();
    }
}
