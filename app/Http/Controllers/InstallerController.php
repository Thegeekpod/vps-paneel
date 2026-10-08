<?php

namespace App\Http\Controllers;

use App\Services\InstallerScriptService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InstallerController extends Controller
{
    public function __construct(
        protected InstallerScriptService $installerService
    ) {}

    public function index(): View
    {
        $script = $this->installerService->generateBashScript();
        return view('installer.index', compact('script'));
    }

    public function rawScript(): Response
    {
        $script = $this->installerService->generateBashScript();
        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
