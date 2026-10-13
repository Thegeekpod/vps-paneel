<div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-amber-900 dark:text-amber-200">
        <div class="font-semibold mb-1 flex items-center gap-1.5">
            <x-heroicon-m-exclamation-triangle class="w-4 h-4 text-amber-500" />
            <span>Important Deliverability & Provider Notice</span>
        </div>
        <ul class="list-disc pl-5 space-y-1 text-xs">
            @foreach ($guidance['smtp_warnings'] as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>

    <div>
        <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Required DNS Records</h4>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400">
                    <tr>
                        <th class="p-2.5">Type</th>
                        <th class="p-2.5">Host</th>
                        <th class="p-2.5">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr>
                        <td class="p-2.5 font-bold text-primary-600">MX</td>
                        <td class="p-2.5">@</td>
                        <td class="p-2.5">mail.{{ $domain->domain_name }}. (Priority: 10)</td>
                    </tr>
                    <tr>
                        <td class="p-2.5 font-bold text-primary-600">TXT (SPF)</td>
                        <td class="p-2.5">@</td>
                        <td class="p-2.5 select-all">{{ $guidance['spf']['value'] }}</td>
                    </tr>
                    <tr>
                        <td class="p-2.5 font-bold text-primary-600">TXT (DKIM)</td>
                        <td class="p-2.5">{{ $guidance['dkim']['name'] }}</td>
                        <td class="p-2.5 break-all select-all">{{ $guidance['dkim']['value'] }}</td>
                    </tr>
                    <tr>
                        <td class="p-2.5 font-bold text-primary-600">TXT (DMARC)</td>
                        <td class="p-2.5">_dmarc</td>
                        <td class="p-2.5 select-all">{{ $guidance['dmarc']['value'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
