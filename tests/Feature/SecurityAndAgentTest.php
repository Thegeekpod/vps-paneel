<?php

namespace Tests\Feature;

use App\Models\ServerOperation;
use App\Models\Website;
use App\Services\FileManagerService;
use App\Services\ServerAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Tests\TestCase;

class SecurityAndAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_vps_agent_rejects_command_injection(): void
    {
        $agent = app(ServerAgentService::class);

        $this->expectException(\RuntimeException::class);
        $agent->execute('CreateWebsite', [
            'domain' => 'test.com; rm -rf /',
            'username' => 'attacker',
        ]);
    }

    public function test_vps_agent_rejects_reserved_system_users(): void
    {
        $agent = app(ServerAgentService::class);

        $this->expectException(\RuntimeException::class);
        $agent->execute('CreateWebsite', [
            'domain' => 'test.com',
            'username' => 'root',
        ]);
    }

    public function test_server_operation_redacts_sensitive_keys(): void
    {
        $payload = [
            'domain' => 'example.com',
            'db_password' => 'SuperSecret123',
            'private_key' => '-----BEGIN RSA PRIVATE KEY----- ...',
            'safe_param' => 'public_value',
        ];

        $redacted = ServerOperation::redactSensitive($payload);

        $this->assertEquals('******** (redacted)', $redacted['db_password']);
        $this->assertEquals('******** (redacted)', $redacted['private_key']);
        $this->assertEquals('public_value', $redacted['safe_param']);
    }

    public function test_file_manager_blocks_path_traversal_attempts(): void
    {
        $website = Website::create([
            'name' => 'Security Test Site',
            'primary_domain' => 'sec-test.com',
            'system_user' => 'u_sectest',
            'document_root' => storage_path('app/sandboxes/sec-test.com/public_html'),
        ]);

        $fileManager = app(FileManagerService::class);

        // Path traversal with ../../etc/passwd
        $resolved = $fileManager->resolveSafePath($website, '../../etc/passwd');
        // The safe resolver strips traversal and confines path strictly to jail root
        $this->assertStringStartsWith(realpath($website->document_root) ?: storage_path('app/sandboxes/sec-test.com'), $resolved);
    }
}
