<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntegrationResource\Pages;
use App\Models\Integration;
use App\Models\Organization;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_active')
                    ->label('Active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platform')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('workspace.name')
                    ->label('Workspace')
                    ->sortable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->options(fn () => Integration::distinct()->pluck('platform', 'platform')->toArray()),
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organization')
                    ->options(fn () => Organization::pluck('name', 'id')->toArray()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Integration Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('platform'),
                        TextEntry::make('workspace.name')
                            ->label('Workspace'),
                        TextEntry::make('organization.name')
                            ->label('Organization'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        TextEntry::make('sync_interval_minutes')
                            ->label('Sync Interval (min)'),
                        TextEntry::make('last_synced_at')
                            ->label('Last Synced')
                            ->dateTime()
                            ->placeholder('Never'),
                        TextEntry::make('last_sync_status')
                            ->label('Last Sync Status')
                            ->placeholder('N/A'),
                        TextEntry::make('last_sync_error')
                            ->label('Last Sync Error')
                            ->placeholder('None'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Sync Statuses')
                    ->schema([
                        TextEntry::make('sync_statuses_display')
                            ->label('Data Type Statuses')
                            ->getStateUsing(function (Integration $record) {
                                $statuses = $record->sync_statuses ?? [];

                                if (empty($statuses)) {
                                    return 'No sync data';
                                }

                                return collect($statuses)
                                    ->map(fn ($status, $type) => "{$type}: {$status}")
                                    ->join(', ');
                            }),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegrations::route('/'),
            'view' => Pages\ViewIntegration::route('/{record}'),
            'edit' => Pages\EditIntegration::route('/{record}/edit'),
        ];
    }
}
