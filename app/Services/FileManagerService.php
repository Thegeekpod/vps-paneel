<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class FileManagerService
{
    public function __construct(
        protected ?ServerAgentService $agentService = null
    ) {
        $this->agentService = $agentService ?? app(ServerAgentService::class);
    }

    /**
     * Get validated absolute path jailed inside website root.
     * Prevents any path traversal or symlink escapes.
     */
    public function resolveSafePath(Website $website, string $relativePath = ''): string
    {
        $root = realpath($website->document_root);
        if (! $root || ! is_dir($root)) {
            // If document root directory does not exist on disk yet (e.g. dev/local), create sandbox
            $root = storage_path("app/sandboxes/{$website->primary_domain}/public_html");
            if (! is_dir($root)) {
                File::makeDirectory($root, 0755, true);
                file_put_contents("{$root}/index.html", "<h1>Welcome to {$website->primary_domain}</h1>");
            }
        }

        $cleanRelative = ltrim(str_replace(['../', '..\\', "\0"], '', $relativePath), '/\\');
        $target = $root . ($cleanRelative !== '' ? DIRECTORY_SEPARATOR . $cleanRelative : '');

        // Canonical verification
        $realTarget = realpath($target);
        if ($realTarget !== false && ! str_starts_with($realTarget, $root)) {
            throw new AccessDeniedException("Access denied: path traversal attempt outside website root");
        }

        return $target;
    }

    /**
     * List files and folders in a relative path.
     */
    public function listContents(Website $website, string $relativePath = ''): array
    {
        $dir = $this->resolveSafePath($website, $relativePath);

        if (! is_dir($dir)) {
            return [];
        }

        $items = [];
        $scanned = scandir($dir) ?: [];

        foreach ($scanned as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($itemPath);
            $size = $isDir ? 0 : (@filesize($itemPath) ?: 0);
            $perms = substr(sprintf('%o', @fileperms($itemPath) ?: 0644), -4);
            $modified = @filemtime($itemPath) ?: time();

            $items[] = [
                'name' => $item,
                'path' => ltrim($relativePath . '/' . $item, '/'),
                'is_dir' => $isDir,
                'size' => $size,
                'formatted_size' => $isDir ? '-' : $this->formatBytes($size),
                'permissions' => $perms,
                'modified_at' => date('Y-m-d H:i:s', $modified),
                'extension' => pathinfo($item, PATHINFO_EXTENSION),
            ];
        }

        // Sort folders first, then files
        usort($items, function ($a, $b) {
            if ($a['is_dir'] === $b['is_dir']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['is_dir'] ? -1 : 1;
        });

        return $items;
    }

    /**
     * Read file contents.
     */
    public function getFileContent(Website $website, string $relativePath): string
    {
        $path = $this->resolveSafePath($website, $relativePath);
        if (! is_file($path)) {
            throw new \InvalidArgumentException("File not found");
        }

        if (filesize($path) > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException("File too large to edit in browser (max 5 MB)");
        }

        return file_get_contents($path) ?: '';
    }

    /**
     * Save file contents.
     */
    public function putFileContent(Website $website, string $relativePath, string $content): void
    {
        $path = $this->resolveSafePath($website, $relativePath);

        // Try direct write first
        if (@file_put_contents($path, $content) !== false) {
            @chmod($path, 0664);
            return;
        }

        // Privileged fallback via vps-agent
        $this->agentService->execute('ManageWebsiteFile', [
            'action' => 'write',
            'path' => $path,
            'content' => $content,
            'user' => $website->system_user,
        ], 'Website', (string) $website->id);
    }

    /**
     * Create new file.
     */
    public function createFile(Website $website, string $relativePath, string $fileName, string $initialContent = ''): string
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
        if (empty($cleanName)) {
            throw new \InvalidArgumentException("Invalid file name");
        }

        $subPath = ltrim($relativePath . '/' . $cleanName, '/');
        $target = $this->resolveSafePath($website, $subPath);

        if (file_exists($target)) {
            throw new \InvalidArgumentException("A file or folder named '{$cleanName}' already exists");
        }

        if (@file_put_contents($target, $initialContent) === false) {
            $this->agentService->execute('ManageWebsiteFile', [
                'action' => 'write',
                'path' => $target,
                'content' => $initialContent,
                'user' => $website->system_user,
            ], 'Website', (string) $website->id);
        } else {
            @chmod($target, 0664);
        }

        return $subPath;
    }

    /**
     * Create directory.
     */
    public function createFolder(Website $website, string $relativePath, string $folderName): void
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '', $folderName);
        if (empty($cleanName)) {
            throw new \InvalidArgumentException("Invalid folder name");
        }

        $target = $this->resolveSafePath($website, $relativePath . '/' . $cleanName);
        if (! is_dir($target)) {
            if (! @mkdir($target, 0775, true)) {
                $this->agentService->execute('ManageWebsiteFile', [
                    'action' => 'mkdir',
                    'path' => $target,
                    'user' => $website->system_user,
                ], 'Website', (string) $website->id);
            }
        }
    }

    /**
     * Rename file or folder.
     */
    public function renameItem(Website $website, string $oldRelativePath, string $newName): void
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '', $newName);
        if (empty($cleanName)) {
            throw new \InvalidArgumentException("Invalid new name");
        }

        $oldTarget = $this->resolveSafePath($website, $oldRelativePath);
        if (! file_exists($oldTarget)) {
            throw new \InvalidArgumentException("Source item not found");
        }

        $parent = dirname($oldRelativePath);
        $newRelativePath = ($parent !== '.' && $parent !== '') ? $parent . '/' . $cleanName : $cleanName;
        $newTarget = $this->resolveSafePath($website, $newRelativePath);

        if (file_exists($newTarget)) {
            throw new \InvalidArgumentException("An item with name '{$cleanName}' already exists");
        }

        if (! @rename($oldTarget, $newTarget)) {
            $this->agentService->execute('ManageWebsiteFile', [
                'action' => 'rename',
                'path' => $oldTarget,
                'new_path' => $newTarget,
                'user' => $website->system_user,
            ], 'Website', (string) $website->id);
        }
    }

    /**
     * Delete file or folder.
     */
    public function deleteItem(Website $website, string $relativePath): void
    {
        $path = $this->resolveSafePath($website, $relativePath);
        $deleted = false;
        if (is_dir($path)) {
            $deleted = @File::deleteDirectory($path);
        } elseif (is_file($path)) {
            $deleted = @unlink($path);
        }

        if (! $deleted && file_exists($path)) {
            $this->agentService->execute('ManageWebsiteFile', [
                'action' => 'delete',
                'path' => $path,
                'user' => $website->system_user,
            ], 'Website', (string) $website->id);
        }
    }

    /**
     * Upload file safely.
     */
    public function uploadFile(Website $website, string $relativePath, UploadedFile $file): void
    {
        $destDir = $this->resolveSafePath($website, $relativePath);
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
        try {
            $file->move($destDir, $fileName);
            @chmod($destDir . DIRECTORY_SEPARATOR . $fileName, 0664);
        } catch (\Throwable $e) {
            $tempPath = storage_path('app/temp_upload_' . uniqid() . '_' . $fileName);
            $file->move(dirname($tempPath), basename($tempPath));
            $targetPath = $destDir . DIRECTORY_SEPARATOR . $fileName;
            $this->agentService->execute('ManageWebsiteFile', [
                'action' => 'write',
                'path' => $targetPath,
                'content' => file_get_contents($tempPath),
                'user' => $website->system_user,
            ], 'Website', (string) $website->id);
            @unlink($tempPath);
        }
    }

    /**
     * Fix permissions for an entire website.
     */
    public function fixPermissions(Website $website): void
    {
        $this->agentService->execute('ManageWebsiteFile', [
            'action' => 'fix_permissions',
            'path' => $website->document_root,
            'user' => $website->system_user,
        ], 'Website', (string) $website->id);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
