<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use App\Models\Plan;
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

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Tenants';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('timezone')
                    ->maxLength(255),
                Toggle::make('support_access_enabled')
                    ->label('Support Access Enabled')
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable()
                    ->placeholder('No plan'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('workspaces_count')
                    ->label('Workspaces')
                    ->counts('workspaces')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Subscription')
                    ->badge()
                    ->getStateUsing(fn (Organization $record) => $record->subscriptionStatus()->value),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->options(fn () => Plan::pluck('name', 'id')->toArray()),
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
                Section::make('Organization Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('timezone')
                            ->placeholder('Not set'),
                        TextEntry::make('plan.name')
                            ->label('Plan')
                            ->placeholder('No plan'),
                        IconEntry::make('support_access_enabled')
                            ->label('Support Access')
                            ->boolean(),
                        TextEntry::make('subscription_status')
                            ->label('Subscription Status')
                            ->getStateUsing(fn (Organization $record) => $record->subscriptionStatus()->value),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Users')
                    ->schema([
                        TextEntry::make('users_list')
                            ->label('Members')
                            ->getStateUsing(fn (Organization $record) => $record->users->pluck('name')->join(', '))
                            ->placeholder('No users'),
                    ]),
                Section::make('Workspaces')
                    ->schema([
                        TextEntry::make('workspaces_list')
                            ->label('Workspaces')
                            ->getStateUsing(fn (Organization $record) => $record->workspaces->pluck('name')->join(', '))
                            ->placeholder('No workspaces'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'view' => Pages\ViewOrganization::route('/{record}'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
