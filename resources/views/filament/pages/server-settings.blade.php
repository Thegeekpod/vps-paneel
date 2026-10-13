<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Section 0: One-Click Panel Update --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-arrow-path class="w-5 h-5 text-emerald-500" />
                    <span>Control Panel Self-Update</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Pulls new commits from your Git repository, executes migrations, publishes assets, clears caches, and restarts background workers.
            </x-slot>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Current Commit:</span>
                        <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                            {{ $versionInfo['hash'] ?? 'main' }}
                        </span>
                        <span class="text-xs text-gray-400 font-mono">({{ $versionInfo['branch'] ?? 'main' }})</span>
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-300">
                        <span class="font-medium">Message:</span> {{ $versionInfo['message'] ?? 'Initial setup' }}
                        <span class="text-gray-400 font-normal">({{ $versionInfo['date'] ?? 'recently' }})</span>
                    </div>
                </div>

                <div>
                    <button 
                        type="button"
                        wire:click="updatePanel"
                        wire:loading.attr="disabled"
                        wire:confirm="Pull latest updates from Git, run migrations, and recompile assets now?"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 shadow-sm disabled:opacity-50"
                    >
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" wire:loading.remove wire:target="updatePanel" />
                        <svg wire:loading wire:target="updatePanel" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Update Panel Now</span>
                    </button>
                </div>
            </div>
        </x-filament::section>

        {{-- Section 1: Core Host Settings --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-server class="w-5 h-5 text-primary-500" />
                    <span>Host Node Configuration</span>
                </div>
            </x-slot>

            <form wire:submit="saveSettings" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Server Hostname (FQDN)</label>
                        <input 
                            type="text" 
                            wire:model="hostname" 
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Public IPv4 Address</label>
                        <input 
                            type="text" 
                            wire:model="serverIp" 
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Panel HTTPS Port</label>
                        <input 
                            type="number" 
                            wire:model="panelPort" 
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Default PHP Version</label>
                        <select 
                            wire:model="defaultPhp" 
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <option value="8.2">PHP 8.2 FPM</option>
                            <option value="8.3">PHP 8.3 FPM</option>
                            <option value="8.4">PHP 8.4 FPM</option>
                        </select>
                    </div>
                </div>

                <div class="pt-2">
                    <button 
                        type="submit" 
                        class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary-600 text-white hover:bg-primary-500 shadow-sm"
                    >
                        Save Configuration
                    </button>
                </div>
            </form>
        </x-filament::section>

        {{-- Section 2: Service Management & Controls --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-command-line class="w-5 h-5 text-primary-500" />
                    <span>System Service Controls</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Safely restart or reload background daemons without logging into SSH.
            </x-slot>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
                @php
                    $services = [
                        'nginx' => 'Nginx Web Server',
                        'mariadb' => 'MariaDB Database',
                        'php8.3-fpm' => 'PHP 8.3 FPM Pool',
                        'pdns' => 'PowerDNS Server',
                        'postfix' => 'Postfix Mail Agent',
                        'dovecot' => 'Dovecot IMAP Server',
                    ];
                @endphp

                @foreach ($services as $svc => $label)
                    <div class="p-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $label }}</div>
                            <div class="text-xs text-gray-400 font-mono">{{ $svc }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                wire:click="reloadService('{{ $svc }}')"
                                class="px-2.5 py-1 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-white dark:hover:bg-gray-800"
                            >
                                Reload
                            </button>
                            <button 
                                wire:click="restartService('{{ $svc }}')"
                                class="px-2.5 py-1 text-xs font-medium rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300 hover:bg-primary-100"
                            >
                                Restart
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Section 3: UFW Firewall Manager --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shield-check class="w-5 h-5 text-emerald-500" />
                    <span>Uncomplicated Firewall (UFW) Rules</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Active host firewall rules protecting and routing network traffic for your hosted websites, DNS, email, and databases.
            </x-slot>

            {{-- Open new port bar & Default Rules Restore --}}
            <div class="p-4 mb-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/60 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Allow Port:</span>
                    <input 
                        type="number" 
                        placeholder="e.g. 21" 
                        wire:model="newPort" 
                        class="w-28 text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-1.5 focus:ring-emerald-500 focus:border-emerald-500"
                    />
                    <select 
                        wire:model="newProto" 
                        class="text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-1.5 focus:ring-emerald-500 focus:border-emerald-500"
                    >
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                    </select>
                    <x-filament::button 
                        wire:click="allowPort"
                        size="sm"
                        color="success"
                        icon="heroicon-m-plus"
                    >
                        Allow Rule
                    </x-filament::button>
                </div>

                <div>
                    <x-filament::button 
                        wire:click="ensureDefaultRules"
                        size="sm"
                        color="primary"
                        icon="heroicon-m-shield-check"
                        outlined
                    >
                        Ensure All Web Hosting Ports (80, 443, 53, Mail)
                    </x-filament::button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-500">
                        <tr>
                            <th class="py-2.5 px-4">Port</th>
                            <th class="py-2.5 px-4">Protocol</th>
                            <th class="py-2.5 px-4">Service / Purpose</th>
                            <th class="py-2.5 px-4">Rule Action</th>
                            <th class="py-2.5 px-4 text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($firewallRules as $rule)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                <td class="py-2.5 px-4 font-bold text-gray-900 dark:text-gray-100">
                                    {{ $rule['port'] }}
                                </td>
                                <td class="py-2.5 px-4 text-gray-500 uppercase">
                                    {{ $rule['proto'] }}
                                </td>
                                <td class="py-2.5 px-4 font-sans font-medium text-gray-700 dark:text-gray-300">
                                    {{ $rule['service'] ?? 'Custom Service Port' }}
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-2xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ $rule['action'] ?? 'ALLOW' }} IN
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right">
                                    @if (! in_array($rule['port'], [22, 8443], true))
                                        <button 
                                            wire:click="deleteRule({{ $rule['port'] }}, '{{ $rule['proto'] }}')"
                                            wire:confirm="Are you sure you want to delete the firewall rule for port {{ $rule['port'] }}?"
                                            class="text-xs text-rose-500 hover:text-rose-700 font-sans font-medium hover:underline"
                                        >
                                            Delete
                                        </button>
                                    @else
                                        <span class="text-3xs text-gray-400 font-sans uppercase">Protected</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-gray-500 font-sans">
                                    No custom firewall rules configured. Click "Ensure All Web Hosting Ports" above to load standard rules.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
