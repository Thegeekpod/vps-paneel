<?php

namespace App\Http\Controllers;

use App\Models\Database;
use App\Services\DatabaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DatabaseController extends Controller
{
    public function __construct(
        protected DatabaseService $databaseService
    ) {}

    public function index(): View
    {
        $databases = Database::with('sites')->latest()->paginate(15);
        $mysqlCount = Database::where('type', 'mysql')->count();
        $postgresCount = Database::where('type', 'postgres')->count();

        return view('databases.index', compact('databases', 'mysqlCount', 'postgresCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:64|unique:databases,name',
            'type' => 'required|in:mysql,postgres',
            'username' => 'nullable|string|max:32',
            'password' => 'nullable|string|min:8|max:64',
        ]);

        $database = $this->databaseService->createDatabase($validated);

        return redirect()->route('databases.index')->with('success', "{$database->type_name} database '{$database->name}' and user created successfully!");
    }

    public function destroy(Database $database): RedirectResponse
    {
        $name = $database->name;
        $this->databaseService->deleteDatabase($database);

        return redirect()->route('databases.index')->with('success', "Database '{$name}' deleted successfully.");
    }

    public function studio(Database $database, Request $request): View
    {
        $allDatabases = Database::orderBy('name')->get();
        $tables = $this->databaseService->getTables($database);

        $activeTable = $request->query('table');
        if (!$activeTable && !empty($tables)) {
            $activeTable = $tables[0]['name'];
        }

        $page = (int)$request->query('page', 1);
        $structure = [];
        $tableData = ['columns' => [], 'rows' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];

        if ($activeTable) {
            $structure = $this->databaseService->getTableStructure($database, $activeTable);
            $tableData = $this->databaseService->getTableRows($database, $activeTable, $page, 25);
        }

        return view('databases.studio', compact('database', 'allDatabases', 'tables', 'activeTable', 'structure', 'tableData'));
    }

    public function query(Database $database, Request $request)
    {
        $validated = $request->validate([
            'sql' => 'required|string',
        ]);

        $result = $this->databaseService->runQuery($database, $validated['sql']);

        return response()->json($result);
    }

    public function createTable(Database $database, Request $request): RedirectResponse
    {
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('table_name'));
        if (!$tableName) {
            return back()->with('error', 'Valid table name is required.');
        }

        $columns = $request->input('columns', []);
        if (empty($columns)) {
            // Default sample table
            if ($database->type === 'postgres') {
                $sql = "CREATE TABLE \"{$tableName}\" (id SERIAL PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);";
            } else {
                $sql = "CREATE TABLE `{$tableName}` (`id` INT AUTO_INCREMENT PRIMARY KEY, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            }
        } else {
            $colDefs = [];
            foreach ($columns as $col) {
                $cName = preg_replace('/[^a-zA-Z0-9_]/', '', $col['name'] ?? '');
                $cType = $col['type'] ?? 'VARCHAR(255)';
                $cNull = !empty($col['nullable']) ? 'NULL' : 'NOT NULL';
                $cPrimary = !empty($col['primary']) ? ($database->type === 'postgres' ? 'PRIMARY KEY' : 'PRIMARY KEY AUTO_INCREMENT') : '';
                
                if ($cName) {
                    $quoted = $database->type === 'postgres' ? "\"{$cName}\"" : "`{$cName}`";
                    $colDefs[] = trim("{$quoted} {$cType} {$cNull} {$cPrimary}");
                }
            }
            if (empty($colDefs)) {
                $colDefs[] = $database->type === 'postgres' ? "id SERIAL PRIMARY KEY" : "`id` INT AUTO_INCREMENT PRIMARY KEY";
            }
            $colSql = implode(', ', $colDefs);
            $quotedTbl = $database->type === 'postgres' ? "\"{$tableName}\"" : "`{$tableName}`";
            $sql = "CREATE TABLE {$quotedTbl} ({$colSql});";
        }

        $result = $this->databaseService->runQuery($database, $sql);

        if ($result['success']) {
            return redirect()->route('databases.studio', [$database, 'table' => $tableName])
                ->with('success', "Table '{$tableName}' created successfully!");
        }

        return back()->with('error', 'Error creating table: ' . ($result['error'] ?? 'Unknown error'));
    }

    public function dropTable(Database $database, Request $request): RedirectResponse
    {
        $table = $request->input('table');
        if (!$table) {
            return back()->with('error', 'No table specified.');
        }

        $quoted = $database->type === 'postgres' ? "\"{$table}\"" : "`{$table}`";
        $sql = "DROP TABLE IF EXISTS {$quoted};";
        $result = $this->databaseService->runQuery($database, $sql);

        if ($result['success']) {
            return redirect()->route('databases.studio', $database)
                ->with('success', "Table '{$table}' dropped successfully.");
        }

        return back()->with('error', 'Error dropping table: ' . ($result['error'] ?? 'Unknown error'));
    }

    public function export(Database $database)
    {
        try {
            $tables = $this->databaseService->getTables($database);
            $pdo = $this->databaseService->getConnection($database);
            $sqlDump = "-- VPS Paneel SQL Dump\n-- Database: {$database->name} ({$database->type})\n-- Exported At: " . now()->toIso8601String() . "\n\n";

            foreach ($tables as $tbl) {
                $tableName = $tbl['name'];
                $quoted = $database->type === 'postgres' ? "\"{$tableName}\"" : "`{$tableName}`";

                $sqlDump .= "-- --------------------------------------------------------\n";
                $sqlDump .= "-- Table structure for {$tableName}\n";
                $sqlDump .= "-- --------------------------------------------------------\n\n";

                if ($database->type === 'mysql') {
                    $createRow = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch();
                    $sqlDump .= ($createRow['Create Table'] ?? '') . ";\n\n";
                }

                // Dump rows
                $rows = $pdo->query("SELECT * FROM {$quoted} LIMIT 5000")->fetchAll();
                if (!empty($rows)) {
                    $sqlDump .= "-- Dumping data for {$tableName}\n";
                    foreach ($rows as $row) {
                        $keys = array_keys($row);
                        $quotedKeys = array_map(fn($k) => $database->type === 'postgres' ? "\"{$k}\"" : "`{$k}`", $keys);
                        $escapedValues = array_map(function($v) use ($pdo) {
                            return is_null($v) ? 'NULL' : $pdo->quote((string)$v);
                        }, array_values($row));

                        $sqlDump .= "INSERT INTO {$quoted} (" . implode(', ', $quotedKeys) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
                    }
                    $sqlDump .= "\n";
                }
            }

            $fileName = "{$database->name}_backup_" . date('Y_m_d_His') . ".sql";

            return response($sqlDump)
                ->header('Content-Type', 'application/sql')
                ->header('Content-Disposition', "attachment; filename=\"{$fileName}\"");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to export database: ' . $e->getMessage());
        }
    }

    public function import(Database $database, Request $request): RedirectResponse
    {
        $request->validate([
            'sql_file' => 'required|file|max:51200', // 50MB max
        ]);

        try {
            $content = file_get_contents($request->file('sql_file')->getRealPath());
            $pdo = $this->databaseService->getConnection($database);
            $pdo->exec($content);

            return back()->with('success', 'SQL file imported successfully!');
        } catch (\Throwable $e) {
            return back()->with('error', 'Import error: ' . $e->getMessage());
        }
    }

    public function manager()
    {
        $adminerPath = public_path('adminer/index.php');
        if (file_exists($adminerPath)) {
            require $adminerPath;
            exit;
        }

        return redirect()->route('databases.index')->with('error', 'Database Manager file not found.');
    }

    public function autologin(Database $database): View
    {
        $driver = $database->type === 'postgres' ? 'pgsql' : 'server';
        $host = '127.0.0.1';
        $username = $database->username;
        $password = $database->password;

        // Ensure database exists in local MySQL if in development
        if ($database->type === 'mysql') {
            try {
                $pdo = new \PDO("mysql:host=127.0.0.1;port=3306", "root", "");
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database->name}`;");
                $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'127.0.0.1' IDENTIFIED BY '{$password}';");
                $pdo->exec("GRANT ALL PRIVILEGES ON `{$database->name}`.* TO '{$username}'@'127.0.0.1';");
                $pdo->exec("FLUSH PRIVILEGES;");
            } catch (\Throwable $e) {}
        }

        // Ensure role and database exist in PostgreSQL
        if ($database->type === 'postgres') {
            try {
                $pdo = new \PDO("pgsql:host=127.0.0.1;port=5432;dbname=postgres", "postgres", "");
                $roleExists = $pdo->query("SELECT 1 FROM pg_roles WHERE rolname = " . $pdo->quote($username))->fetchColumn();
                if (!$roleExists) {
                    $escapedPass = str_replace("'", "''", $password);
                    $pdo->exec("CREATE ROLE \"{$username}\" WITH LOGIN PASSWORD '{$escapedPass}';");
                }
                $dbExists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($database->name))->fetchColumn();
                if (!$dbExists) {
                    $pdo->exec("CREATE DATABASE \"{$database->name}\" OWNER \"{$username}\";");
                }
                $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE \"{$database->name}\" TO \"{$username}\";");
            } catch (\Throwable $e) {}
        }

        return view('databases.autologin', compact('database', 'driver', 'host', 'username', 'password'));
    }
}
