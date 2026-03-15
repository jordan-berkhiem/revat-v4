<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkspaceResource\Pages;
use App\Models\Organization;
use App\Models\Workspace;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WorkspaceResource extends Resource
{
    protected static ?string $model = Workspace::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static string|\UnitEnum|null $navigationGroup = 'Tenants';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organization')
                    ->options(fn () => Organization::pluck('name', 'id')->toArray()),
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
                Section::make('Workspace Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('organization.name')
                            ->label('Organization'),
                        IconEntry::make('is_default')
                            ->label('Default Workspace')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Assigned Users')
                    ->schema([
                        TextEntry::make('users_list')
                            ->label('Users')
                            ->getStateUsing(fn (Workspace $record) => $record->users->pluck('name')->join(', '))
                            ->placeholder('No users'),
                    ]),
                Section::make('Integrations')
                    ->schema([
                        TextEntry::make('integrations_list')
                            ->label('Integrations')
                            ->getStateUsing(fn (Workspace $record) => $record->integrations->pluck('name')->join(', '))
                            ->placeholder('No integrations'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkspaces::route('/'),
            'view' => Pages\ViewWorkspace::route('/{record}'),
            'edit' => Pages\EditWorkspace::route('/{record}/edit'),
        ];
    }
}
