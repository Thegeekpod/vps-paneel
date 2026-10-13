<?php

namespace App\Filament\Resources;

use App\Enums\DomainType;
use App\Filament\Resources\DomainResource\Pages;
use App\Models\Domain;
use App\Models\Website;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Domain Configuration')
                    ->schema([
                        Forms\Components\Select::make('website_id')
                            ->label('Target Website')
                            ->relationship('website', 'primary_domain')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('domain_name')
                            ->label('Domain Name')
                            ->required()
                            ->regex('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i')
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('type')
                            ->label('Routing Type')
                            ->options([
                                'primary' => 'Primary Domain',
                                'alias' => 'Alias / Domain Pointer',
                                'redirect' => 'URL Redirect',
                            ])
                            ->default('alias')
                            ->reactive()
                            ->required(),

                        Forms\Components\TextInput::make('redirect_url')
                            ->label('Destination URL')
                            ->url()
                            ->visible(fn(Forms\Get $get) => $get('type') === 'redirect')
                            ->required(fn(Forms\Get $get) => $get('type') === 'redirect'),

                        Forms\Components\Select::make('redirect_status')
                            ->label('HTTP Redirect Code')
                            ->options([
                                301 => '301 Permanent Redirect',
                                302 => '302 Temporary Redirect',
                            ])
                            ->default(301)
                            ->visible(fn(Forms\Get $get) => $get('type') === 'redirect'),
                    ])->columns(2),
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

                Tables\Columns\TextColumn::make('website.primary_domain')
                    ->label('Assigned Website')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn(DomainType $state) => match ($state) {
                        DomainType::Primary => 'success',
                        DomainType::Alias => 'info',
                        DomainType::Redirect => 'warning',
                    }),

                Tables\Columns\IconColumn::make('ssl_enabled')
                    ->label('SSL')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([
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
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }
}
