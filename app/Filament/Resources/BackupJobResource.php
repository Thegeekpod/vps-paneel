<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BackupJobResource\Pages;
use App\Models\BackupJob;
use App\Models\BackupRepository;
use App\Models\Website;
use App\Services\BackupManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BackupJobResource extends Resource
{
    protected static ?string $model = BackupJob::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        // Ensure default repository exists
        $defaultRepo = BackupRepository::firstOrCreate(
            ['name' => 'Local Encrypted Repository'],
            [
                'type' => 'local',
                'path_or_uri' => config('vps.paths.restic_repo_dir', '/var/backups/restic'),
                'is_initialized' => true,
            ]
        );

        return $form
            ->schema([
                Forms\Components\Section::make('Backup Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Backup Job Name')
                            ->placeholder('e.g. Daily Website & DB Backup')
                            ->required(),

                        Forms\Components\Select::make('website_id')
                            ->label('Target Website (Optional)')
                            ->relationship('website', 'primary_domain')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to back up full server configurations'),

                        Forms\Components\Select::make('backup_repository_id')
                            ->label('Backup Repository')
                            ->relationship('repository', 'name')
                            ->default($defaultRepo->id)
                            ->required(),

                        Forms\Components\Select::make('frequency')
                            ->label('Schedule Frequency')
                            ->options([
                                'daily' => 'Daily (Runs every 24h)',
                                'weekly' => 'Weekly (Runs every 7 days)',
                                'monthly' => 'Monthly (Runs every 30 days)',
                                'manual' => 'Manual Trigger Only',
                            ])
                            ->default('daily')
                            ->required(),

                        Forms\Components\Toggle::make('include_files')
                            ->label('Include Website Files')
                            ->default(true),

                        Forms\Components\Toggle::make('include_databases')
                            ->label('Include Database SQL Dumps')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Restic Prune & Retention Policy')
                    ->description('Automatically prune old snapshots according to retention rules.')
                    ->schema([
                        Forms\Components\TextInput::make('retention_daily')
                            ->label('Keep Daily Snapshots')
                            ->numeric()
                            ->default(7)
                            ->required(),

                        Forms\Components\TextInput::make('retention_weekly')
                            ->label('Keep Weekly Snapshots')
                            ->numeric()
                            ->default(4)
                            ->required(),

                        Forms\Components\TextInput::make('retention_monthly')
                            ->label('Keep Monthly Snapshots')
                            ->numeric()
                            ->default(6)
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Job Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('website.primary_domain')
                    ->label('Target Website')
                    ->placeholder('Entire Node'),

                Tables\Columns\TextColumn::make('frequency')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('snapshots_count')
                    ->counts('snapshots')
                    ->label('Snapshots')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->placeholder('Never run'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('run_now')
                    ->label('Run Backup')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (BackupJob $record, BackupManager $manager) {
                        try {
                            $snapshot = $manager->runJob($record);
                            Notification::make()
                                ->title('Backup Completed')
                                ->body("Snapshot #{$snapshot->snapshot_id} created successfully")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Backup Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackupJobs::route('/'),
            'create' => Pages\CreateBackupJob::route('/create'),
            'edit' => Pages\EditBackupJob::route('/{record}/edit'),
        ];
    }
}
