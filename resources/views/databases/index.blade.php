@extends('layouts.app')

@section('title', 'Databases & phpMyAdmin')
@section('page-title', 'Database Management (MySQL & PostgreSQL)')

@section('content')
<div class="space-y-6" x-data="{ dbType: 'mysql' }">

    <!-- Top Action & Manager Launch Banner -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-indigo-950/60 via-[#0b101e] to-sky-950/40 border border-white/5 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-xs font-semibold uppercase tracking-wider">
                    Web Database Administration
                </span>
            </div>
            <h2 class="text-xl font-bold text-white tracking-tight">phpMyAdmin & Database Web GUI</h2>
            <p class="text-xs text-slate-300 mt-1 max-w-xl">
                Manage your MySQL tables with phpMyAdmin, or use the integrated Web Database Manager to administer both MySQL and PostgreSQL databases right from your browser.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Open phpMyAdmin -->
            <a href="/phpmyadmin" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 text-xs font-semibold shadow-lg shadow-amber-500/10 transition group">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                <span>Launch phpMyAdmin</span>
            </a>

            <!-- Open Integrated Database Manager (Adminer) -->
            <a href="{{ route('databases.manager') }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/25 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                <span>All-in-One DB Manager (Adminer)</span>
            </a>
        </div>
    </div>

    <!-- Engine Counter Chips -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="p-4 rounded-xl bg-[#0b101e] border border-amber-500/20 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold text-sm">
                    🐬
                </div>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">MySQL / MariaDB</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Port 3306 &bull; Supported by phpMyAdmin</p>
                </div>
            </div>
            <div class="text-2xl font-bold text-white">{{ $mysqlCount }} <span class="text-xs font-normal text-slate-400">DBs</span></div>
        </div>

        <div class="p-4 rounded-xl bg-[#0b101e] border border-sky-500/20 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-sky-500/10 text-sky-400 flex items-center justify-center font-bold text-sm">
                    🐘
                </div>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">PostgreSQL</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Port 5432 &bull; Supported by Adminer & pgAdmin</p>
                </div>
            </div>
            <div class="text-2xl font-bold text-white">{{ $postgresCount }} <span class="text-xs font-normal text-slate-400">DBs</span></div>
        </div>
    </div>

    <!-- Main Content Grid: Create Form & List Table -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Create Database Card -->
        <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl h-fit space-y-5">
            <div>
                <h2 class="text-base font-semibold text-white">Create New Database</h2>
                <p class="text-xs text-slate-400 mt-0.5">Choose your database engine and specify user credentials.</p>
            </div>

            <form method="POST" action="{{ route('databases.store') }}" class="space-y-4">
                @csrf

                <!-- Database Engine Switcher -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Database Engine</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center gap-2 p-2.5 rounded-xl border cursor-pointer transition select-none text-xs font-medium"
                            :class="dbType === 'mysql' ? 'border-amber-500/50 bg-amber-500/10 text-amber-300' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:border-white/10'">
                            <input type="radio" name="type" value="mysql" x-model="dbType" class="sr-only">
                            <span>🐬 MySQL</span>
                        </label>
                        <label class="flex items-center justify-center gap-2 p-2.5 rounded-xl border cursor-pointer transition select-none text-xs font-medium"
                            :class="dbType === 'postgres' ? 'border-sky-500/50 bg-sky-500/10 text-sky-300' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:border-white/10'">
                            <input type="radio" name="type" value="postgres" x-model="dbType" class="sr-only">
                            <span>🐘 PostgreSQL</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1.5 font-medium">Database Name</label>
                    <input type="text" name="name" placeholder="e.g. app_production" required
                        class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono transition">
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1.5 font-medium">Username (Optional)</label>
                    <input type="text" name="username" placeholder="Leave blank to use database name"
                        class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono transition">
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1.5 font-medium">Password (Optional)</label>
                    <input type="text" name="password" placeholder="Leave blank to auto-generate"
                        class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono transition">
                </div>

                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-[11px] text-slate-400">
                    <span class="block text-slate-300 font-medium mb-0.5">Connection Port</span>
                    <span x-text="dbType === 'mysql' ? 'Default 127.0.0.1:3306 (MySQL)' : 'Default 127.0.0.1:5432 (PostgreSQL)'"></span>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/25 transition">
                        Create Database & User
                    </button>
                </div>
            </form>
        </div>

        <!-- Databases List Table -->
        <div class="lg:col-span-2 p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-semibold text-white">Active Databases</h2>
                    <p class="text-xs text-slate-400">Total {{ $databases->total() }} databases configured across MySQL and PostgreSQL</p>
                </div>
            </div>

            @if($databases->isEmpty())
                <div class="text-center py-12 border border-dashed border-white/10 rounded-xl">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                    <h3 class="text-sm font-medium text-slate-300">No databases found</h3>
                    <p class="text-xs text-slate-500 mt-1">Create your first MySQL or PostgreSQL database using the form on the left.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-white/5 uppercase text-[10px]">
                                <th class="pb-3 font-semibold">Engine</th>
                                <th class="pb-3 font-semibold">Database Name</th>
                                <th class="pb-3 font-semibold">User</th>
                                <th class="pb-3 font-semibold">Port</th>
                                <th class="pb-3 font-semibold">Attached App</th>
                                <th class="pb-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($databases as $db)
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $db->type_badge_class }}">
                                            {{ $db->type === 'postgres' ? 'PostgreSQL' : 'MySQL' }}
                                        </span>
                                    </td>
                                    <td class="py-3 font-mono font-bold text-white">
                                        {{ $db->name }}
                                    </td>
                                    <td class="py-3 font-mono text-slate-300">
                                        {{ $db->username }}
                                    </td>
                                    <td class="py-3 font-mono text-slate-400">
                                        :{{ $db->port }}
                                    </td>
                                    <td class="py-3 text-slate-400">
                                        @if($db->sites->isNotEmpty())
                                            @foreach($db->sites as $s)
                                                <a href="{{ route('sites.show', $s) }}" class="text-indigo-400 hover:underline block truncate max-w-[130px]">{{ $s->domain }}</a>
                                            @endforeach
                                        @else
                                            <span class="text-slate-600">None</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('databases.manager') }}?username={{ $db->username }}&db={{ $db->name }}&server=127.0.0.1&{{ $db->type === 'postgres' ? 'pgsql=' : 'server=' }}" target="_blank"
                                                class="px-2.5 py-1 rounded bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-300 text-[11px] font-medium transition" title="Open in DB Manager">
                                                Manage
                                            </a>

                                            <form method="POST" action="{{ route('databases.destroy', $db) }}" onsubmit="return confirm('Are you sure you want to drop database {{ $db->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-2 py-1 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-[11px] font-medium transition">
                                                    Drop
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $databases->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
