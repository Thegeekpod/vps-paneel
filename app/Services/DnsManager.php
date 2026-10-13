<?php

namespace App\Services;

use App\Enums\DnsRecordType;
use App\Models\DnsRecord;
use App\Models\DnsZone;
use App\Models\ServerSetting;
use Illuminate\Validation\ValidationException;

class DnsManager
{
    public function __construct(
        protected ServerAgentService $agent
    ) {}

    /**
     * Create a new DNS Zone with recommended defaults (A, CNAME, MX, SPF, DMARC, NS).
     */
    public function createZone(string $domain, ?int $websiteId = null): DnsZone
    {
        $domain = strtolower(trim($domain));
        $serverIp = ServerSetting::get('server_ip', '127.0.0.1');
        $ns1 = ServerSetting::get('dns_nameserver_1', 'ns1.example.com');
        $ns2 = ServerSetting::get('dns_nameserver_2', 'ns2.example.com');

        $zone = DnsZone::create([
            'website_id' => $websiteId,
            'domain_name' => $domain,
            'soa_email' => 'hostmaster.' . $domain,
            'default_ttl' => 3600,
            'status' => 'active',
        ]);

        // Standard default DNS template
        $defaultRecords = [
            ['name' => '@', 'type' => DnsRecordType::A, 'content' => $serverIp, 'ttl' => 3600, 'priority' => null],
            ['name' => 'www', 'type' => DnsRecordType::CNAME, 'content' => $domain . '.', 'ttl' => 3600, 'priority' => null],
            ['name' => 'mail', 'type' => DnsRecordType::A, 'content' => $serverIp, 'ttl' => 3600, 'priority' => null],
            ['name' => '@', 'type' => DnsRecordType::MX, 'content' => 'mail.' . $domain . '.', 'ttl' => 3600, 'priority' => 10],
            ['name' => '@', 'type' => DnsRecordType::TXT, 'content' => '"v=spf1 mx a ~all"', 'ttl' => 3600, 'priority' => null],
            ['name' => '_dmarc', 'type' => DnsRecordType::TXT, 'content' => '"v=DMARC1; p=quarantine;"', 'ttl' => 3600, 'priority' => null],
            ['name' => '@', 'type' => DnsRecordType::NS, 'content' => $ns1 . '.', 'ttl' => 86400, 'priority' => null],
            ['name' => '@', 'type' => DnsRecordType::NS, 'content' => $ns2 . '.', 'ttl' => 86400, 'priority' => null],
        ];

        foreach ($defaultRecords as $rec) {
            $zone->records()->create($rec);
        }

        $this->syncZone($zone);

        return $zone;
    }

    /**
     * Validate and create a DNS record.
     */
    public function addRecord(DnsZone $zone, array $data): DnsRecord
    {
        $this->validateRecord($data['type'], $data['content'], $data['priority'] ?? null);

        $record = $zone->records()->create([
            'name' => trim($data['name']),
            'type' => $data['type'],
            'content' => trim($data['content']),
            'ttl' => (int) ($data['ttl'] ?? $zone->default_ttl),
            'priority' => isset($data['priority']) ? (int) $data['priority'] : null,
        ]);

        $this->syncZone($zone);

        return $record;
    }

    /**
     * Synchronize zone records with PowerDNS via the privileged agent.
     */
    public function syncZone(DnsZone $zone): void
    {
        $records = $zone->records->map(fn($r) => [
            'name' => $r->name,
            'type' => $r->type->value,
            'content' => $r->content,
            'ttl' => $r->ttl,
            'priority' => $r->priority,
        ])->toArray();

        $this->agent->execute('ConfigureDnsZone', [
            'domain' => $zone->domain_name,
            'records' => $records,
        ], 'DnsZone', (string) $zone->id);
    }

    /**
     * Validate DNS record syntax.
     */
    public function validateRecord(DnsRecordType|string $type, string $content, ?int $priority = null): void
    {
        $typeVal = is_string($type) ? $type : $type->value;
        $content = trim($content);

        match ($typeVal) {
            'A' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                ?: throw ValidationException::withMessages(['content' => 'Content must be a valid IPv4 address']),
            'AAAA' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
                ?: throw ValidationException::withMessages(['content' => 'Content must be a valid IPv6 address']),
            'MX' => ($priority !== null && $priority >= 0)
                ?: throw ValidationException::withMessages(['priority' => 'MX records require a valid priority number (0-65535)']),
            default => true,
        };
    }
}
