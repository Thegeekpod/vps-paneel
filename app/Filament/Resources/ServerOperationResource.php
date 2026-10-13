<?php

namespace App\Filament\Resources;

use App\Enums\OperationStatus;
use App\Filament\Resources\ServerOperationResource\Pages;
use App\Models\ServerOperation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServerOperationResource extends Resource
{
    protected static ?string $model = ServerOperation::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationLabel = 'Audit Logs & Operations';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Operation Details')
                    ->schema([
                        Forms\Components\TextInput::make('action')
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->disabled(),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('Client IP')
                            ->disabled(),

                        Forms\Components\TextInput::make('duration_ms')
                            ->label('Execution Time')
                            ->suffix('ms')
                            ->disabled(),

                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->columnSpanFull()
                            ->disabled()
                            ->visible(fn(?ServerOperation $record) => ! empty($record?->error_message)),

                        Forms\Components\KeyValue::make('payload')
                            ->label('Sanitized Payload')
                            ->columnSpanFull()
                            ->disabled(),

                        Forms\Components\KeyValue::make('result')
                            ->label('Execution Result')
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Operation')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(OperationStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('target_type')
                    ->label('Target')
                    ->formatStateUsing(fn(ServerOperation $record) => $record->target_type ? "{$record->target_type} ({$record->target_id})" : '-'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Triggered By')
                    ->placeholder('System / Agent')
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->suffix(' ms')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'running' => 'Running',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServerOperations::route('/'),
            'view' => Pages\ViewServerOperation::route('/{record}'),
        ];
    }
}
