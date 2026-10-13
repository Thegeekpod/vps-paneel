<?php

namespace Tests\Feature;

use App\Services\PanelUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_current_git_version(): void
    {
        $updater = app(PanelUpdateService::class);
        $version = $updater->getCurrentVersion();

        $this->assertArrayHasKey('branch', $version);
        $this->assertArrayHasKey('hash', $version);
        $this->assertArrayHasKey('message', $version);
    }

    public function test_check_for_updates(): void
    {
        $updater = app(PanelUpdateService::class);
        $check = $updater->checkForUpdates();

        $this->assertArrayHasKey('is_behind', $check);
        $this->assertArrayHasKey('commits_behind', $check);
        $this->assertArrayHasKey('current_hash', $check);
    }
}
