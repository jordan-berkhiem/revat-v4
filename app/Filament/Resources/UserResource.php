<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Tenants';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_deactivated')
                    ->label('Deactivated')
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Toggle $component, ?User $record) {
                        $component->state($record?->isDeactivated() ?? false);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currentOrganization.name')
                    ->label('Current Organization')
                    ->placeholder('None'),
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->getStateUsing(fn (User $record) => $record->roles->pluck('name')->join(', ') ?: 'None'),
                Tables\Columns\IconColumn::make('is_deactivated')
                    ->label('Deactivated')
                    ->boolean()
                    ->getStateUsing(fn (User $record) => $record->isDeactivated()),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->relationship('roles', 'name'),
                Tables\Filters\TernaryFilter::make('deactivated')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('deactivated_at'),
                        false: fn ($query) => $query->whereNull('deactivated_at'),
                    ),
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
                Section::make('User Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        TextEntry::make('currentOrganization.name')
                            ->label('Current Organization')
                            ->placeholder('None'),
                        IconEntry::make('is_deactivated')
                            ->label('Deactivated')
                            ->boolean()
                            ->getStateUsing(fn (User $record) => $record->isDeactivated()),
                        TextEntry::make('email_verified_at')
                            ->label('Email Verified')
                            ->dateTime()
                            ->placeholder('Not verified'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Organization Memberships')
                    ->schema([
                        TextEntry::make('organizations_list')
                            ->label('Organizations')
                            ->getStateUsing(fn (User $record) => $record->organizations->pluck('name')->join(', '))
                            ->placeholder('No organizations'),
                    ]),
                Section::make('Workspace Access')
                    ->schema([
                        TextEntry::make('workspaces_list')
                            ->label('Workspaces')
                            ->getStateUsing(fn (User $record) => $record->workspaces->pluck('name')->join(', '))
                            ->placeholder('No workspaces'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
