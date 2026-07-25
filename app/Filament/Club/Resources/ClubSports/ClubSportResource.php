<?php

namespace App\Filament\Club\Resources\ClubSports;

use App\Filament\Club\Resources\ClubSports\Pages\ManageClubSports;
use App\Models\Club;
use App\Models\ClubSport;
use App\Models\Sport;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClubSportResource extends Resource
{
    protected static ?string $model = ClubSport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'sport';

    protected static ?string $pluralModelLabel = 'sporturi';

    protected static ?string $navigationLabel = 'Sporturi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sport_id')
                    ->label('Sport')
                    ->options(fn (?ClubSport $record): array => static::availableSports($record))
                    ->searchable()
                    ->required(),
                FileUpload::make('cover_path')
                    ->label('Cover')
                    ->image()
                    ->disk('s3')
                    ->directory('clubs/sport-covers'),
                Textarea::make('description')
                    ->label('Descriere')
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_path')
                    ->label('Cover')
                    ->disk('s3'),
                TextColumn::make('sport.translated_name')
                    ->label('Sport')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Gate creating a sport behind the club's subscription plan limit. The
     * "New" button hides once the plan's `sports` limit is reached.
     */
    public static function canCreate(): bool
    {
        $club = Filament::getTenant();

        return $club instanceof Club
            && $club->canAddSport()
            && parent::canCreate();
    }

    /**
     * Sports the current club can still add (global taxonomy minus the ones it
     * already offers), keeping the record's own sport selectable while editing.
     *
     * @return array<int, string>
     */
    protected static function availableSports(?ClubSport $record): array
    {
        $club = Filament::getTenant();

        $taken = $club instanceof Club
            ? $club->clubSports()
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->pluck('sport_id')
            : collect();

        return Sport::query()
            ->whereNotIn('id', $taken)
            ->get()
            ->sortBy(fn (Sport $sport): string => $sport->translated_name)
            ->pluck('translated_name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClubSports::route('/'),
        ];
    }
}
