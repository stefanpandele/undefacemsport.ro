<?php

namespace App\Filament\Admin\Resources\Organizations\Schemas;

use App\Enums\OrganizationType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->label('Tip')
                    ->helperText('Decide adresa publică și forma paginii. Nu limitează ce poate publica: un club care își deține baza poate să o și închirieze.')
                    ->options(OrganizationType::options())
                    ->default(OrganizationType::Club)
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->label('Descriere (afișată pe pagina publică)')
                    ->columnSpanFull(),
                TextInput::make('owner_user_id')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
