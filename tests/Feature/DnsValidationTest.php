<?php

namespace Tests\Feature;

use App\Enums\DnsRecordType;
use App\Services\DnsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DnsValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dns_zone_creation_with_defaults(): void
    {
        $manager = app(DnsManager::class);
        $zone = $manager->createZone('testzone.com');

        $this->assertDatabaseHas('dns_zones', [
            'domain_name' => 'testzone.com',
        ]);

        $this->assertDatabaseHas('dns_records', [
            'dns_zone_id' => $zone->id,
            'name' => '@',
            'type' => 'A',
        ]);

        $this->assertDatabaseHas('dns_records', [
            'dns_zone_id' => $zone->id,
            'name' => '@',
            'type' => 'MX',
            'priority' => 10,
        ]);
    }

    public function test_dns_record_ipv4_validation(): void
    {
        $manager = app(DnsManager::class);

        $this->expectException(ValidationException::class);
        $manager->validateRecord('A', 'not-a-valid-ip');
    }

    public function test_dns_record_mx_priority_validation(): void
    {
        $manager = app(DnsManager::class);

        $this->expectException(ValidationException::class);
        $manager->validateRecord('MX', 'mail.example.com', null);
    }
}
