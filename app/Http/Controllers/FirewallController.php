<?php

namespace App\Http\Controllers;

use App\Models\FirewallRule;
use App\Services\FirewallService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FirewallController extends Controller
{
    public function __construct(
        protected FirewallService $firewallService
    ) {}

    public function index(): View
    {
        $rules = $this->firewallService->getRules();
        return view('firewall.index', compact('rules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'port' => 'required|string|max:20',
            'protocol' => 'required|in:tcp,udp,any',
            'action' => 'required|in:allow,deny',
            'from_ip' => 'nullable|string|max:45',
        ]);

        $this->firewallService->addRule($validated);

        return redirect()->route('firewall.index')->with('success', "Firewall rule for port {$validated['port']} added successfully!");
    }

    public function destroy(FirewallRule $rule): RedirectResponse
    {
        $port = $rule->port;
        $this->firewallService->deleteRule($rule);

        return redirect()->route('firewall.index')->with('success', "Firewall rule for port {$port} removed successfully.");
    }
}
