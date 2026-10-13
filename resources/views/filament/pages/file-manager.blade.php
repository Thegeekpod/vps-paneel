<x-filament-panels::page>
    <div class="space-y-5" x-data @keydown.window.cmd.s.prevent="$wire.saveFile()" @keydown.window.ctrl.s.prevent="$wire.saveFile()">
        
        {{-- Header Controls: Website selector, safe path indicator & Action Buttons --}}
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Target Website:</span>
                <select 
                    wire:model.live="selectedWebsiteId"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs font-medium focus:border-primary-500 focus:ring-primary-500 py-1.5 px-3"
                >
                    @forelse (\App\Models\Website::all() as $site)
                        <option value="{{ $site->id }}">{{ $site->primary_domain }} (user: {{ $site->system_user }})</option>
                    @empty
                        <option value="">No websites hosted yet</option>
                    @endforelse
                </select>

                @if ($this->selectedWebsite)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-2xs font-mono rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                        <x-heroicon-m-shield-check class="w-3.5 h-3.5 text-emerald-500" />
                        public_html sandbox
                    </span>
                @endif
            </div>

            {{-- Action Buttons: New File, New Folder, Upload --}}
            <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto justify-start lg:justify-end">
                <x-filament::button 
                    wire:click="openNewFileModal" 
                    size="sm" 
                    color="primary" 
                    icon="heroicon-m-document-plus"
                >
                    New File
                </x-filament::button>

                <x-filament::button 
                    wire:click="openNewFolderModal" 
                    size="sm" 
                    color="gray" 
                    icon="heroicon-m-folder-plus"
                >
                    New Folder
                </x-filament::button>

                <label class="cursor-pointer">
                    <input 
                        type="file" 
                        wire:model="uploadedFile" 
                        class="hidden" 
                    />
                    <span class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 shadow-sm transition">
                        <x-heroicon-m-arrow-up-tray class="w-4 h-4 text-gray-500" />
                        <span>Upload</span>
                    </span>
                </label>

                <x-filament::button 
                    wire:click="fixPermissions" 
                    size="sm" 
                    color="gray" 
                    icon="heroicon-m-shield-check"
                    tooltip="Reset correct ownership (siteuser:www-data) and permissions (775/664)"
                >
                    Fix Permissions
                </x-filament::button>

                <div wire:loading wire:target="uploadedFile" class="text-xs text-primary-600 font-semibold animate-pulse">
                    Uploading...
                </div>
            </div>
        </div>

        {{-- Breadcrumbs Navigation Bar --}}
        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-900/60 text-xs">
            <div class="flex items-center gap-1.5 font-mono overflow-x-auto py-0.5">
                <button 
                    wire:click="navigateTo('')" 
                    class="px-2 py-1 rounded-md hover:bg-gray-200 dark:hover:bg-gray-800 text-primary-600 dark:text-primary-400 font-bold flex items-center gap-1"
                >
                    <x-heroicon-m-home class="w-3.5 h-3.5" />
                    <span>root</span>
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
                        class="px-2 py-1 rounded-md hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium"
                    >
                        {{ $part }}
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <span class="text-3xs text-gray-400 uppercase font-mono hidden sm:inline">
                    {{ count($this->files) }} items
                </span>

                @if ($currentPath)
                    <button 
                        wire:click="navigateUp"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 shadow-2xs"
                    >
                        <x-heroicon-m-arrow-up class="w-3.5 h-3.5" />
                        Up Level
                    </button>
                @endif
            </div>
        </div>

        {{-- Built-in Text / Code Editor --}}
        @if ($editingFilePath)
            <div class="rounded-xl border border-indigo-500/40 bg-white dark:bg-gray-900 shadow-xl overflow-hidden transition-all">
                {{-- Editor Header --}}
                <div class="flex items-center justify-between p-3.5 bg-slate-900 border-b border-slate-800 text-white">
                    <div class="flex items-center gap-2 font-mono text-xs">
                        <x-heroicon-o-document-text class="w-4 h-4 text-indigo-400" />
                        <span class="font-bold text-gray-200">{{ $editingFileName }}</span>
                        <span class="text-gray-500 text-3xs">({{ $editingFilePath }})</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-filament::button 
                            wire:click="saveFile" 
                            size="sm" 
                            color="success" 
                            icon="heroicon-m-check"
                        >
                            Save Changes (Ctrl+S)
                        </x-filament::button>

                        <button 
                            wire:click="closeEditor"
                            class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-slate-800 text-gray-300 hover:bg-slate-700 border border-slate-700 transition"
                        >
                            Close
                        </button>
                    </div>
                </div>

                {{-- Editor Body --}}
                <div class="relative bg-[#0d1117] p-3">
                    <textarea 
                        wire:model="fileContent"
                        rows="22"
                        spellcheck="false"
                        class="w-full font-mono text-xs p-3 rounded-lg border-0 bg-transparent text-[#7ee787] focus:ring-0 leading-relaxed outline-none resize-y selection:bg-indigo-600"
                        placeholder="// Enter file content..."
                    ></textarea>

                    <div class="flex items-center justify-between text-3xs text-gray-500 font-mono pt-2 border-t border-slate-800/80 px-1">
                        <span>UTF-8 • {{ strlen($fileContent) }} characters</span>
                        <span>Hotkeys: Ctrl+S / Cmd+S to Save instantly</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Files and Directories Table --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-3xs uppercase tracking-wider text-gray-500 border-b border-gray-100 dark:border-gray-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">Name</th>
                        <th class="py-3 px-4 font-semibold">Size</th>
                        <th class="py-3 px-4 font-semibold">Permissions</th>
                        <th class="py-3 px-4 font-semibold">Last Modified</th>
                        <th class="py-3 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono">
                    @forelse ($this->files as $item)
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition-colors group">
                            <td class="py-3 px-4 font-medium flex items-center gap-2.5">
                                @if ($item['is_dir'])
                                    <x-heroicon-s-folder class="w-4 h-4 text-amber-500 shrink-0" />
                                    <button 
                                        wire:click="navigateTo('{{ $item['path'] }}')" 
                                        class="text-primary-600 dark:text-primary-400 hover:underline font-bold text-left"
                                    >
                                        {{ $item['name'] }}/
                                    </button>
                                @else
                                    @php
                                        $ext = strtolower($item['extension'] ?? '');
                                        $isCode = in_array($ext, ['php', 'html', 'js', 'css', 'json', 'sql', 'env', 'sh', 'md', 'txt', 'conf'], true);
                                    @endphp
                                    @if ($isCode)
                                        <x-heroicon-o-code-bracket class="w-4 h-4 text-indigo-400 shrink-0" />
                                    @else
                                        <x-heroicon-o-document class="w-4 h-4 text-gray-400 shrink-0" />
                                    @endif
                                    <button 
                                        wire:click="editFile('{{ $item['path'] }}')"
                                        class="text-gray-800 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400 hover:underline text-left"
                                    >
                                        {{ $item['name'] }}
                                    </button>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-gray-500 font-sans">
                                {{ $item['formatted_size'] }}
                            </td>

                            <td class="py-3 px-4 text-gray-400 text-3xs">
                                {{ $item['permissions'] }}
                            </td>

                            <td class="py-3 px-4 text-gray-500 font-sans">
                                {{ $item['modified_at'] }}
                            </td>

                            <td class="py-3 px-4 text-right space-x-1.5 font-sans">
                                @if (! $item['is_dir'])
                                    <button 
                                        wire:click="editFile('{{ $item['path'] }}')"
                                        class="px-2 py-1 text-2xs font-medium rounded-md bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 hover:bg-indigo-100 transition"
                                        title="Edit File"
                                    >
                                        Edit
                                    </button>

                                    <button 
                                        wire:click="downloadItem('{{ $item['path'] }}')"
                                        class="px-2 py-1 text-2xs font-medium rounded-md bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200 transition"
                                        title="Download"
                                    >
                                        Download
                                    </button>
                                @endif

                                <button 
                                    wire:click="openRenameModal('{{ $item['path'] }}', '{{ $item['name'] }}')"
                                    class="px-2 py-1 text-2xs font-medium rounded-md bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200 transition"
                                    title="Rename"
                                >
                                    Rename
                                </button>

                                <button 
                                    wire:click="deleteItem('{{ $item['path'] }}')"
                                    wire:confirm="Are you sure you want to permanently delete '{{ $item['name'] }}'?"
                                    class="px-2 py-1 text-2xs font-medium rounded-md bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 hover:bg-rose-100 transition"
                                    title="Delete"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-400 font-sans">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <x-heroicon-o-folder-open class="w-8 h-8 text-gray-300 dark:text-gray-600" />
                                    <span>This directory is currently empty.</span>
                                    <span class="text-xs text-gray-400">Click <strong>New File</strong> or <strong>Upload</strong> above to add files.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Modal: Create New File --}}
        @if ($showNewFileModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
                <div class="w-full max-w-md p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl space-y-4">
                    <div class="flex items-center gap-2 text-base font-bold text-gray-900 dark:text-gray-100">
                        <x-heroicon-o-document-plus class="w-5 h-5 text-primary-500" />
                        <span>Create New File</span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Enter the name for the new file in <code class="text-primary-500">/{{ $currentPath ?: 'root' }}</code>:
                    </p>

                    <div>
                        <input 
                            type="text" 
                            wire:model="newFileName"
                            wire:keydown.enter="createNewFile"
                            placeholder="e.g. index.php, style.css, script.js"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 focus:ring-primary-500 focus:border-primary-500 font-mono"
                            autofocus
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button 
                            wire:click="$set('showNewFileModal', false)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200"
                        >
                            Cancel
                        </button>
                        <x-filament::button wire:click="createNewFile" size="sm" color="primary">
                            Create & Open in Editor
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal: Create New Folder --}}
        @if ($showNewFolderModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
                <div class="w-full max-w-md p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl space-y-4">
                    <div class="flex items-center gap-2 text-base font-bold text-gray-900 dark:text-gray-100">
                        <x-heroicon-o-folder-plus class="w-5 h-5 text-amber-500" />
                        <span>Create New Folder</span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Enter the folder name to create in <code class="text-amber-500">/{{ $currentPath ?: 'root' }}</code>:
                    </p>

                    <div>
                        <input 
                            type="text" 
                            wire:model="newFolderName"
                            wire:keydown.enter="createNewFolder"
                            placeholder="e.g. assets, images, includes"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 focus:ring-amber-500 focus:border-amber-500 font-mono"
                            autofocus
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button 
                            wire:click="$set('showNewFolderModal', false)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200"
                        >
                            Cancel
                        </button>
                        <x-filament::button wire:click="createNewFolder" size="sm" color="warning">
                            Create Folder
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal: Rename Item --}}
        @if ($showRenameModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
                <div class="w-full max-w-md p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl space-y-4">
                    <div class="flex items-center gap-2 text-base font-bold text-gray-900 dark:text-gray-100">
                        <x-heroicon-o-pencil-square class="w-5 h-5 text-primary-500" />
                        <span>Rename Item</span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Enter the new name for <code class="text-primary-500 font-mono">{{ basename($renameTargetPath) }}</code>:
                    </p>

                    <div>
                        <input 
                            type="text" 
                            wire:model="renameNewName"
                            wire:keydown.enter="executeRename"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 focus:ring-primary-500 focus:border-primary-500 font-mono"
                            autofocus
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button 
                            wire:click="$set('showRenameModal', false)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200"
                        >
                            Cancel
                        </button>
                        <x-filament::button wire:click="executeRename" size="sm" color="primary">
                            Save Name
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
