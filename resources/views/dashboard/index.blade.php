@extends('layouts.app')

@section('title', 'VPS Server Dashboard')
@section('page-title', 'Server Overview & Metrics')

@section('content')
<div class="space-y-6">

    <!-- 1. Real-Time Hardware Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- CPU Card -->
        <div class="p-5 rounded-2xl bg-[#0b101e] border border-white/5 relative overflow-hidden group hover:border-indigo-500/30 transition shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                    CPU Usage
                </span>
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    {{ $metrics['cpu']['cores'] }} Cores
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-white tracking-tight" id="cpu-percent">{{ $metrics['cpu']['percentage'] }}%</span>
                <span class="text-xs text-slate-400">Load: {{ round($metrics['load_average'][0], 2) }}</span>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-800/80 rounded-full h-2 mt-4 overflow-hidden">
                <div id="cpu-bar" class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all duration-500" style="width: {{ $metrics['cpu']['percentage'] }}%"></div>
            </div>
        </div>

        <!-- RAM Memory Card -->
        <div class="p-5 rounded-2xl bg-[#0b101e] border border-white/5 relative overflow-hidden group hover:border-emerald-500/30 transition shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    RAM Memory
                </span>
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20" id="ram-mb">
                    {{ $metrics['memory']['used_mb'] }} / {{ $metrics['memory']['total_mb'] }} MB
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-white tracking-tight" id="ram-percent">{{ $metrics['memory']['percentage'] }}%</span>
                <span class="text-xs text-slate-400">{{ round($metrics['memory']['free_mb'] / 1024, 1) }} GB Free</span>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-800/80 rounded-full h-2 mt-4 overflow-hidden">
                <div id="ram-bar" class="h-2 rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-500" style="width: {{ $metrics['memory']['percentage'] }}%"></div>
            </div>
        </div>

        <!-- Disk Storage Card -->
        <div class="p-5 rounded-2xl bg-[#0b101e] border border-white/5 relative overflow-hidden group hover:border-sky-500/30 transition shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                    NVMe / SSD Disk
                </span>
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/20">
                    {{ $metrics['disk']['used_gb'] }} / {{ $metrics['disk']['total_gb'] }} GB
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-white tracking-tight">{{ $metrics['disk']['percentage'] }}%</span>
                <span class="text-xs text-slate-400">{{ $metrics['disk']['free_gb'] }} GB Free</span>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-800/80 rounded-full h-2 mt-4 overflow-hidden">
                <div class="h-2 rounded-full bg-gradient-to-r from-sky-500 to-blue-500 transition-all duration-500" style="width: {{ $metrics['disk']['percentage'] }}%"></div>
            </div>
        </div>

        <!-- Server Uptime & OS Card -->
        <div class="p-5 rounded-2xl bg-[#0b101e] border border-white/5 relative overflow-hidden group hover:border-amber-500/30 transition shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Server Uptime
                </span>
                <span class="text-xs px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    Active
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-white tracking-tight">{{ $metrics['uptime'] }}</span>
            </div>
            <p class="text-xs text-slate-400 mt-4 truncate" title="{{ $metrics['os'] }}">
                {{ $metrics['os'] }}
            </p>
        </div>
    </div>

    <!-- 2. Application Stack Breakdown Banners -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <!-- Next.js -->
        <a href="{{ route('sites.index', ['type' => 'nextjs']) }}" class="p-4 rounded-xl bg-gradient-to-br from-indigo-950/40 to-[#0c1222] border border-indigo-500/20 hover:border-indigo-500/40 transition flex items-center justify-between group shadow-sm">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
                    <span class="text-xs font-semibold text-slate-300">Next.js Apps</span>
                </div>
                <div class="text-2xl font-bold text-white">{{ $sitesByType['nextjs'] }}</div>
            </div>
            <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
        </a>

        <!-- Laravel -->
        <a href="{{ route('sites.index', ['type' => 'laravel']) }}" class="p-4 rounded-xl bg-gradient-to-br from-red-950/40 to-[#0c1222] border border-red-500/20 hover:border-red-500/40 transition flex items-center justify-between group shadow-sm">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                    <span class="text-xs font-semibold text-slate-300">Laravel Apps</span>
                </div>
                <div class="text-2xl font-bold text-white">{{ $sitesByType['laravel'] }}</div>
            </div>
            <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center text-red-400 group-hover:scale-110 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
        </a>

        <!-- WordPress -->
        <a href="{{ route('sites.index', ['type' => 'wordpress']) }}" class="p-4 rounded-xl bg-gradient-to-br from-sky-950/40 to-[#0c1222] border border-sky-500/20 hover:border-sky-500/40 transition flex items-center justify-between group shadow-sm">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-400"></span>
                    <span class="text-xs font-semibold text-slate-300">WordPress Sites</span>
                </div>
                <div class="text-2xl font-bold text-white">{{ $sitesByType['wordpress'] }}</div>
            </div>
            <div class="w-8 h-8 rounded-lg bg-sky-500/10 flex items-center justify-center text-sky-400 group-hover:scale-110 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
            </div>
        </a>

        <!-- Custom PHP -->
        <a href="{{ route('sites.index', ['type' => 'php']) }}" class="p-4 rounded-xl bg-gradient-to-br from-purple-950/40 to-[#0c1222] border border-purple-500/20 hover:border-purple-500/40 transition flex items-center justify-between group shadow-sm">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                    <span class="text-xs font-semibold text-slate-300">Custom PHP</span>
                </div>
                <div class="text-2xl font-bold text-white">{{ $sitesByType['php'] }}</div>
            </div>
            <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center text-purple-400 group-hover:scale-110 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
            </div>
        </a>
    </div>

    <!-- 3. Server Core Services Status -->
    <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-lg">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            Server Core Services
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($metrics['services'] as $key => $service)
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-300 truncate">{{ $service['name'] }}</span>
                        @if($service['status'])
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" title="Active"></span>
                        @else
                            <span class="w-2 h-2 rounded-full bg-slate-600" title="Inactive"></span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                        <span>{{ $service['port'] ? 'Port ' . $service['port'] : 'Daemon' }}</span>
                        <span class="px-1.5 py-0.2 rounded {{ $service['status'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-700/50 text-slate-400' }}">
                            {{ $service['status'] ? 'Running' : 'Stopped' }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 4. Two-Column Section: Active Sites & Live Task History -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Active Sites Table (2 Columns) -->
        <div class="lg:col-span-2 p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-white">Active Hosted Applications</h2>
                        <p class="text-xs text-slate-400">Next.js, Laravel, WordPress and PHP websites configured on this server</p>
                    </div>
                    <a href="{{ route('sites.index') }}" class="text-xs text-indigo-400 hover:text-indigo-300 font-medium">View All ({{ $sitesCount }}) &rarr;</a>
                </div>

                @if($recentSites->isEmpty())
                    <div class="text-center py-12 border border-dashed border-white/10 rounded-xl">
                        <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <h3 class="text-sm font-medium text-slate-300">No websites deployed yet</h3>
                        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Click the button below to deploy your first Next.js, Laravel, WordPress or PHP site in seconds.</p>
                        <a href="{{ route('sites.create') }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Deploy First Website
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs text-slate-400 border-b border-white/5 uppercase">
                                    <th class="pb-3 font-medium">Website</th>
                                    <th class="pb-3 font-medium">Type</th>
                                    <th class="pb-3 font-medium">SSL</th>
                                    <th class="pb-3 font-medium">Status</th>
                                    <th class="pb-3 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($recentSites as $site)
                                    <tr class="hover:bg-white/[0.02] transition">
                                        <td class="py-3">
                                            <div class="font-medium text-white flex items-center gap-2">
                                                <span>{{ $site->domain }}</span>
                                                @if($site->type === 'nextjs')
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">:{{ $site->port }}</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-slate-400">{{ $site->name }}</div>
                                        </td>
                                        <td class="py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $site->type_badge_class }}">
                                                {{ ucfirst($site->type) }}
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            @if($site->ssl_enabled)
                                                <span class="inline-flex items-center gap-1 text-emerald-400 text-xs">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                                    HTTPS
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-amber-400 text-xs">
                                                    HTTP
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $site->status_badge_class }}">
                                                {{ ucfirst($site->status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-right">
                                            <a href="{{ route('sites.show', $site) }}" class="px-2.5 py-1 rounded bg-white/5 hover:bg-white/10 text-xs text-slate-300 transition">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent Activity / Task Execution Logs (1 Column) -->
        <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-lg flex flex-col justify-between">
            <div>
                <h2 class="text-base font-semibold text-white mb-1">Recent Activity Logs</h2>
                <p class="text-xs text-slate-400 mb-4">Automated deployments, SSL issuances and restarts</p>

                @if($recentTasks->isEmpty())
                    <div class="text-center py-8 text-xs text-slate-500">
                        No activity logs recorded yet.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentTasks as $task)
                            <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-medium text-slate-200 truncate">{{ $task->title }}</span>
                                    <span class="text-[10px] text-slate-500">{{ $task->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-400 text-[11px]">
                                    <span class="capitalize text-indigo-400">{{ $task->type }}</span>
                                    <span class="text-emerald-400 font-mono">{{ $task->execution_time_ms ? $task->execution_time_ms . 'ms' : 'Done' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-4 pt-4 border-t border-white/5">
                <a href="{{ route('installer.index') }}" class="w-full py-2.5 px-3 rounded-lg bg-white/5 hover:bg-white/10 text-xs text-center text-slate-300 font-medium flex items-center justify-center gap-2 transition">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    Install on New Blank VPS
                </a>
            </div>
        </div>
    </div>

</div>

<!-- Real-Time Metrics Poller Script -->
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Poll metrics every 4 seconds
        setInterval(async () => {
            try {
                const res = await fetch("{{ route('api.metrics') }}");
                if (!res.ok) return;
                const data = await res.json();
                
                // Update CPU
                if (data.cpu) {
                    const cpuEl = document.getElementById('cpu-percent');
                    const cpuBar = document.getElementById('cpu-bar');
                    if (cpuEl) cpuEl.innerText = `${data.cpu.percentage}%`;
                    if (cpuBar) cpuBar.style.width = `${data.cpu.percentage}%`;
                }

                // Update RAM
                if (data.memory) {
                    const ramEl = document.getElementById('ram-percent');
                    const ramBar = document.getElementById('ram-bar');
                    const ramMb = document.getElementById('ram-mb');
                    if (ramEl) ramEl.innerText = `${data.memory.percentage}%`;
                    if (ramBar) ramBar.style.width = `${data.memory.percentage}%`;
                    if (ramMb) ramMb.innerText = `${data.memory.used_mb} / ${data.memory.total_mb} MB`;
                }
            } catch (err) {
                // Silently ignore network hiccup
            }
        }, 4000);
    });
</script>
@endpush
@endsection
