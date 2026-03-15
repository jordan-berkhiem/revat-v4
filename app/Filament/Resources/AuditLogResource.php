<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\Admin;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('Admin')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('action')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('resource_type')
                    ->label('Target Type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('resource_id')
                    ->label('Target ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('metadata')
                    ->label('Details')
                    ->getStateUsing(fn (AuditLog $record) => $record->metadata ? json_encode($record->metadata) : null)
                    ->placeholder('None')
                    ->limit(50),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('admin_id')
                    ->label('Admin')
                    ->options(fn () => Admin::pluck('name', 'id')->toArray()),
                Tables\Filters\SelectFilter::make('action')
                    ->options(fn () => AuditLog::distinct()->pluck('action', 'action')->toArray()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
