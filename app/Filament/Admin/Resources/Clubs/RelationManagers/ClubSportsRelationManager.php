<?php

namespace App\Filament\Admin\Resources\Clubs\RelationManagers;

use App\Filament\Club\Resources\ClubSports\ClubSportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ClubSportsRelationManager extends RelationManager
{
    protected static string $relationship = 'clubSports';

    protected static ?string $title = 'Sporturi';

    public function form(Schema $schema): Schema
    {
        return ClubSportResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return ClubSportResource::table($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
