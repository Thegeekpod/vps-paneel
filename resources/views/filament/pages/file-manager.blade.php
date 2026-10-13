<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Controls: Website selector & path breadcrumbs --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Target Website:</label>
                <select 
                    wire:model.live="selectedWebsiteId"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    @foreach (\App\Models\Website::all() as $site)
                        <option value="{{ $site->id }}">{{ $site->primary_domain }} ({{ $site->system_user }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 font-mono">
                <x-heroicon-m-shield-check class="w-4 h-4 text-emerald-500" />
                <span>Jail: /home/{{ $this->selectedWebsite?->system_user }}/web/{{ $this->selectedWebsite?->primary_domain }}/public_html</span>
            </div>
        </div>

        {{-- Breadcrumbs Navigation --}}
        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
            <div class="flex items-center gap-2 font-mono text-sm">
                <button 
                    wire:click="navigateTo('')" 
                    class="px-2 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-800 text-primary-600 font-bold"
                >
                    /
                </button>
                @php
                    $parts = array_filter(explode('/', $currentPath));
                    $cumulative = '';
                @endphp
                @foreach ($parts as $part)
                    @php $cumulative .= '/' . $part; @endphp
                    <span class="text-gray-400">/</span>
                    <button 
                        wire:click="navigateTo('{{ trim($cumulative, '/') }}')"
                        class="px-2 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200"
                    >
                        {{ $part }}
                    </button>
                @endforeach
            </div>

            @if ($currentPath)
                <button 
                    wire:click="navigateUp"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50"
                >
                    <x-heroicon-m-arrow-up class="w-3.5 h-3.5" />
                    Up Level
                </button>
            @endif
        </div>

        {{-- File Editor Drawer/Card (Active when editing) --}}
        @if ($editingFilePath)
            <div class="p-4 rounded-xl border border-primary-500/30 bg-white dark:bg-gray-900 shadow-lg space-y-4">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div class="flex items-center gap-2 font-mono text-sm font-semibold">
                        <x-heroicon-o-document-text class="w-5 h-5 text-primary-500" />
                        <span>Editing: {{ $editingFilePath }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="saveFile"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white hover:bg-primary-500 shadow-sm"
                        >
                            Save Changes
                        </button>
                        <button 
                            wire:click="closeEditor"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <div>
                    <textarea 
                        wire:model="fileContent"
                        rows="18"
                        class="w-full font-mono text-xs p-3 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-900 text-emerald-400 focus:ring-primary-500 focus:border-primary-500"
                    ></textarea>
                </div>
            </div>
        @endif

        {{-- Files Table --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Name</th>
                        <th class="py-3 px-4">Size</th>
                        <th class="py-3 px-4">Permissions</th>
                        <th class="py-3 px-4">Modified</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono text-xs">
                    @forelse ($this->files as $item)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="py-3 px-4 font-medium flex items-center gap-2">
                                @if ($item['is_dir'])
                                    <x-heroicon-s-folder class="w-4 h-4 text-amber-500 shrink-0" />
                                    <button wire:click="navigateTo('{{ $item['path'] }}')" class="text-primary-600 hover:underline font-semibold">
                                        {{ $item['name'] }}/
                                    </button>
                                @else
                                    <x-heroicon-o-document-text class="w-4 h-4 text-gray-400 shrink-0" />
                                    <span class="text-gray-800 dark:text-gray-200">{{ $item['name'] }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-gray-500">{{ $item['formatted_size'] }}</td>
                            <td class="py-3 px-4 text-gray-500">{{ $item['permissions'] }}</td>
                            <td class="py-3 px-4 text-gray-500">{{ $item['modified_at'] }}</td>
                            <td class="py-3 px-4 text-right space-x-2">
                                @if (! $item['is_dir'])
                                    <button 
                                        wire:click="editFile('{{ $item['path'] }}')"
                                        class="px-2 py-1 text-xs text-primary-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                @endif
                                <button 
                                    wire:click="deleteItem('{{ $item['path'] }}')"
                                    wire:confirm="Are you sure you want to delete this item?"
                                    class="px-2 py-1 text-xs text-rose-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-400">
                                This folder is empty.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
