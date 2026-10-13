<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class FileManagerService
{
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
        file_put_contents($path, $content);
    }

    /**
     * Create directory.
     */
    public function createFolder(Website $website, string $relativePath, string $folderName): void
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '', $folderName);
        $target = $this->resolveSafePath($website, $relativePath . '/' . $cleanName);
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }
    }

    /**
     * Delete file or folder.
     */
    public function deleteItem(Website $website, string $relativePath): void
    {
        $path = $this->resolveSafePath($website, $relativePath);
        if (is_dir($path)) {
            File::deleteDirectory($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Upload file safely.
     */
    public function uploadFile(Website $website, string $relativePath, UploadedFile $file): void
    {
        $destDir = $this->resolveSafePath($website, $relativePath);
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
        $file->move($destDir, $fileName);
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
