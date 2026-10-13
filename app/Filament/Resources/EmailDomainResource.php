<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailDomainResource\Pages;
use App\Models\EmailDomain;
use App\Models\ServerSetting;
use App\Services\MailManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailDomainResource extends Resource
{
    protected static ?string $model = EmailDomain::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Services';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Email Domain')
                    ->schema([
                        Forms\Components\TextInput::make('domain_name')
                            ->label('Domain Name')
                            ->required()
                            ->regex('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i')
                            ->unique(ignoreRecord: true),
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

                Tables\Columns\TextColumn::make('mailboxes_count')
                    ->counts('mailboxes')
                    ->label('Mailboxes')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('aliases_count')
                    ->counts('aliases')
                    ->label('Aliases / Forwards')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => $state === 'active' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('add_mailbox')
                    ->label('New Mailbox')
                    ->icon('heroicon-o-envelope-open')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('username')
                            ->label('Mailbox Name (Local part)')
                            ->placeholder('e.g. info or contact')
                            ->required()
                            ->regex('/^[a-z0-9._-]{1,64}$/'),

                        Forms\Components\TextInput::make('password')
                            ->label('Mailbox Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->revealable(),

                        Forms\Components\TextInput::make('quota_mb')
                            ->label('Quota (MB)')
                            ->numeric()
                            ->default(1024)
                            ->required(),
                    ])
                    ->action(function (EmailDomain $record, array $data, MailManager $manager) {
                        try {
                            $manager->createMailbox($record, $data['username'], $data['password'], (int) $data['quota_mb']);
                            Notification::make()->title('Mailbox created successfully')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Failed to create mailbox')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('dns_guidance')
                    ->label('DNS Setup Guide')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->modalHeading(fn(EmailDomain $record) => "DNS Records & Guidance for {$record->domain_name}")
                    ->modalContent(function (EmailDomain $record, MailManager $manager) {
                        $serverIp = ServerSetting::get('server_ip', '127.0.0.1');
                        $guidance = $manager->getDnsGuidance($record, $serverIp);
                        return view('filament.modals.email-dns-guidance', [
                            'domain' => $record,
                            'guidance' => $guidance,
                            'serverIp' => $serverIp,
                        ]);
                    }),

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
            'index' => Pages\ListEmailDomains::route('/'),
            'create' => Pages\CreateEmailDomain::route('/create'),
        ];
    }
}
