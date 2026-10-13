<?php

namespace App\Filament\Resources;

use App\Enums\SslProvider;
use App\Enums\SslStatus;
use App\Filament\Resources\SslCertificateResource\Pages;
use App\Models\SslCertificate;
use App\Services\SslManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SslCertificateResource extends Resource
{
    protected static ?string $model = SslCertificate::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Services';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Certificate Type')
                    ->schema([
                        Forms\Components\Select::make('website_id')
                            ->label('Target Website')
                            ->relationship('website', 'primary_domain')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('domain_name')
                            ->label('Domain / Common Name')
                            ->required()
                            ->regex('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'),

                        Forms\Components\Select::make('provider')
                            ->label('SSL Provider')
                            ->options([
                                'letsencrypt' => 'Let\'s Encrypt (Automated ACME)',
                                'custom' => 'Custom Certificate (Manual Upload)',
                            ])
                            ->default('letsencrypt')
                            ->reactive()
                            ->required(),

                        Forms\Components\Toggle::make('auto_renew')
                            ->label('Auto-renew certificate before expiration')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Custom SSL Credentials')
                    ->description('Paste your PEM-encoded SSL certificate and matching private key.')
                    ->visible(fn(Forms\Get $get) => $get('provider') === 'custom')
                    ->schema([
                        Forms\Components\Textarea::make('custom_certificate')
                            ->label('Certificate PEM (Includes Intermediate/Chain)')
                            ->placeholder('-----BEGIN CERTIFICATE----- ...')
                            ->rows(6)
                            ->required(fn(Forms\Get $get) => $get('provider') === 'custom'),

                        Forms\Components\Textarea::make('custom_private_key')
                            ->label('Private Key PEM (Unencrypted)')
                            ->placeholder('-----BEGIN RSA PRIVATE KEY----- ...')
                            ->rows(6)
                            ->password()
                            ->required(fn(Forms\Get $get) => $get('provider') === 'custom'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color(fn(SslProvider $state) => $state === SslProvider::LetsEncrypt ? 'success' : 'info'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(SslStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('issuer')
                    ->limit(25)
                    ->placeholder('Pending issue'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expiration')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->description(fn(SslCertificate $record) => $record->isExpiringSoon() ? 'Expiring soon!' : null)
                    ->color(fn(SslCertificate $record) => $record->isExpiringSoon() ? 'danger' : 'gray'),

                Tables\Columns\IconColumn::make('auto_renew')
                    ->label('Auto-Renew')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('renew')
                    ->label('Renew')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn(SslCertificate $record) => $record->provider === SslProvider::LetsEncrypt)
                    ->action(function (SslCertificate $record, SslManager $manager) {
                        try {
                            $manager->issueLetsEncrypt($record->website, $record->domain_name);
                            Notification::make()->title('Certificate renewed successfully')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Renewal failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('renew_all')
                    ->label('Renew All Expiring')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (SslManager $manager) {
                        $manager->renewAll();
                        Notification::make()->title('Renewal process triggered')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSslCertificates::route('/'),
            'create' => Pages\CreateSslCertificate::route('/create'),
        ];
    }
}
