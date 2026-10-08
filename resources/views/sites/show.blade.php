@extends('layouts.app')

@section('title', $site->domain . ' - Manage Site')
@section('page-title', 'Site: ' . $site->domain)

@section('content')
<div class="space-y-6" x-data="{ tab: 'overview' }">

    <!-- Breadcrumb & Back Link -->
    <div class="flex items-center justify-between">
        <a href="{{ route('sites.index') }}" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Websites
        </a>

        <div class="flex items-center gap-2">
            <a href="http://{{ $site->domain }}" target="_blank" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs text-slate-300 flex items-center gap-1.5 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                Visit Site
            </a>

            <form method="POST" action="{{ route('sites.restart', $site) }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-medium transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Reload Service
                </button>
            </form>

            <form method="POST" action="{{ route('sites.destroy', $site) }}" onsubmit="return confirm('Are you sure you want to delete this website? This action cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-medium transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Delete
                </button>
            </form>
        </div>
    </div>

    <!-- Header Hero Card -->
    <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2.5 mb-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $site->type_badge_class }}">
                    {{ ucfirst($site->type) }}
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $site->status_badge_class }}">
                    {{ ucfirst($site->status) }}
                </span>
                @if($site->ssl_enabled)
                    <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        SSL Active (Let's Encrypt)
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">{{ $site->domain }}</h1>
            <p class="text-xs text-slate-400 mt-1">App: {{ $site->name }} &bull; Created {{ $site->created_at->toFormattedDateString() }}</p>
        </div>

        <!-- Quick Stats Right Side -->
        <div class="flex items-center gap-4 text-xs font-mono">
            @if($site->type === 'nextjs')
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-center">
                    <span class="text-slate-400 block text-[10px]">Node Version</span>
                    <span class="text-indigo-400 font-bold">Node {{ $site->node_version }}</span>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-center">
                    <span class="text-slate-400 block text-[10px]">Proxy Port</span>
                    <span class="text-indigo-400 font-bold">:{{ $site->port }}</span>
                </div>
            @else
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-center">
                    <span class="text-slate-400 block text-[10px]">PHP Version</span>
                    <span class="text-purple-400 font-bold">PHP {{ $site->php_version }}</span>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-center">
                    <span class="text-slate-400 block text-[10px]">Web Root</span>
                    <span class="text-slate-200">{{ $site->web_directory ?: '/' }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-white/5 pb-2">
        <button @click="tab = 'overview'" :class="tab === 'overview' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'" class="px-4 py-2 rounded-lg text-xs font-semibold transition">
            Overview & Details
        </button>
        <button @click="tab = 'ssl'" :class="tab === 'ssl' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'" class="px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
            SSL Certificate
            @if($site->ssl_enabled)
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            @endif
        </button>
        <button @click="tab = 'nginx'" :class="tab === 'nginx' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'" class="px-4 py-2 rounded-lg text-xs font-semibold transition">
            Nginx Virtual Host
        </button>
        <button @click="tab = 'env'" :class="tab === 'env' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'" class="px-4 py-2 rounded-lg text-xs font-semibold transition">
            Environment (.env)
        </button>
        <button @click="tab = 'logs'" :class="tab === 'logs' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'" class="px-4 py-2 rounded-lg text-xs font-semibold transition">
            Deployment Logs ({{ $taskLogs->count() }})
        </button>
    </div>

    <!-- 1. OVERVIEW TAB -->
    <div x-show="tab === 'overview'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Server Directory & Paths -->
            <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 space-y-4">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider text-slate-400">File System & Paths</h3>
                
                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 block mb-1">Server Root Directory</span>
                        <div class="p-2.5 rounded-lg bg-black/40 border border-white/5 font-mono text-slate-200 select-all">
                            {{ $site->root_path }}
                        </div>
                    </div>

                    <div>
                        <span class="text-slate-400 block mb-1">Public Web Directory</span>
                        <div class="p-2.5 rounded-lg bg-black/40 border border-white/5 font-mono text-slate-200 select-all">
                            {{ $site->root_path }}{{ $site->web_directory }}
                        </div>
                    </div>

                    @if($site->type === 'nextjs')
                        <div>
                            <span class="text-slate-400 block mb-1">PM2 Ecosystem File</span>
                            <div class="p-2.5 rounded-lg bg-black/40 border border-white/5 font-mono text-slate-200 select-all">
                                {{ $site->root_path }}/ecosystem.config.cjs
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Database Information -->
            <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 space-y-4">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider text-slate-400">Database Connection</h3>

                @if($site->database)
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-white/[0.02] border border-white/5">
                            <span class="text-slate-400">Database Name</span>
                            <span class="font-mono text-white font-semibold">{{ $site->database->name }}</span>
                        </div>
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-white/[0.02] border border-white/5">
                            <span class="text-slate-400">Database User</span>
                            <span class="font-mono text-slate-300">{{ $site->database->username }}</span>
                        </div>
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-white/[0.02] border border-white/5">
                            <span class="text-slate-400">Database Host</span>
                            <span class="font-mono text-slate-300">127.0.0.1:3306</span>
                        </div>
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-white/[0.02] border border-white/5">
                            <span class="text-slate-400">Password</span>
                            <span class="font-mono text-slate-300 select-all">{{ $site->database->password }}</span>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8 text-xs text-slate-500">
                        No dedicated database attached to this site.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- 2. SSL TAB -->
    <div x-show="tab === 'ssl'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 space-y-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-white">Let's Encrypt SSL Certificate</h3>
                    <p class="text-xs text-slate-400 mt-1">Automatic issuance and renewal via Certbot and Nginx</p>
                </div>

                <form method="POST" action="{{ route('sites.toggle-ssl', $site) }}">
                    @csrf
                    @if($site->ssl_enabled)
                        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition">
                            Disable SSL
                        </button>
                    @else
                        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-lg shadow-emerald-600/25 transition">
                            Issue Free SSL Certificate
                        </button>
                    @endif
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-white/5 text-xs">
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-slate-400 block mb-1">Status</span>
                    <span class="font-bold {{ $site->ssl_enabled ? 'text-emerald-400' : 'text-slate-400' }}">
                        {{ $site->ssl_enabled ? 'Active (HTTPS Enforced)' : 'Inactive' }}
                    </span>
                </div>
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-slate-400 block mb-1">Issued At</span>
                    <span class="text-slate-200">
                        {{ $site->ssl_issued_at ? $site->ssl_issued_at->toFormattedDateString() : 'N/A' }}
                    </span>
                </div>
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-slate-400 block mb-1">Auto-Renew Status</span>
                    <span class="text-indigo-400">Enabled (Every 60 Days)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. NGINX VHOST TAB -->
    <div x-show="tab === 'nginx'" class="space-y-4">
        <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-white">Active Nginx Virtual Host</h3>
                    <p class="text-xs text-slate-400">Generated configuration in /etc/nginx/sites-available/{{ $site->domain }}.conf</p>
                </div>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('nginx-code').innerText); alert('Nginx config copied to clipboard!');"
                    class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs text-slate-300 transition">
                    Copy Config
                </button>
            </div>

            <div class="p-4 rounded-xl bg-black/60 border border-white/5 font-mono text-xs text-slate-300 overflow-x-auto leading-relaxed">
                <pre id="nginx-code">{{ $nginxConfig }}</pre>
            </div>
        </div>
    </div>

    <!-- 4. ENVIRONMENT (.ENV) TAB -->
    <div x-show="tab === 'env'" class="space-y-4">
        <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5">
            <h3 class="text-sm font-semibold text-white mb-1">Environment Variables (.env)</h3>
            <p class="text-xs text-slate-400 mb-4">Configure your app keys, secrets, database passwords, and runtime flags</p>

            <form method="POST" action="{{ route('sites.save-env', $site) }}">
                @csrf
                <textarea name="env_content" rows="12" class="w-full bg-black/60 border border-white/10 rounded-xl p-4 font-mono text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition leading-relaxed">{{ $envContent }}</textarea>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow transition">
                        Save Environment Variables
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. TASK LOGS TAB -->
    <div x-show="tab === 'logs'" class="space-y-4">
        <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5">
            <h3 class="text-sm font-semibold text-white mb-1">Deployment & Execution Logs</h3>
            <p class="text-xs text-slate-400 mb-4">Real-time command output recorded during provisioning and updates</p>

            @if($taskLogs->isEmpty())
                <div class="text-center py-8 text-xs text-slate-500">No execution logs found for this site.</div>
            @else
                <div class="space-y-4">
                    @foreach($taskLogs as $log)
                        <div class="p-4 rounded-xl bg-black/40 border border-white/5">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-white">{{ $log->title }}</span>
                                <span class="text-[11px] text-slate-500 font-mono">{{ $log->created_at->format('M d, H:i:s') }}</span>
                            </div>
                            <div class="p-3 rounded-lg bg-black/70 font-mono text-[11px] text-emerald-400/90 whitespace-pre-wrap leading-relaxed">
                                {{ $log->output }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
