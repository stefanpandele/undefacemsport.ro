<?php

namespace App\Filament\Admin\Resources\Locations;

use App\Actions\MergeLocations;
use App\Filament\Admin\Resources\Locations\Pages\ManageLocations;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * The shared physical places, and the tool for repairing the one thing that
 * cannot repair itself: two records for one place.
 *
 * Duplicates are caught at creation now, but a pair that slipped through before
 * that — or one deliberately created because the shared record was wrong — has to
 * be foldable, because every public count on the platform derives from locations.
 */
class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'locație';

    protected static ?string $pluralModelLabel = 'locații';

    protected static ?string $navigationLabel = 'Locații';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nume')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')
                    ->helperText('Schimbarea lui rupe linkurile existente. Preferă o contopire.')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('address')
                    ->label('Stradă și număr')
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Oraș')
                    ->maxLength(255),
                TextInput::make('county')
                    ->label('Județ')
                    ->maxLength(255),
                TextInput::make('google_place_id')
                    ->label('Google place id')
                    ->helperText('Identitatea Google a locului — semnalul cel mai puternic împotriva duplicatelor.')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'organizationLocations',
                'spaces',
                'facilities',
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nume')
                    ->description(fn (Location $record): string => (string) $record->address)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label('Oraș')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('county')
                    ->label('Județ')
                    ->searchable(),
                TextColumn::make('organization_locations_count')
                    ->label('Organizații')
                    ->sortable(),
                TextColumn::make('spaces_count')
                    ->label('Spații')
                    ->sortable(),
                TextColumn::make('facilities_count')
                    ->label('Facilități'),
                TextColumn::make('google_place_id')
                    ->label('Google')
                    ->placeholder('—')
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                static::mergeAction(),
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
     * Fold this location into another. The record acted on is the one that goes
     * away, so the wording has to be unambiguous about which name survives.
     */
    public static function mergeAction(): Action
    {
        return Action::make('merge')
            ->label('Contopește')
            ->icon(Heroicon::OutlinedArrowsPointingIn)
            ->color('warning')
            ->modalHeading('Contopește această locație în alta')
            ->modalDescription('Locația de aici dispare, iar tot ce ține de ea trece la cea pe care o alegi. Adresa vechiului URL va redirecta.')
            ->modalSubmitActionLabel('Contopește')
            ->schema([
                Select::make('winner')
                    ->label('Se păstrează')
                    ->helperText('Numele, adresa și pagina acestei locații rămân cele oficiale.')
                    ->options(fn (Location $record): array => static::mergeCandidates($record))
                    ->searchable()
                    ->required()
                    ->live(),
            ])
            ->action(function (array $data, Location $record, MergeLocations $merge): void {
                $winner = Location::query()->whereKey($data['winner'])->first();

                if ($winner === null || $winner->is($record)) {
                    Notification::make()
                        ->danger()
                        ->title('Alege o altă locație')
                        ->send();

                    return;
                }

                $moved = $merge->handle($winner, $record);

                // No undo — rebuilding the graph would be guesswork — so the trail
                // is what makes a mistaken merge diagnosable.
                Log::info('Locations merged', [
                    'winner' => ['id' => $winner->getKey(), 'slug' => $winner->slug],
                    'loser' => ['id' => $record->getKey(), 'slug' => $record->slug],
                    'moved' => $moved,
                ]);

                Notification::make()
                    ->success()
                    ->title('Locațiile au fost contopite')
                    ->body(static::summarise($moved).' Adresa „/locatii/'.$record->slug.'" redirectează acum.')
                    ->send();
            });
    }

    /**
     * Everywhere this location could be folded into — nearest first, because a
     * duplicate is almost always the record a few metres away.
     *
     * @return array<int, string>
     */
    protected static function mergeCandidates(Location $record): array
    {
        $nearby = $record->latitude !== null && $record->longitude !== null
            ? Location::nearby((float) $record->latitude, (float) $record->longitude, 1000, $record->getKey())
            : Location::query()->whereRaw('1 = 0')->get();

        $rest = Location::query()
            ->whereKeyNot($record->getKey())
            ->when($nearby->isNotEmpty(), fn (Builder $query) => $query->whereKeyNot($nearby->modelKeys()))
            ->when($record->city, fn (Builder $query) => $query->where('city', $record->city))
            ->orderBy('name')
            ->limit(50)
            ->get();

        return $nearby->concat($rest)
            ->mapWithKeys(fn (Location $location): array => [
                $location->getKey() => trim($location->name.' · '.$location->address
                    .($location->distance_meters === null ? '' : ' — la '.round($location->distance_meters).' m')),
            ])
            ->all();
    }

    /**
     * @param  array<string, int>  $moved
     */
    protected static function summarise(array $moved): string
    {
        $parts = collect([
            'presences' => 'organizații',
            'spaces' => 'spații',
            'facilities' => 'facilități',
            'corrections' => 'corecții',
        ])
            ->filter(fn (string $label, string $key): bool => ($moved[$key] ?? 0) > 0)
            ->map(fn (string $label, string $key): string => $moved[$key].' '.$label)
            ->values();

        return $parts->isEmpty() ? 'Nu era nimic de mutat.' : 'S-au mutat: '.$parts->implode(', ').'.';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLocations::route('/'),
        ];
    }
}
