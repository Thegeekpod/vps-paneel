@extends('layouts.app')

@section('title', 'Blank VPS Auto-Installer')
@section('page-title', 'Blank VPS Server Auto-Installer')

@section('content')
<div class="max-w-5xl mx-auto space-y-8">

    <!-- Hero Card -->
    <div class="p-8 rounded-3xl bg-gradient-to-br from-indigo-950/60 via-[#0b101e] to-purple-950/40 border border-indigo-500/20 shadow-2xl relative overflow-hidden">
        <div class="max-w-2xl relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-xs font-semibold uppercase tracking-wider mb-4">
                Automated VPS Provisioner
            </span>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Turn Any Blank Ubuntu VPS into a Full Hosting Server</h1>
            <p class="text-sm text-slate-300 mt-2 leading-relaxed">
                Connect to your fresh Ubuntu 22.04 or 24.04 VPS via SSH and run this single command. It installs Nginx, PHP 8.3, Node.js 22, PM2, MySQL, Composer, Certbot, and launches this panel ready to host your Next.js, Laravel, WordPress, and PHP apps.
            </p>
        </div>

        <!-- One-Click Command Box -->
        <div class="mt-8 space-y-3">
            <div>
                <span class="text-xs font-semibold text-slate-300 mb-1.5 block">Option 1: Direct from GitHub (Recommended on any fresh VPS)</span>
                <div class="p-4 rounded-2xl bg-black/70 border border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="font-mono text-sm text-indigo-300 flex items-center gap-3 overflow-x-auto w-full select-all">
                        <span class="text-pink-400 select-none">$</span>
                        <span id="curl-gh">curl -sSL https://raw.githubusercontent.com/Thegeekpod/vps-paneel/main/scripts/install.sh | sudo bash</span>
                    </div>
                    <button onclick="navigator.clipboard.writeText(document.getElementById('curl-gh').innerText); alert('GitHub installer command copied!');"
                        class="w-full sm:w-auto shrink-0 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                        Copy Command
                    </button>
                </div>
            </div>

            <div>
                <span class="text-xs font-semibold text-slate-400 mb-1.5 block">Option 2: From this portal instance</span>
                <div class="p-3.5 rounded-xl bg-black/40 border border-white/5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <div class="font-mono text-slate-300 overflow-x-auto select-all">
                        <span class="text-slate-500">$</span>
                        <span id="curl-cmd">curl -sSL {{ url('/install.sh') }} | sudo bash</span>
                    </div>
                    <button onclick="navigator.clipboard.writeText(document.getElementById('curl-cmd').innerText); alert('Portal installer command copied!');"
                        class="px-3.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-medium transition shrink-0">
                        Copy
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- What Gets Installed Step-by-Step -->
    <div class="p-8 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl space-y-6">
        <h2 class="text-base font-semibold text-white">What this script provisions automatically:</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Step 1 -->
            <div class="p-5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-xs">01</div>
                <h3 class="text-sm font-semibold text-white">Nginx Web Server</h3>
                <p class="text-xs text-slate-400 leading-snug">Configured with reverse proxy for Next.js and FastCGI for PHP/WordPress/Laravel.</p>
            </div>

            <!-- Step 2 -->
            <div class="p-5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                <div class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 flex items-center justify-center font-bold text-xs">02</div>
                <h3 class="text-sm font-semibold text-white">PHP 8.3 & Composer</h3>
                <p class="text-xs text-slate-400 leading-snug">PHP-FPM, mbstring, gd, curl, mysql, xml, zip, sqlite3, and Composer binary.</p>
            </div>

            <!-- Step 3 -->
            <div class="p-5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-400 flex items-center justify-center font-bold text-xs">03</div>
                <h3 class="text-sm font-semibold text-white">Node.js 22 & PM2</h3>
                <p class="text-xs text-slate-400 leading-snug">Node 22 LTS with PM2 cluster mode daemon for zero-downtime Next.js hosting.</p>
            </div>

            <!-- Step 4 -->
            <div class="p-5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-xs">04</div>
                <h3 class="text-sm font-semibold text-white">MySQL & Certbot</h3>
                <p class="text-xs text-slate-400 leading-snug">MySQL database engine, Let's Encrypt Certbot, and UFW firewall security.</p>
            </div>
        </div>
    </div>

    <!-- Raw Shell Script Viewer -->
    <div class="p-8 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-white">Complete Bash Script (install.sh)</h2>
                <p class="text-xs text-slate-400">Available as raw endpoint at <a href="{{ route('installer.raw') }}" target="_blank" class="text-indigo-400 hover:underline">/install.sh</a></p>
            </div>
            <a href="{{ route('installer.raw') }}" target="_blank" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs text-slate-300 transition">
                View Raw
            </a>
        </div>

        <div class="p-4 rounded-xl bg-black/60 border border-white/5 font-mono text-xs text-slate-300 overflow-x-auto max-h-96 leading-relaxed">
            <pre>{{ $script }}</pre>
        </div>
    </div>

</div>
@endsection
