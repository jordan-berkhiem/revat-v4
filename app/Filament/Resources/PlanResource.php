<?php

namespace App\Filament\Resources;

use App\Enums\SupportLevel;
use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin && $admin->support_level->isAtLeast(SupportLevel::Super);
    }

    public static function canEdit(Model $record): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin && $admin->support_level->isAtLeast(SupportLevel::Super);
    }

    public static function canDelete(Model $record): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin && $admin->support_level->isAtLeast(SupportLevel::Super);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                TextInput::make('stripe_price_monthly')
                    ->maxLength(255),
                TextInput::make('stripe_price_yearly')
                    ->maxLength(255),
                TextInput::make('max_workspaces')
                    ->numeric()
                    ->required(),
                TextInput::make('max_integrations_per_workspace')
                    ->numeric()
                    ->required(),
                TextInput::make('max_users')
                    ->numeric()
                    ->required(),
                Toggle::make('is_visible')
                    ->label('Visible'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_workspaces')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_integrations_per_workspace')
                    ->label('Max Integrations')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stripe_price_monthly')
                    ->label('Monthly Price')
                    ->placeholder('N/A'),
                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
