<?php

namespace App\Filament\Admin\Resources\Surfaces;

use App\Filament\Admin\Resources\Surfaces\Pages\ManageSurfaces;
use App\Models\Sport;
use App\Models\Surface;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The vocabulary of what a court is played on, and which sports ask about it.
 *
 * The map is what earns this screen. Which surfaces a sport has decides whether
 * the operator is asked at all — none and the field is absent, one and it stays
 * absent because the answer is already known, two and the question appears along
 * with a filter on the sport's public page. Moving a sport between lists changes
 * all of that, and it should not need a deploy.
 */
class SurfaceResource extends Resource
{
    protected static ?string $model = Surface::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $modelLabel = 'suprafață';

    protected static ?string $pluralModelLabel = 'suprafețe';

    protected static ?string $navigationLabel = 'Suprafețe';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Denumire')
                    ->helperText('Un termen pentru un lucru fizic, nu unul pentru vocabularul fiecărui sport: tenisul zice „iarbă" și fotbalul zice „gazon", dar e aceeași iarbă, iar două rânduri ar tăia orice căutare în două.')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('sort_order')
                    ->label('Ordine')
                    ->helperText('Ordinea în care sunt oferite în formular și în filtrul public.')
                    ->numeric()
                    ->default(0),
                Select::make('sports')
                    ->label('Sporturi jucate pe ea')
                    ->helperText('Un sport cu o singură suprafață nu primește întrebarea — răspunsul e deja știut. De la a doua în sus, apare și câmpul din formular, și filtrul de pe pagina sportului.')
                    // The title attribute is named even though the options are
                    // built by hand: without it the relationship has no column to
                    // select and the field renders nothing.
                    ->relationship('sports', 'name')
                    ->options(fn (): array => Sport::query()
                        ->get()
                        ->sortBy(fn (Sport $sport): string => $sport->translated_name)
                        ->mapWithKeys(fn (Sport $sport): array => [$sport->getKey() => $sport->translated_name])
                        ->all())
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('sports'))
            ->columns([
                TextColumn::make('name')
                    ->label('Denumire')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sports')
                    ->label('Sporturi')
                    ->badge()
                    ->state(fn (Surface $record): array => $record->sports
                        ->map(fn (Sport $sport): string => $sport->translated_name)
                        ->all())
                    ->placeholder('niciunul'),
                // What is actually published on it. A surface nobody has ever
                // chosen is a candidate for removal, and this is how it shows.
                TextColumn::make('spaces_count')
                    ->label('Terenuri')
                    ->counts('spaces')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->sortable(),
            ])
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

    public static function getPages(): array
    {
        return [
            'index' => ManageSurfaces::route('/'),
        ];
    }
}
