<?php

namespace App\Filament\Admin\Resources\Organizations\RelationManagers;

use App\Filament\Organization\Resources\People\PersonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PeopleRelationManager extends RelationManager
{
    protected static string $relationship = 'people';

    protected static ?string $title = 'Antrenori';

    public function form(Schema $schema): Schema
    {
        return PersonResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return PersonResource::table($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
