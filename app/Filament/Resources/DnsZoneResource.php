<?php

namespace App\Filament\Resources;

use App\Enums\DnsRecordType;
use App\Filament\Resources\DnsZoneResource\Pages;
use App\Models\DnsZone;
use App\Services\DnsManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DnsZoneResource extends Resource
{
    protected static ?string $model = DnsZone::class;
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationGroup = 'Services';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('DNS Zone Information')
                    ->schema([
                        Forms\Components\TextInput::make('domain_name')
                            ->label('Zone Domain Name')
                            ->required()
                            ->regex('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i')
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('website_id')
                            ->label('Linked Website (Optional)')
                            ->relationship('website', 'primary_domain')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('soa_email')
                            ->label('SOA Email')
                            ->default('hostmaster')
                            ->required(),

                        Forms\Components\TextInput::make('default_ttl')
                            ->label('Default TTL (Seconds)')
                            ->numeric()
                            ->default(3600)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('DNS Resource Records')
                    ->description('Manage A, AAAA, CNAME, MX, TXT, NS, and SRV records for this zone.')
                    ->schema([
                        Forms\Components\Repeater::make('records')
                            ->relationship('records')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Host / Name')
                                    ->placeholder('@ or www or mail')
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('type')
                                    ->label('Record Type')
                                    ->options(collect(DnsRecordType::cases())->mapWithKeys(fn($c) => [$c->value => $c->name]))
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('content')
                                    ->label('Content / Value')
                                    ->placeholder('e.g. 192.0.2.1 or mail.example.com.')
                                    ->required()
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->placeholder('10')
                                    ->visible(fn(Forms\Get $get) => in_array($get('type'), ['MX', 'SRV']))
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('ttl')
                                    ->label('TTL')
                                    ->numeric()
                                    ->default(3600)
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => ($state['name'] ?? '@') . ' ' . ($state['type'] ?? 'A') . ' ' . ($state['content'] ?? '')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain_name')
                    ->label('Zone Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('records_count')
                    ->counts('records')
                    ->label('Records')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('website.primary_domain')
                    ->label('Linked Website')
                    ->placeholder('Standalone Zone'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => $state === 'active' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('Sync PowerDNS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (DnsZone $record, DnsManager $manager) {
                        try {
                            $manager->syncZone($record);
                            Notification::make()->title('PowerDNS Zone Synced')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Sync Failed')->body($e->getMessage())->danger()->send();
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
            'index' => Pages\ListDnsZones::route('/'),
            'create' => Pages\CreateDnsZone::route('/create'),
            'edit' => Pages\EditDnsZone::route('/{record}/edit'),
        ];
    }
}
