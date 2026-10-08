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

    public function manager()
    {
        $adminerPath = public_path('adminer/index.php');
        if (file_exists($adminerPath)) {
            require $adminerPath;
            exit;
        }

        return redirect()->route('databases.index')->with('error', 'Database Manager file not found.');
    }
}
