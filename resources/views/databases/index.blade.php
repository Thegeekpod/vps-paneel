@extends('layouts.app')

@section('title', 'MySQL Databases')
@section('page-title', 'MySQL Databases & Users')

@section('content')
<div class="space-y-6">

    <!-- Top Grid: Create Database Form (1/3) & Database List (2/3) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Create Database Card -->
        <div class="p-6 rounded-2xl bg-[#0b101e] border border-white/5 shadow-xl h-fit">
            <h2 class="text-base font-semibold text-white mb-1">Create New Database</h2>
            <p class="text-xs text-slate-400 mb-6">Create an isolated MySQL database and user credentials.</p>

            <form method="POST" action="{{ route('databases.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs text-slate-300 mb-1.5 font-medium">Database Name</label>
                    <input type="text" name="name" placeholder="e.g. app_production" required
                        class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1.5 font-medium">Database Username (Optional)</label>
                    <input type="text" name="username" placeholder="Leave blank to use db name"
                        class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1.5 font-medium">Password (Optional)</label>
                    <input type="text" name="password" placeholder="Leave blank to auto-generate"
                        class="w-full bg-white/[0.02] border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/20 transition">
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
                    <p class="text-xs text-slate-400">Total {{ $databases->total() }} MySQL databases provisioned on this VPS</p>
                </div>
            </div>

            @if($databases->isEmpty())
                <div class="text-center py-12 border border-dashed border-white/10 rounded-xl">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                    <h3 class="text-sm font-medium text-slate-300">No databases created</h3>
                    <p class="text-xs text-slate-500 mt-1">Use the form on the left or deploy a WordPress/Laravel site to auto-create databases.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-white/5 uppercase text-[10px]">
                                <th class="pb-3 font-semibold">Database</th>
                                <th class="pb-3 font-semibold">User</th>
                                <th class="pb-3 font-semibold">Connected Site</th>
                                <th class="pb-3 font-semibold">Host & Port</th>
                                <th class="pb-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($databases as $db)
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="py-3 font-mono font-bold text-white">
                                        {{ $db->name }}
                                    </td>
                                    <td class="py-3 font-mono text-slate-300">
                                        {{ $db->username }}
                                    </td>
                                    <td class="py-3 text-slate-400">
                                        @if($db->sites->isNotEmpty())
                                            @foreach($db->sites as $s)
                                                <a href="{{ route('sites.show', $s) }}" class="text-indigo-400 hover:underline block truncate max-w-[140px]">{{ $s->domain }}</a>
                                            @endforeach
                                        @else
                                            <span class="text-slate-600">None</span>
                                        @endif
                                    </td>
                                    <td class="py-3 font-mono text-slate-400">
                                        {{ $db->host }}:{{ $db->port }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <form method="POST" action="{{ route('databases.destroy', $db) }}" onsubmit="return confirm('Are you sure you want to drop database {{ $db->name }}? All data will be permanently deleted.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-[11px] font-medium transition">
                                                Drop DB
                                            </button>
                                        </form>
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
