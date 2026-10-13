<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Services\FileManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    protected Website $website;
    protected FileManagerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $this->website = Website::create([
            'user_id' => $user->id,
            'name' => 'Test App',
            'primary_domain' => 'testfilemanager.com',
            'system_user' => 'siteuser',
            'document_root' => storage_path('app/sandboxes/testfilemanager.com/public_html'),
            'php_version' => '8.3',
            'status' => 'active',
        ]);

        $this->service = app(FileManagerService::class);
    }

    public function test_create_and_read_and_edit_file(): void
    {
        // 1. Create file
        $relPath = $this->service->createFile($this->website, '', 'hello.txt', 'Initial content');
        $this->assertEquals('hello.txt', $relPath);

        // 2. Read content
        $content = $this->service->getFileContent($this->website, 'hello.txt');
        $this->assertEquals('Initial content', $content);

        // 3. Edit & save content
        $this->service->putFileContent($this->website, 'hello.txt', 'Updated content');
        $updated = $this->service->getFileContent($this->website, 'hello.txt');
        $this->assertEquals('Updated content', $updated);

        // 4. Rename file
        $this->service->renameItem($this->website, 'hello.txt', 'renamed.txt');
        $this->assertEquals('Updated content', $this->service->getFileContent($this->website, 'renamed.txt'));

        // 5. Delete file
        $this->service->deleteItem($this->website, 'renamed.txt');
        $this->assertFalse(file_exists($this->service->resolveSafePath($this->website, 'renamed.txt')));
    }

    public function test_create_and_delete_directory(): void
    {
        $this->service->createFolder($this->website, '', 'subfolder');
        $target = $this->service->resolveSafePath($this->website, 'subfolder');
        $this->assertTrue(is_dir($target));

        $this->service->deleteItem($this->website, 'subfolder');
        $this->assertFalse(is_dir($target));
    }
}
