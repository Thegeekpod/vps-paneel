@extends('layouts.app')

@section('title', 'Deploy New Website')
@section('page-title', 'Deploy New Website / Application')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{ 
    type: '{{ old('type', 'nextjs') }}', 
    autoDb: true,
    domain: '{{ old('domain', '') }}'
}">

    <!-- Breadcrumb & Back Link -->
    <div>
        <a href="{{ route('sites.index') }}" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Websites
        </a>
    </div>

    <!-- Main Creation Card -->
    <div class="p-8 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl">
        <div class="mb-8">
            <h2 class="text-xl font-bold text-white tracking-tight">Deploy Website or Application</h2>
            <p class="text-xs text-slate-400 mt-1">Select your stack, set your domain, and the panel will configure Nginx, SSL, runtimes, and databases automatically.</p>
        </div>

        @if($errors->any())
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs mb-6 space-y-1">
                @foreach($errors->all() as $error)
                    <div>• {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('sites.store') }}" class="space-y-8">
            @csrf

            <!-- 1. Select Application Stack -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">1. Select Application Stack</label>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <!-- Next.js Option -->
                    <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition select-none"
                        :class="type === 'nextjs' ? 'border-indigo-500 bg-indigo-500/10 text-white shadow-lg shadow-indigo-500/10' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:border-white/10'">
                        <input type="radio" name="type" value="nextjs" x-model="type" class="sr-only">
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-3 h-3 rounded-full border flex items-center justify-center" :class="type === 'nextjs' ? 'border-indigo-400' : 'border-slate-600'">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400" x-show="type === 'nextjs'"></span>
                            </span>
                            <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300">Node</span>
                        </div>
                        <span class="text-sm font-bold text-white mb-1">Next.js</span>
                        <span class="text-[11px] text-slate-400 leading-snug">React fullstack with PM2 & reverse proxy</span>
                    </label>

                    <!-- Laravel Option -->
                    <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition select-none"
                        :class="type === 'laravel' ? 'border-red-500 bg-red-500/10 text-white shadow-lg shadow-red-500/10' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:border-white/10'">
                        <input type="radio" name="type" value="laravel" x-model="type" class="sr-only">
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-3 h-3 rounded-full border flex items-center justify-center" :class="type === 'laravel' ? 'border-red-400' : 'border-slate-600'">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-400" x-show="type === 'laravel'"></span>
                            </span>
                            <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-red-500/20 text-red-300">PHP 8.3</span>
                        </div>
                        <span class="text-sm font-bold text-white mb-1">Laravel</span>
                        <span class="text-[11px] text-slate-400 leading-snug">Artisan, /public root, Composer & .env</span>
                    </label>

                    <!-- WordPress Option -->
                    <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition select-none"
                        :class="type === 'wordpress' ? 'border-sky-500 bg-sky-500/10 text-white shadow-lg shadow-sky-500/10' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:border-white/10'">
                        <input type="radio" name="type" value="wordpress" x-model="type" class="sr-only">
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-3 h-3 rounded-full border flex items-center justify-center" :class="type === 'wordpress' ? 'border-sky-400' : 'border-slate-600'">
                                <span class="w-1.5 h-1.5 rounded-full bg-sky-400" x-show="type === 'wordpress'"></span>
                            </span>
                            <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-sky-500/20 text-sky-300">CMS</span>
                        </div>
                        <span class="text-sm font-bold text-white mb-1">WordPress</span>
                        <span class="text-[11px] text-slate-400 leading-snug">Auto-DB, wp-config, salts & Nginx rewrites</span>
                    </label>

                    <!-- Custom PHP Option -->
                    <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition select-none"
                        :class="type === 'php' ? 'border-purple-500 bg-purple-500/10 text-white shadow-lg shadow-purple-500/10' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:border-white/10'">
                        <input type="radio" name="type" value="php" x-model="type" class="sr-only">
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-3 h-3 rounded-full border flex items-center justify-center" :class="type === 'php' ? 'border-purple-400' : 'border-slate-600'">
                                <span class="w-1.5 h-1.5 rounded-full bg-purple-400" x-show="type === 'php'"></span>
                            </span>
                            <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300">PHP</span>
                        </div>
                        <span class="text-sm font-bold text-white mb-1">Custom PHP</span>
                        <span class="text-[11px] text-slate-400 leading-snug">Plain PHP scripts with FastCGI Nginx pool</span>
                    </label>
                </div>
            </div>

            <!-- 2. Domain & Application Information -->
            <div class="space-y-4">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">2. Domain & Project Details</label>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">Project / App Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. My Next.js Store" required
                            class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">Primary Domain Name</label>
                        <input type="text" name="domain" x-model="domain" value="{{ old('domain') }}" placeholder="e.g. mystore.com or app.example.com" required
                            class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1.5">Domain Aliases (Optional)</label>
                    <input type="text" name="aliases" value="{{ old('aliases') }}" placeholder="e.g. www.mystore.com extra-domain.com"
                        class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 transition">
                    <p class="text-[11px] text-slate-500 mt-1">Separate multiple aliases with spaces.</p>
                </div>
            </div>

            <!-- 3. Stack-Specific Settings -->
            <!-- Next.js Specific Options -->
            <div x-show="type === 'nextjs'" class="space-y-4 p-5 rounded-xl bg-indigo-950/20 border border-indigo-500/20">
                <div class="flex items-center gap-2 text-indigo-400 text-xs font-semibold uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Next.js Runtimes & Port
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">Node.js Version</label>
                        <select name="node_version" class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="22" selected>Node.js 22 (LTS - Recommended)</option>
                            <option value="20">Node.js 20 (LTS)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">Internal Proxy Port (Optional)</label>
                        <input type="number" name="port" value="{{ old('port') }}" placeholder="Auto-assigned (e.g. 3000, 3001)"
                            class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1.5">Git Repository (Optional)</label>
                    <input type="text" name="git_repository" value="{{ old('git_repository') }}" placeholder="https://github.com/username/next-repo.git"
                        class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>
            </div>

            <!-- WordPress Specific Options -->
            <div x-show="type === 'wordpress'" class="space-y-4 p-5 rounded-xl bg-sky-950/20 border border-sky-500/20">
                <div class="flex items-center gap-2 text-sky-400 text-xs font-semibold uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                    WordPress Automated Setup
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">Admin Username</label>
                        <input type="text" name="wp_admin_user" value="{{ old('wp_admin_user', 'admin') }}"
                            class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-sky-500">
                    </div>

                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">Admin Email</label>
                        <input type="email" name="wp_admin_email" value="{{ old('wp_admin_email', 'admin@example.com') }}"
                            class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-sky-500">
                    </div>
                </div>

                <div class="p-3 rounded-lg bg-sky-500/10 text-sky-300 text-xs flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    A dedicated MySQL database, secure wp-config.php, and random security salts will be generated automatically.
                </div>
            </div>

            <!-- Laravel Specific Options -->
            <div x-show="type === 'laravel'" class="space-y-4 p-5 rounded-xl bg-red-950/20 border border-red-500/20">
                <div class="flex items-center gap-2 text-red-400 text-xs font-semibold uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Laravel Application Settings
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">PHP Version</label>
                        <select name="php_version" class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-red-500">
                            <option value="8.4">PHP 8.4 (Latest)</option>
                            <option value="8.3" selected>PHP 8.3 (Stable)</option>
                            <option value="8.2">PHP 8.2</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">Auto Create MySQL Database</label>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer">
                            <input type="checkbox" name="create_database" value="1" checked class="rounded border-slate-700 text-red-600 focus:ring-0">
                            <span class="text-xs text-slate-300">Create isolated database & populate in .env</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1.5">Git Repository (Optional)</label>
                    <input type="text" name="git_repository" value="{{ old('git_repository') }}" placeholder="https://github.com/username/laravel-app.git"
                        class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-red-500 font-mono">
                </div>
            </div>

            <!-- Custom PHP Options -->
            <div x-show="type === 'php'" class="space-y-4 p-5 rounded-xl bg-purple-950/20 border border-purple-500/20">
                <div class="flex items-center gap-2 text-purple-400 text-xs font-semibold uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    PHP FastCGI Settings
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">PHP Version</label>
                        <select name="php_version" class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500">
                            <option value="8.4">PHP 8.4</option>
                            <option value="8.3" selected>PHP 8.3</option>
                            <option value="8.2">PHP 8.2</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-300 mb-1.5">Create Database</label>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer">
                            <input type="checkbox" name="create_database" value="1" class="rounded border-slate-700 text-purple-600 focus:ring-0">
                            <span class="text-xs text-slate-300">Create MySQL Database for this site</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-white/5 flex items-center justify-end gap-3">
                <a href="{{ route('sites.index') }}" class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-xs font-medium transition">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition transform hover:-translate-y-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Provision & Deploy Site
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
