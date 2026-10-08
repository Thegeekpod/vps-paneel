<?php

namespace App\Http\Controllers;

use App\Models\Database;
use App\Models\Site;
use App\Models\TaskLog;
use App\Services\ServerMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected ServerMetricsService $metricsService
    ) {}

    public function index(): View
    {
        $metrics = $this->metricsService->getMetrics();
        $sitesCount = Site::count();
        $sitesByType = [
            'nextjs' => Site::where('type', 'nextjs')->count(),
            'laravel' => Site::where('type', 'laravel')->count(),
            'wordpress' => Site::where('type', 'wordpress')->count(),
            'php' => Site::where('type', 'php')->count(),
        ];
        $databasesCount = Database::count();
        $recentSites = Site::latest()->take(5)->get();
        $recentTasks = TaskLog::with('site')->latest()->take(6)->get();

        return view('dashboard.index', compact(
            'metrics',
            'sitesCount',
            'sitesByType',
            'databasesCount',
            'recentSites',
            'recentTasks'
        ));
    }

    public function metrics(): JsonResponse
    {
        return response()->json($this->metricsService->getMetrics());
    }
}
