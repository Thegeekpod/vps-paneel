<?php

namespace App\Filament\Resources;

use App\Enums\WebsiteStatus;
use App\Filament\Resources\WebsiteResource\Pages;
use App\Models\Website;
use App\Services\SslManager;
use App\Services\WebsiteManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebsiteResource extends Resource
{
    protected static ?string $model = Website::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Website Information')
                    ->schema([
                        Forms\Components\TextInput::make('primary_domain')
                            ->label('Primary Domain')
                            ->required()
                            ->regex('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i')
                            ->helperText('Fully Qualified Domain Name (e.g. example.com or app.example.com)')
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('name')
                            ->label('Site Label / Title')
                            ->placeholder('My Client Website'),

                        Forms\Components\Select::make('php_version')
                            ->label('PHP Version')
                            ->options([
                                '8.2' => 'PHP 8.2 FPM',
                                '8.3' => 'PHP 8.3 FPM (Recommended)',
                                '8.4' => 'PHP 8.4 FPM',
                            ])
                            ->default(config('vps.default_php_version', '8.3'))
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Administrative Notes')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('primary_domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Website $record): string => $record->name ?: 'Web Application'),

                Tables\Columns\TextColumn::make('system_user')
                    ->label('Linux User')
                    ->fontFamily('mono')
                    ->copyable()
                    ->badge(),

                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(WebsiteStatus $state): string => $state->color()),

                Tables\Columns\IconColumn::make('ssl_enabled')
                    ->label('SSL')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('issue_ssl')
                    ->label('Issue SSL')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Issue Let\'s Encrypt Certificate')
                    ->modalDescription(fn(Website $record) => "Request a free automated TLS certificate for {$record->primary_domain}? Ensure public DNS points to this server IP before proceeding.")
                    ->action(function (Website $record, SslManager $sslManager) {
                        try {
                            $sslManager->issueLetsEncrypt($record);
                            Notification::make()
                                ->title('SSL Certificate Issued')
                                ->body("TLS certificate is now active for {$record->primary_domain}")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('SSL Issuance Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn(Website $record) => $record->status === WebsiteStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Website $record, WebsiteManager $manager) {
                        $manager->suspend($record);
                        Notification::make()->title('Website suspended')->warning()->send();
                    }),

                Tables\Actions\Action::make('unsuspend')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn(Website $record) => $record->status === WebsiteStatus::Suspended)
                    ->action(function (Website $record, WebsiteManager $manager) {
                        $manager->unsuspend($record);
                        Notification::make()->title('Website activated')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Delete Website and Vhost')
                    ->modalDescription('This will permanently delete the Nginx virtual host, PHP-FPM pool, and remove site routing.')
                    ->using(function (Website $record, WebsiteManager $manager) {
                        $manager->delete($record);
                    }),
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
            'index' => Pages\ListWebsites::route('/'),
            'create' => Pages\CreateWebsite::route('/create'),
            'edit' => Pages\EditWebsite::route('/{record}/edit'),
        ];
    }
}
