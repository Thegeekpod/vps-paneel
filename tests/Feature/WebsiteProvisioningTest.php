<?php

namespace Tests\Feature;

use App\Enums\WebsiteStatus;
use App\Models\Website;
use App\Services\WebsiteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_creation_flow(): void
    {
        $manager = app(WebsiteManager::class);

        $website = $manager->create([
            'primary_domain' => 'clientdomain.com',
            'name' => 'Client App',
            'php_version' => '8.3',
        ]);

        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'primary_domain' => 'clientdomain.com',
            'status' => WebsiteStatus::Active->value,
        ]);

        $this->assertDatabaseHas('domains', [
            'website_id' => $website->id,
            'domain_name' => 'clientdomain.com',
            'type' => 'primary',
        ]);
    }

    public function test_website_suspend_and_unsuspend(): void
    {
        $manager = app(WebsiteManager::class);

        $website = $manager->create([
            'primary_domain' => 'mycompany.org',
            'name' => 'My Company',
        ]);

        $manager->suspend($website);
        $this->assertEquals(WebsiteStatus::Suspended, $website->fresh()->status);

        $manager->unsuspend($website);
        $this->assertEquals(WebsiteStatus::Active, $website->fresh()->status);
    }

    public function test_website_deletion(): void
    {
        $manager = app(WebsiteManager::class);

        $website = $manager->create([
            'primary_domain' => 'delete-me.io',
        ]);

        $manager->delete($website);
        $this->assertDatabaseMissing('websites', ['primary_domain' => 'delete-me.io']);
    }
}
