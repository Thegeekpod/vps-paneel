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
    public ?string $editingFilePath = null;
    public string $fileContent = '';
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
    }

    public function navigateUp(): void
    {
        if (empty($this->currentPath)) {
            return;
        }

        $parts = explode('/', $this->currentPath);
        array_pop($parts);
        $this->currentPath = implode('/', $parts);
    }

    public function editFile(string $path): void
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return;
        }

        try {
            $this->editingFilePath = $path;
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
            Notification::make()->title('File saved successfully')->success()->send();
            $this->editingFilePath = null;
        } catch (\Throwable $e) {
            Notification::make()->title('Save failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function closeEditor(): void
    {
        $this->editingFilePath = null;
        $this->fileContent = '';
    }

    public function createFolderPrompt(): void
    {
        // Handled via Livewire action / modal
    }

    public function deleteItem(string $path): void
    {
        $site = $this->selectedWebsite;
        if (! $site) {
            return;
        }

        try {
            app(FileManagerService::class)->deleteItem($site, $path);
            Notification::make()->title('Item deleted')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Delete failed')->body($e->getMessage())->danger()->send();
        }
    }
}
