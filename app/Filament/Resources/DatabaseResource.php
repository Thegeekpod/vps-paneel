<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DatabaseResource\Pages;
use App\Models\Database;
use App\Models\Website;
use App\Services\DatabaseManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DatabaseResource extends Resource
{
    protected static ?string $model = Database::class;
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationGroup = 'Services';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Database Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Database Name')
                            ->required()
                            ->regex('/^[a-zA-Z0-9_]{1,64}$/')
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('website_id')
                            ->label('Associated Website (Optional)')
                            ->relationship('website', 'primary_domain')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('charset')
                            ->label('Character Set')
                            ->options([
                                'utf8mb4' => 'utf8mb4 (Recommended)',
                                'utf8' => 'utf8',
                            ])
                            ->default('utf8mb4')
                            ->required(),

                        Forms\Components\Select::make('collation')
                            ->label('Collation')
                            ->options([
                                'utf8mb4_unicode_ci' => 'utf8mb4_unicode_ci',
                                'utf8mb4_general_ci' => 'utf8mb4_general_ci',
                            ])
                            ->default('utf8mb4_unicode_ci')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Create Database User')
                    ->description('Optionally create a dedicated database user with full privileges.')
                    ->schema([
                        Forms\Components\TextInput::make('db_user')
                            ->label('New Username')
                            ->placeholder('e.g. wp_user')
                            ->regex('/^[a-zA-Z0-9_]{1,32}$/'),

                        Forms\Components\TextInput::make('db_password')
                            ->label('User Password')
                            ->password()
                            ->minLength(8)
                            ->revealable(),
                    ])->columns(2)->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Database Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('website.primary_domain')
                    ->label('Linked Website')
                    ->badge()
                    ->placeholder('Standalone Database'),

                Tables\Columns\TextColumn::make('users.username')
                    ->label('Authorized Users')
                    ->badge()
                    ->color('info')
                    ->separator(', '),

                Tables\Columns\TextColumn::make('charset')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('create_user')
                    ->label('Add User')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('username')
                            ->label('Database Username')
                            ->required()
                            ->regex('/^[a-zA-Z0-9_]{1,32}$/'),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->revealable(),
                    ])
                    ->action(function (Database $record, array $data, DatabaseManager $manager) {
                        try {
                            $manager->createUser($data['username'], $data['password'], $record);
                            Notification::make()->title('Database user created and granted privileges')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('User creation failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->using(function (Database $record, DatabaseManager $manager) {
                        $manager->dropDatabase($record);
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
            'index' => Pages\ListDatabases::route('/'),
            'create' => Pages\CreateDatabase::route('/create'),
        ];
    }
}
