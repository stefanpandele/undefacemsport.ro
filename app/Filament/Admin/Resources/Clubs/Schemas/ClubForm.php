<?php

namespace App\Filament\Admin\Resources\Clubs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('owner_user_id')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
