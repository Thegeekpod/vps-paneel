@extends('layouts.app')

@section('title', 'UFW Firewall')
@section('page-title', 'UFW Network Firewall')

@section('content')
<div class="space-y-6">

    <!-- Top Grid: Add Rule Form (1/3) & Rules Table (2/3) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Add Custom Rule Card & Presets -->
        <div class="space-y-6">
            <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl">
                <h2 class="text-base font-semibold text-white mb-1">Add Firewall Rule</h2>
                <p class="text-xs text-slate-400 mb-6">Manage allowed and blocked ports in UFW.</p>

                <form method="POST" action="{{ route('firewall.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5 font-medium">Rule Name</label>
                        <input type="text" name="name" placeholder="e.g. Next.js App 3000" required
                            class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-300 mb-1.5 font-medium">Port</label>
                            <input type="text" name="port" placeholder="e.g. 3000" required
                                class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                        </div>

                        <div>
                            <label class="block text-xs text-slate-300 mb-1.5 font-medium">Protocol</label>
                            <select name="protocol" class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                <option value="tcp">TCP</option>
                                <option value="udp">UDP</option>
                                <option value="any">Any</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-300 mb-1.5 font-medium">Action</label>
                            <select name="action" class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                <option value="allow">Allow</option>
                                <option value="deny">Deny</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-300 mb-1.5 font-medium">Source IP</label>
                            <input type="text" name="from_ip" value="0.0.0.0/0"
                                class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/20 transition">
                            Add Firewall Rule
                        </button>
                    </div>
                </form>
            </div>

            <!-- Quick Port Presets -->
            <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Quick Presets</h3>
                <div class="space-y-2 text-xs">
                    <form method="POST" action="{{ route('firewall.store') }}">
                        @csrf
                        <input type="hidden" name="name" value="Next.js App Default">
                        <input type="hidden" name="port" value="3000">
                        <input type="hidden" name="protocol" value="tcp">
                        <input type="hidden" name="action" value="allow">
                        <button type="submit" class="w-full flex items-center justify-between p-2.5 rounded-lg bg-white/[0.02] hover:bg-white/5 border border-white/5 text-slate-300 text-left transition">
                            <span>Open Next.js Port (3000)</span>
                            <span class="text-indigo-400 font-mono text-[11px]">+ Add</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('firewall.store') }}">
                        @csrf
                        <input type="hidden" name="name" value="Redis Cache">
                        <input type="hidden" name="port" value="6379">
                        <input type="hidden" name="protocol" value="tcp">
                        <input type="hidden" name="action" value="allow">
                        <button type="submit" class="w-full flex items-center justify-between p-2.5 rounded-lg bg-white/[0.02] hover:bg-white/5 border border-white/5 text-slate-300 text-left transition">
                            <span>Open Redis Port (6379)</span>
                            <span class="text-indigo-400 font-mono text-[11px]">+ Add</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Firewall Rules Table -->
        <div class="lg:col-span-2 p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-semibold text-white">Active UFW Rules</h2>
                    <p class="text-xs text-slate-400">Default policy: Incoming traffic Denied, Outgoing traffic Allowed</p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-medium flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Firewall Active
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-white/5 uppercase text-[10px]">
                            <th class="pb-3 font-semibold">Service / Name</th>
                            <th class="pb-3 font-semibold">Port</th>
                            <th class="pb-3 font-semibold">Protocol</th>
                            <th class="pb-3 font-semibold">Source</th>
                            <th class="pb-3 font-semibold">Action</th>
                            <th class="pb-3 font-semibold text-right">Delete</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($rules as $rule)
                            <tr class="hover:bg-white/[0.02] transition">
                                <td class="py-3 font-medium text-white">
                                    {{ $rule['name'] }}
                                </td>
                                <td class="py-3 font-mono font-bold text-indigo-400">
                                    {{ $rule['port'] }}
                                </td>
                                <td class="py-3 uppercase font-mono text-slate-400">
                                    {{ $rule['protocol'] }}
                                </td>
                                <td class="py-3 font-mono text-slate-400">
                                    {{ $rule['from_ip'] }}
                                </td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] uppercase font-bold {{ $rule['action'] === 'allow' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                        {{ $rule['action'] }}
                                    </span>
                                </td>
                                <td class="py-3 text-right">
                                    <form method="POST" action="{{ route('firewall.destroy', $rule['id']) }}" onsubmit="return confirm('Remove firewall rule for port {{ $rule['port'] }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-[11px] font-medium transition">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection
