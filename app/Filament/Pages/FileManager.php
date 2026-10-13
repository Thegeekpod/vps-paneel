<?php

namespace App\Filament\Pages;

use App\Models\Website;
use App\Services\FileManagerService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

class FileManager extends Page implements HasForms
{
    use InteractsWithForms, WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?string $navigationLabel = 'File Manager';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.file-manager';

    public ?int $selectedWebsiteId = null;
    public string $currentPath = '';

    // File Editor State
    public ?string $editingFilePath = null;
    public string $editingFileName = '';
    public string $fileContent = '';

    // Creation & Rename Modals State
    public bool $showNewFileModal = false;
    public string $newFileName = '';

    public bool $showNewFolderModal = false;
    public string $newFolderName = '';

    public bool $showRenameModal = false;
    public string $renameTargetPath = '';
    public string $renameNewName = '';

    // Upload State
    public $uploadedFile = null;

    public function mount(): void
    {
        $firstSite = Website::first();
        if ($firstSite) {
            $this->selectedWebsiteId = $firstSite->id;
        }
    }

    public function getSelectedWebsiteProperty(): ?Website
    {
        return $this->selectedWebsiteId ? Website::find($this->selectedWebsiteId) : null;
    }

    public function getFilesProperty(): array
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return [];
        }

        try {
            return app(FileManagerService::class)->listContents($site, $this->currentPath);
        } catch (\Throwable $e) {
            Notification::make()->title('Error reading directory')->body($e->getMessage())->danger()->send();
            $this->currentPath = '';
            return [];
        }
    }

    public function navigateTo(string $path): void
    {
        $this->currentPath = trim($path, '/');
        $this->closeEditor();
    }

    public function navigateUp(): void
    {
        if (empty($this->currentPath)) {
            return;
        }

        $parts = explode('/', $this->currentPath);
        array_pop($parts);
        $this->currentPath = implode('/', $parts);
        $this->closeEditor();
    }

    public function openNewFileModal(): void
    {
        $this->newFileName = '';
        $this->showNewFileModal = true;
    }

    public function createNewFile(): void
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return;
        }

        $fileName = trim($this->newFileName);
        if (empty($fileName)) {
            Notification::make()->title('File name is required')->danger()->send();
            return;
        }

        try {
            $createdPath = app(FileManagerService::class)->createFile($site, $this->currentPath, $fileName);
            $this->showNewFileModal = false;
            $this->newFileName = '';

            Notification::make()->title("File '{$fileName}' created successfully")->success()->send();
            $this->editFile($createdPath);
        } catch (\Throwable $e) {
            Notification::make()->title('Creation failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function openNewFolderModal(): void
    {
        $this->newFolderName = '';
        $this->showNewFolderModal = true;
    }

    public function createNewFolder(): void
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return;
        }

        $folderName = trim($this->newFolderName);
        if (empty($folderName)) {
            Notification::make()->title('Folder name is required')->danger()->send();
            return;
        }

        try {
            app(FileManagerService::class)->createFolder($site, $this->currentPath, $folderName);
            $this->showNewFolderModal = false;
            $this->newFolderName = '';

            Notification::make()->title("Folder '{$folderName}' created successfully")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Creation failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function openRenameModal(string $path, string $currentName): void
    {
        $this->renameTargetPath = $path;
        $this->renameNewName = $currentName;
        $this->showRenameModal = true;
    }

    public function executeRename(): void
    {
        $site = $this->selectedWebsite;
        if (! $site || empty($this->renameTargetPath)) {
            return;
        }

        $newName = trim($this->renameNewName);
        if (empty($newName)) {
            Notification::make()->title('New name cannot be empty')->danger()->send();
            return;
        }

        try {
            app(FileManagerService::class)->renameItem($site, $this->renameTargetPath, $newName);
            $this->showRenameModal = false;
            $this->renameTargetPath = '';
            $this->renameNewName = '';

            Notification::make()->title('Item renamed successfully')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Rename failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function editFile(string $path): void
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return;
        }

        try {
            $this->editingFilePath = $path;
            $this->editingFileName = basename($path);
            $this->fileContent = app(FileManagerService::class)->getFileContent($site, $path);
        } catch (\Throwable $e) {
            Notification::make()->title('Cannot open file')->body($e->getMessage())->danger()->send();
        }
    }

    public function saveFile(): void
    {
        $site = $this->selectedWebsite;
        if (! $site || ! $this->editingFilePath) {
            return;
        }

        try {
            app(FileManagerService::class)->putFileContent($site, $this->editingFilePath, $this->fileContent);
            Notification::make()->title("Saved {$this->editingFileName} successfully")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Save failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function closeEditor(): void
    {
        $this->editingFilePath = null;
        $this->editingFileName = '';
        $this->fileContent = '';
    }

    public function deleteItem(string $path): void
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return;
        }

        try {
            app(FileManagerService::class)->deleteItem($site, $path);
            if ($this->editingFilePath === $path) {
                $this->closeEditor();
            }
            Notification::make()->title('Item deleted successfully')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Delete failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function updatedUploadedFile(): void
    {
        $site = $this->selectedWebsite;
        if (! $site || ! $this->uploadedFile) {
            return;
        }

        try {
            app(FileManagerService::class)->uploadFile($site, $this->currentPath, $this->uploadedFile);
            $fileName = $this->uploadedFile->getClientOriginalName();
            $this->uploadedFile = null;
            Notification::make()->title("File '{$fileName}' uploaded successfully")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Upload failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function downloadItem(string $path)
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return;
        }

        try {
            $fullPath = app(FileManagerService::class)->resolveSafePath($site, $path);
            if (! is_file($fullPath)) {
                Notification::make()->title('File not found for download')->danger()->send();
                return;
            }

            return response()->download($fullPath, basename($path));
        } catch (\Throwable $e) {
            Notification::make()->title('Download error')->body($e->getMessage())->danger()->send();
        }
    }

    public function fixPermissions(): void
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return;
        }

        try {
            app(FileManagerService::class)->fixPermissions($site);
            Notification::make()->title('File permissions repaired successfully')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Repair permissions failed')->body($e->getMessage())->danger()->send();
        }
    }
}
