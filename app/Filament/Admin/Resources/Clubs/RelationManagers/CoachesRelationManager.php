<?php

namespace App\Filament\Admin\Resources\Clubs\RelationManagers;

use App\Filament\Club\Resources\Coaches\CoachResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CoachesRelationManager extends RelationManager
{
    protected static string $relationship = 'coaches';

    protected static ?string $title = 'Antrenori';

    public function form(Schema $schema): Schema
    {
        return CoachResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return CoachResource::table($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
