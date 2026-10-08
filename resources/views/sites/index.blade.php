@extends('layouts.app')

@section('title', 'Websites & Applications')
@section('page-title', 'Websites & Applications')

@section('content')
<div class="space-y-6">

    <!-- Header Filter & Search Toolbar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <!-- Filter Tabs -->
        <div class="flex items-center gap-1.5 p-1 rounded-xl bg-[#0b101e] border border-white/5 w-full sm:w-auto overflow-x-auto">
            <a href="{{ route('sites.index') }}" class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition {{ !request('type') ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                All Sites ({{ \App\Models\Site::count() }})
            </a>
            <a href="{{ route('sites.index', ['type' => 'nextjs']) }}" class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition {{ request('type') === 'nextjs' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                Next.js ({{ \App\Models\Site::where('type', 'nextjs')->count() }})
            </a>
            <a href="{{ route('sites.index', ['type' => 'laravel']) }}" class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition {{ request('type') === 'laravel' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                Laravel ({{ \App\Models\Site::where('type', 'laravel')->count() }})
            </a>
            <a href="{{ route('sites.index', ['type' => 'wordpress']) }}" class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition {{ request('type') === 'wordpress' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                WordPress ({{ \App\Models\Site::where('type', 'wordpress')->count() }})
            </a>
            <a href="{{ route('sites.index', ['type' => 'php']) }}" class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition {{ request('type') === 'php' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                PHP ({{ \App\Models\Site::where('type', 'php')->count() }})
            </a>
        </div>

        <!-- Search Bar -->
        <form method="GET" action="{{ route('sites.index') }}" class="relative w-full sm:w-64">
            @if(request('type'))
                <input type="hidden" name="type" value="{{ request('type') }}">
            @endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search domain or app..." class="w-full bg-[#0b101e] border border-white/10 rounded-xl px-4 py-2 pl-9 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
            <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </form>
    </div>

    <!-- Sites Grid -->
    @if($sites->isEmpty())
        <div class="p-12 rounded-2xl bg-[#0b101e] border border-white/5 text-center">
            <div class="w-16 h-16 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
            </div>
            <h3 class="text-base font-semibold text-white">No websites found</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">There are no websites matching your current filter. You can deploy a new Next.js, Laravel, WordPress or PHP site right now.</p>
            <a href="{{ route('sites.create') }}" class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Deploy New Website
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($sites as $site)
                <div class="p-5 rounded-2xl bg-[#0b101e] border border-white/5 hover:border-white/10 transition shadow-lg flex flex-col justify-between group">
                    <div>
                        <!-- Header with Type Badge & SSL Status -->
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $site->type_badge_class }}">
                                {{ ucfirst($site->type) }}
                            </span>
                            @if($site->ssl_enabled)
                                <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    SSL Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                                    HTTP (No SSL)
                                </span>
                            @endif
                        </div>

                        <!-- Domain Title -->
                        <h3 class="text-base font-semibold text-white group-hover:text-indigo-400 transition flex items-center gap-2">
                            <a href="{{ route('sites.show', $site) }}">{{ $site->domain }}</a>
                            <a href="http://{{ $site->domain }}" target="_blank" class="text-slate-500 hover:text-slate-300 transition" title="Open website in new tab">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $site->name }}</p>

                        <!-- Tech Metadata Grid -->
                        <div class="mt-4 pt-3 border-t border-white/5 space-y-2 text-xs">
                            <div class="flex items-center justify-between text-slate-400">
                                <span>Directory</span>
                                <span class="font-mono text-slate-300 truncate max-w-[180px]" title="{{ $site->root_path }}">{{ basename($site->root_path) }}</span>
                            </div>
                            @if($site->type === 'nextjs')
                                <div class="flex items-center justify-between text-slate-400">
                                    <span>Proxy Port / Node</span>
                                    <span class="font-mono text-indigo-400">:{{ $site->port }} (Node {{ $site->node_version }})</span>
                                </div>
                            @else
                                <div class="flex items-center justify-between text-slate-400">
                                    <span>PHP Version</span>
                                    <span class="font-mono text-purple-400">PHP {{ $site->php_version }}</span>
                                </div>
                            @endif
                            @if($site->database)
                                <div class="flex items-center justify-between text-slate-400">
                                    <span>Database</span>
                                    <span class="font-mono text-sky-400 truncate max-w-[160px]">{{ $site->database->name }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Action Footer -->
                    <div class="mt-5 pt-3 border-t border-white/5 flex items-center justify-between gap-2">
                        <form method="POST" action="{{ route('sites.restart', $site) }}">
                            @csrf
                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-medium transition flex items-center gap-1.5" title="Restart Service / Reload Vhost">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Restart
                            </button>
                        </form>

                        <a href="{{ route('sites.show', $site) }}" class="px-3.5 py-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-medium transition">
                            Manage Site &rarr;
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $sites->links() }}
        </div>
    @endif

</div>
@endsection
