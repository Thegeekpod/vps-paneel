@extends('layouts.app')

@section('title', $database->name . ' - Database Studio')
@section('page-title', 'Database Studio: ' . $database->name)

@section('content')
<div class="space-y-6" x-data="databaseStudio({
    dbId: {{ $database->id }},
    activeTab: '{{ $activeTable ? 'browse' : 'query' }}',
    activeTable: '{{ $activeTable ?? '' }}',
    querySql: '{{ $activeTable ? "SELECT * FROM " . ($database->type === 'postgres' ? "\\\"$activeTable\\\"" : "`$activeTable`") . " LIMIT 50;" : "SELECT 1;" }}'
})">

    <!-- Top Database Header & Quick Switcher -->
    <div class="p-5 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl {{ $database->type === 'postgres' ? 'bg-sky-500/10 text-sky-400 border border-sky-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }} flex items-center justify-center text-2xl shadow-inner shrink-0">
                {{ $database->type === 'postgres' ? '🐘' : '🐬' }}
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold text-white tracking-tight font-mono">{{ $database->name }}</h1>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wider {{ $database->type === 'postgres' ? 'bg-sky-500/15 text-sky-400 border border-sky-500/30' : 'bg-amber-500/15 text-amber-400 border border-amber-500/30' }}">
                        {{ $database->type_name }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-400 text-xs border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Connected
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1 flex items-center gap-2">
                    <span>Host: <strong class="text-slate-300 font-mono">{{ $database->host }}:{{ $database->port }}</strong></span>
                    <span>&bull;</span>
                    <span>User: <strong class="text-slate-300 font-mono">{{ $database->username }}</strong></span>
                    <span>&bull;</span>
                    <span>{{ count($tables) }} Tables</span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <!-- Database Switcher Dropdown -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 text-xs font-semibold flex items-center gap-2 transition">
                    <span>Switch DB</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-56 rounded-xl bg-[#0d1527] border border-white/10 shadow-2xl py-1.5 z-50">
                    <div class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Select Database</div>
                    @foreach($allDatabases as $dbOption)
                        <a href="{{ route('databases.studio', $dbOption) }}" class="flex items-center justify-between px-3 py-2 text-xs text-slate-200 hover:bg-indigo-600/20 hover:text-white transition {{ $dbOption->id === $database->id ? 'bg-indigo-600/15 text-indigo-300 font-bold' : '' }}">
                            <span class="flex items-center gap-2 font-mono">
                                <span>{{ $dbOption->type === 'postgres' ? '🐘' : '🐬' }}</span>
                                {{ $dbOption->name }}
                            </span>
                            <span class="text-[10px] text-slate-400 uppercase">{{ $dbOption->type }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Export SQL Dump -->
            <a href="{{ route('databases.studio.export', $database) }}" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/10 text-xs font-medium flex items-center gap-1.5 transition" title="Export Full SQL Dump">
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export</span>
            </a>

            <!-- Open Adminer Direct Tool -->
            <a href="{{ route('databases.autologin', $database) }}" target="_blank" class="px-3 py-2 rounded-xl bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 border border-purple-500/30 text-xs font-medium flex items-center gap-1.5 transition" title="Open in Classic Web Manager">
                <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span>Classic GUI</span>
            </a>

            <!-- Back to Databases Index -->
            <a href="{{ route('databases.index') }}" class="px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold flex items-center gap-1.5 transition shadow-md shadow-indigo-600/20">
                <span>&larr; Back to Databases</span>
            </a>
        </div>
    </div>

    <!-- Main Dual-Pane Studio -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Left Column: Tables Explorer (3 cols) -->
        <div class="lg:col-span-3 bg-[#0b101e] border border-white/5 rounded-2xl p-4 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    Tables ({{ count($tables) }})
                </h3>
                <button @click="$dispatch('open-create-table-modal')" class="p-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 transition text-xs flex items-center gap-1 font-semibold" title="Create New Table">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Table</span>
                </button>
            </div>

            <!-- Table Search -->
            <div class="relative">
                <input type="text" x-model="searchTable" placeholder="Search tables..." class="w-full bg-[#070b14] border border-white/10 rounded-xl px-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                <svg class="w-3.5 h-3.5 text-slate-500 absolute right-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Tables List -->
            <div class="space-y-1 max-h-[580px] overflow-y-auto pr-1 custom-scrollbar">
                @forelse($tables as $tbl)
                    <div x-show="!searchTable || '{{ strtolower($tbl['name']) }}'.includes(searchTable.toLowerCase())">
                        <a href="{{ route('databases.studio', [$database, 'table' => $tbl['name']]) }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-mono transition group {{ $activeTable === $tbl['name'] ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white border border-transparent' }}">
                            <div class="flex items-center gap-2 truncate">
                                <svg class="w-3.5 h-3.5 {{ $activeTable === $tbl['name'] ? 'text-indigo-400' : 'text-slate-500 group-hover:text-slate-300' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span class="truncate">{{ $tbl['name'] }}</span>
                            </div>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-white/5 text-slate-400 border border-white/5 shrink-0">
                                {{ number_format($tbl['rows']) }}
                            </span>
                        </a>
                    </div>
                @empty
                    <div class="text-center py-8 px-4 bg-[#070b14]/50 rounded-xl border border-white/5">
                        <div class="text-2xl mb-1">📦</div>
                        <p class="text-xs text-slate-400 font-medium">No tables yet</p>
                        <p class="text-[11px] text-slate-500 mt-1">Create your first table or run a SQL statement.</p>
                        <button @click="$dispatch('open-create-table-modal')" class="mt-3 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-500 transition shadow-sm">
                            + Create Table
                        </button>
                    </div>
                @endforelse
            </div>

            <!-- Quick SQL shortcuts -->
            <div class="pt-3 border-t border-white/5">
                <button @click="activeTab = 'query'" class="w-full px-3 py-2 rounded-xl bg-gradient-to-r from-indigo-600/15 to-purple-600/15 hover:from-indigo-600/25 hover:to-purple-600/25 text-indigo-300 border border-indigo-500/20 text-xs font-semibold flex items-center justify-center gap-2 transition">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Open SQL Runner</span>
                </button>
            </div>
        </div>

        <!-- Right Column: Main Studio Tabs (9 cols) -->
        <div class="lg:col-span-9 space-y-4">

            <!-- Navigation Tabs Bar -->
            <div class="p-1.5 rounded-xl bg-[#0b101e] border border-white/5 flex flex-wrap items-center justify-between gap-2 shadow-md">
                <div class="flex items-center gap-1">
                    @if($activeTable)
                        <button @click="activeTab = 'browse'"
                                :class="activeTab === 'browse' ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/25' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'"
                                class="px-4 py-2 rounded-lg text-xs font-medium flex items-center gap-2 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            <span>Browse Data</span>
                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-black/25 text-indigo-200 font-mono">{{ number_format($tableData['total'] ?? 0) }}</span>
                        </button>

                        <button @click="activeTab = 'structure'"
                                :class="activeTab === 'structure' ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/25' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'"
                                class="px-4 py-2 rounded-lg text-xs font-medium flex items-center gap-2 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Schema Structure</span>
                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-black/25 text-indigo-200 font-mono">{{ count($structure) }} cols</span>
                        </button>
                    @endif

                    <button @click="activeTab = 'query'"
                            :class="activeTab === 'query' ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/25' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'"
                            class="px-4 py-2 rounded-lg text-xs font-medium flex items-center gap-2 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        <span>SQL Query Runner</span>
                    </button>

                    <button @click="activeTab = 'import_export'"
                            :class="activeTab === 'import_export' ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/25' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'"
                            class="px-4 py-2 rounded-lg text-xs font-medium flex items-center gap-2 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span>Import / Export</span>
                    </button>
                </div>

                @if($activeTable)
                    <div class="flex items-center gap-2 pr-2">
                        <span class="text-xs text-slate-400 font-mono">Table: <strong class="text-indigo-400">{{ $activeTable }}</strong></span>
                        <form method="POST" action="{{ route('databases.studio.drop-table', $database) }}" onsubmit="return confirm('Are you sure you want to drop table {{ $activeTable }}? This cannot be undone!');">
                            @csrf
                            <input type="hidden" name="table" value="{{ $activeTable }}">
                            <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-[11px] transition flex items-center gap-1" title="Drop Table">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                <span>Drop</span>
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- TAB 1: BROWSE TABLE DATA -->
            @if($activeTable)
                <div x-show="activeTab === 'browse'" x-cloak class="bg-[#0b101e] border border-white/5 rounded-2xl p-5 shadow-xl space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-3 border-b border-white/5">
                        <div class="flex items-center gap-3">
                            <h2 class="text-base font-bold text-white font-mono flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                {{ $activeTable }}
                            </h2>
                            <span class="text-xs text-slate-400">Total: <strong class="text-white">{{ number_format($tableData['total']) }}</strong> rows</span>
                        </div>

                        <!-- Pagination Links -->
                        @if(($tableData['total_pages'] ?? 1) > 1)
                            <div class="flex items-center gap-2 text-xs">
                                @if($tableData['page'] > 1)
                                    <a href="{{ route('databases.studio', [$database, 'table' => $activeTable, 'page' => $tableData['page'] - 1]) }}" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 transition">
                                        &larr; Prev
                                    </a>
                                @endif
                                <span class="text-slate-400">Page <strong class="text-white">{{ $tableData['page'] }}</strong> of {{ $tableData['total_pages'] }}</span>
                                @if($tableData['page'] < $tableData['total_pages'])
                                    <a href="{{ route('databases.studio', [$database, 'table' => $activeTable, 'page' => $tableData['page'] + 1]) }}" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 transition">
                                        Next &rarr;
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Data Grid Table -->
                    @if(!empty($tableData['columns']))
                        <div class="overflow-x-auto rounded-xl border border-white/5 max-h-[500px] overflow-y-auto custom-scrollbar bg-[#070b14]">
                            <table class="w-full text-left text-xs text-slate-300 font-mono">
                                <thead class="bg-[#0f172a] text-slate-400 uppercase text-[11px] tracking-wider sticky top-0 z-10 border-b border-white/10">
                                    <tr>
                                        <th class="py-3 px-4 w-12 text-slate-500 text-center">#</th>
                                        @foreach($tableData['columns'] as $col)
                                            <th class="py-3 px-4 font-semibold text-slate-300 whitespace-nowrap">{{ $col }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @forelse($tableData['rows'] as $idx => $row)
                                        <tr class="hover:bg-indigo-600/5 transition">
                                            <td class="py-2.5 px-4 text-slate-500 text-center text-[10px]">{{ ($tableData['page'] - 1) * $tableData['per_page'] + $idx + 1 }}</td>
                                            @foreach($tableData['columns'] as $col)
                                                <td class="py-2.5 px-4 whitespace-nowrap max-w-xs truncate text-slate-300">
                                                    @if(is_null($row[$col] ?? null))
                                                        <span class="text-slate-600 italic">NULL</span>
                                                    @else
                                                        {{ (string)($row[$col] ?? '') }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ count($tableData['columns']) + 1 }}" class="py-12 text-center text-slate-400">
                                                <div class="text-3xl mb-2">📭</div>
                                                <p class="font-sans font-medium text-slate-300">This table is currently empty</p>
                                                <p class="text-[11px] text-slate-500 font-sans mt-0.5">Use the SQL Query Runner tab to insert records.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-8 text-center bg-[#070b14] rounded-xl border border-white/5 text-slate-400">
                            No columns found for this table.
                        </div>
                    @endif
                </div>

                <!-- TAB 2: SCHEMA STRUCTURE -->
                <div x-show="activeTab === 'structure'" x-cloak class="bg-[#0b101e] border border-white/5 rounded-2xl p-5 shadow-xl space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-white/5">
                        <h2 class="text-base font-bold text-white font-mono flex items-center gap-2">
                            <span>Columns &amp; Types:</span>
                            <span class="text-indigo-400">{{ $activeTable }}</span>
                        </h2>
                        <span class="text-xs text-slate-400">{{ count($structure) }} columns defined</span>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-white/5 bg-[#070b14]">
                        <table class="w-full text-left text-xs text-slate-300 font-mono">
                            <thead class="bg-[#0f172a] text-slate-400 uppercase text-[11px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Column</th>
                                    <th class="py-3 px-4">Type</th>
                                    <th class="py-3 px-4">Null</th>
                                    <th class="py-3 px-4">Key / Index</th>
                                    <th class="py-3 px-4">Default</th>
                                    <th class="py-3 px-4">Extra</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($structure as $col)
                                    <tr class="hover:bg-white/5 transition">
                                        <td class="py-3 px-4 font-bold text-white flex items-center gap-2">
                                            @if(!empty($col['key']) && $col['key'] === 'PRI')
                                                <span class="text-amber-400" title="Primary Key">🔑</span>
                                            @endif
                                            <span>{{ $col['field'] }}</span>
                                        </td>
                                        <td class="py-3 px-4 text-indigo-300">
                                            <span class="px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/20 text-[11px]">
                                                {{ $col['type'] }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($col['null'])
                                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-400 font-semibold">YES</span>
                                            @else
                                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-rose-500/10 text-rose-400 font-semibold">NO</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            @if(!empty($col['key']))
                                                <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 font-semibold text-[10px] border border-amber-500/20">
                                                    {{ $col['key'] }}
                                                </span>
                                            @else
                                                <span class="text-slate-600">-</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-slate-400">{{ $col['default'] ?? 'NULL' }}</td>
                                        <td class="py-3 px-4 text-slate-400 text-[11px]">{{ $col['extra'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- TAB 3: SQL QUERY RUNNER -->
            <div x-show="activeTab === 'query'" x-cloak class="bg-[#0b101e] border border-white/5 rounded-2xl p-5 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-white/5">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span>SQL Query Runner</span>
                        </h2>
                        <p class="text-xs text-slate-400">Run SELECT, INSERT, UPDATE, ALTER queries directly on <strong class="text-slate-200 font-mono">{{ $database->name }}</strong>.</p>
                    </div>

                    <!-- Quick Query Presets -->
                    <div class="hidden sm:flex items-center gap-1.5 text-xs">
                        <button @click="querySql = 'SHOW TABLES;'" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 text-[11px] transition">
                            Tables
                        </button>
                        @if($activeTable)
                            <button @click="querySql = 'SELECT * FROM {{ $database->type === 'postgres' ? '\"' . $activeTable . '\"' : '`' . $activeTable . '`' }} LIMIT 20;'" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 text-[11px] transition">
                                Select {{ $activeTable }}
                            </button>
                            <button @click="querySql = 'SELECT COUNT(*) as total_records FROM {{ $database->type === 'postgres' ? '\"' . $activeTable . '\"' : '`' . $activeTable . '`' }};'" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 text-[11px] transition">
                                Count
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Query Input Form -->
                <form @submit.prevent="runQueryNow" class="space-y-3">
                    <div class="relative">
                        <textarea x-model="querySql" @keydown.meta.enter="runQueryNow" @keydown.ctrl.enter="runQueryNow" rows="5"
                                  placeholder="Write your SQL query here (e.g. SELECT * FROM users LIMIT 10;)"
                                  class="w-full font-mono text-xs bg-[#030712] border border-white/10 rounded-xl p-4 text-emerald-300 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition shadow-inner leading-relaxed"></textarea>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-slate-500 flex items-center gap-1.5">
                            <kbd class="px-1.5 py-0.5 rounded bg-white/10 text-slate-300 font-mono text-[10px]">⌘ + Enter</kbd>
                            or
                            <kbd class="px-1.5 py-0.5 rounded bg-white/10 text-slate-300 font-mono text-[10px]">Ctrl + Enter</kbd>
                            to execute
                        </span>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="querySql = ''" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-xs transition">
                                Clear
                            </button>
                            <button type="submit" :disabled="isExecuting" class="px-5 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold flex items-center gap-2 transition shadow-lg shadow-emerald-600/25 disabled:opacity-50">
                                <template x-if="isExecuting">
                                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                </template>
                                <template x-if="!isExecuting">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </template>
                                <span>Execute Query</span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Query Results Box -->
                <div x-show="queryResult !== null" x-cloak class="mt-4 pt-4 border-t border-white/5 space-y-3">
                    <!-- Status Banner -->
                    <template x-if="queryResult && queryResult.success">
                        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span x-text="queryResult.type === 'select' ? 'Query returned ' + queryResult.count + ' rows' : 'Query executed successfully. ' + (queryResult.affected || 0) + ' rows affected.'"></span>
                            </span>
                            <span class="font-mono text-[11px] text-emerald-300" x-text="'⚡ ' + queryResult.time_ms + ' ms'"></span>
                        </div>
                    </template>

                    <template x-if="queryResult && !queryResult.success">
                        <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-start gap-2">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <strong class="block font-bold">SQL Execution Error:</strong>
                                <span class="font-mono text-[11px] text-rose-300 break-all" x-text="queryResult.error"></span>
                            </div>
                        </div>
                    </template>

                    <!-- Query Result Table -->
                    <template x-if="queryResult && queryResult.success && queryResult.type === 'select' && queryResult.columns.length > 0">
                        <div class="overflow-x-auto rounded-xl border border-white/5 max-h-[420px] overflow-y-auto custom-scrollbar bg-[#070b14]">
                            <table class="w-full text-left text-xs text-slate-300 font-mono">
                                <thead class="bg-[#0f172a] text-slate-400 uppercase text-[11px] tracking-wider sticky top-0 z-10 border-b border-white/10">
                                    <tr>
                                        <th class="py-2.5 px-3 w-10 text-slate-500 text-center">#</th>
                                        <template x-for="col in queryResult.columns" :key="col">
                                            <th class="py-2.5 px-3 font-semibold text-slate-300 whitespace-nowrap" x-text="col"></th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <template x-for="(row, idx) in queryResult.rows" :key="idx">
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="py-2 px-3 text-slate-500 text-center text-[10px]" x-text="idx + 1"></td>
                                            <template x-for="col in queryResult.columns" :key="col">
                                                <td class="py-2 px-3 whitespace-nowrap max-w-xs truncate text-slate-300" x-text="row[col] !== null ? row[col] : 'NULL'"></td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>

            <!-- TAB 4: IMPORT / EXPORT -->
            <div x-show="activeTab === 'import_export'" x-cloak class="bg-[#0b101e] border border-white/5 rounded-2xl p-6 shadow-xl space-y-6">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        <span>Database Backup &amp; Restore</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">Export full database dumps or restore an existing .sql dump into <strong class="text-slate-200 font-mono">{{ $database->name }}</strong>.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Export Card -->
                    <div class="p-5 rounded-xl bg-[#070b14] border border-white/5 space-y-4">
                        <div class="w-10 h-10 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Export Database Dump</h3>
                            <p class="text-xs text-slate-400 mt-1">Generates a full SQL dump containing table structures, indexes, and all rows.</p>
                        </div>
                        <a href="{{ route('databases.studio.export', $database) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/25 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Download .SQL Backup</span>
                        </a>
                    </div>

                    <!-- Import Card -->
                    <div class="p-5 rounded-xl bg-[#070b14] border border-white/5 space-y-4">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Import SQL File</h3>
                            <p class="text-xs text-slate-400 mt-1">Upload and execute a .sql dump file directly into this database.</p>
                        </div>

                        <form method="POST" action="{{ route('databases.studio.import', $database) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input type="file" name="sql_file" accept=".sql" required class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white/10 file:text-slate-200 hover:file:bg-white/20 transition cursor-pointer">
                            <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-md shadow-emerald-600/25 transition flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                <span>Upload &amp; Restore</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- CREATE TABLE MODAL -->
    <div x-data="{ open: false, tableName: '', cols: [{name: 'id', type: 'INT', nullable: false, primary: true}, {name: 'name', type: 'VARCHAR(255)', nullable: false, primary: false}] }"
         @open-create-table-modal.window="open = true"
         x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div @click.outside="open = false" class="w-full max-w-lg bg-[#0b101e] border border-white/10 rounded-2xl p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-white/5">
                <h3 class="text-base font-bold text-white">Create New Table in <span class="font-mono text-indigo-400">{{ $database->name }}</span></h3>
                <button @click="open = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>

            <form method="POST" action="{{ route('databases.studio.create-table', $database) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Table Name</label>
                    <input type="text" name="table_name" required placeholder="e.g. users, posts, products"
                           class="w-full bg-[#070b14] border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold text-slate-300">Columns</label>
                        <button type="button" @click="cols.push({name: '', type: 'VARCHAR(255)', nullable: true, primary: false})" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">
                            + Add Column
                        </button>
                    </div>

                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <template x-for="(col, index) in cols" :key="index">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="'columns['+index+'][name]'" x-model="col.name" placeholder="Column name" class="flex-1 bg-[#070b14] border border-white/10 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                <select :name="'columns['+index+'][type]'" x-model="col.type" class="bg-[#070b14] border border-white/10 rounded-lg px-2 py-1.5 text-xs text-slate-200">
                                    <option value="INT">INT</option>
                                    <option value="BIGINT">BIGINT</option>
                                    <option value="VARCHAR(255)">VARCHAR(255)</option>
                                    <option value="TEXT">TEXT</option>
                                    <option value="BOOLEAN">BOOLEAN</option>
                                    <option value="TIMESTAMP">TIMESTAMP</option>
                                    <option value="DECIMAL(10,2)">DECIMAL</option>
                                </select>
                                <label class="flex items-center gap-1 text-[11px] text-slate-400">
                                    <input type="checkbox" :name="'columns['+index+'][primary]'" value="1" x-model="col.primary" class="rounded bg-slate-900 border-slate-700 text-indigo-600">
                                    <span>PK</span>
                                </label>
                                <button type="button" @click="if(cols.length > 1) cols.splice(index, 1)" class="text-slate-500 hover:text-rose-400 text-xs px-1">&times;</button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/5">
                    <button type="button" @click="open = false" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-semibold">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/25">
                        Create Table
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function databaseStudio(config) {
    return {
        dbId: config.dbId,
        activeTab: config.activeTab,
        activeTable: config.activeTable,
        searchTable: '',
        querySql: config.querySql,
        isExecuting: false,
        queryResult: null,

        async runQueryNow() {
            if (!this.querySql.trim() || this.isExecuting) return;
            this.isExecuting = true;
            this.queryResult = null;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                               || '{{ csrf_token() }}';

                const response = await fetch('{{ route("databases.studio.query", $database) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ sql: this.querySql })
                });

                const data = await response.json();
                this.queryResult = data;
            } catch (err) {
                this.queryResult = {
                    success: false,
                    error: err.message || 'Network request failed'
                };
            } finally {
                this.isExecuting = false;
            }
        }
    };
}
</script>
@endsection
