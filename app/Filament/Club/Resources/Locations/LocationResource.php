<?php

namespace App\Filament\Club\Resources\Locations;

use App\Filament\Club\Resources\Locations\Pages\ManageLocations;
use App\Filament\Concerns\ResolvesClub;
use App\Filament\Forms\Components\LocationMap;
use App\Models\Club;
use App\Models\ClubLocation;
use App\Models\County;
use App\Models\Facility;
use App\Models\Locality;
use App\Models\Location;
use App\Models\Sport;
use App\Services\Geocoder;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use RuntimeException;

class LocationResource extends Resource
{
    use ResolvesClub;

    protected static ?string $model = ClubLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'locație';

    protected static ?string $pluralModelLabel = 'locații';

    protected static ?string $navigationLabel = 'Locații';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('county')
                    ->label('Județ')
                    ->options(fn (): array => County::query()->orderBy('name')->pluck('name', 'name')->all())
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, $set, Component $livewire): void {
                        $set('city', null);
                        $set('name_locked', false);
                        $set('known_location_id', null);
                        static::geocodeAndGoto($set, $livewire, static::composeAddress(null, null, $state), zoom: 9);
                    }),
                Select::make('city')
                    ->label('Oraș / Localitate')
                    ->options(fn ($get): array => filled($get('county'))
                        ? Locality::query()
                            ->whereRelation('county', 'name', $get('county'))
                            ->orderBy('name')
                            ->pluck('name', 'name')
                            ->all()
                        : [])
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn ($state, $get, $set, Component $livewire) => static::geocodeAndGoto($set, $livewire, static::composeAddress(null, $state, $get('county')), zoom: 12)),
                TextInput::make('address')
                    ->label('Stradă și număr')
                    ->required()
                    ->columnSpanFull()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($set): void {
                        $set('name_locked', false);
                        $set('known_location_id', null);
                    })
                    ->rule(static function ($get, ?ClubLocation $record, ?Component $livewire): Closure {
                        return static function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $livewire): void {
                            $club = static::resolveClub($livewire);

                            if ($club instanceof Club && $club->clubLocationAt($get('county'), $get('city'), $value, $record?->getKey()) !== null) {
                                $fail('Ai deja o locație la această adresă.');
                            }
                        };
                    })
                    ->suffixAction(
                        Action::make('geocode')
                            ->label('Caută')
                            ->icon(Heroicon::OutlinedMagnifyingGlass)
                            ->action(function ($get, $set, Component $livewire, ?ClubLocation $record): void {
                                $missing = collect([
                                    blank($get('county')) ? 'județul' : null,
                                    blank($get('city')) ? 'localitatea' : null,
                                ])->filter();

                                if ($missing->isNotEmpty()) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Completează '.$missing->implode(' și ').' înainte de căutare')
                                        ->send();

                                    return;
                                }

                                $coordinates = app(Geocoder::class)->geocode(
                                    static::composeAddress($get('address'), $get('city'), $get('county')),
                                );

                                if ($coordinates === null) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Adresa nu a putut fi găsită')
                                        ->body('Verifică strada, orașul și județul, apoi încearcă din nou.')
                                        ->send();

                                    return;
                                }

                                // A shared location may already exist at this address (added by
                                // any club): reuse its canonical name and show our own pin.
                                $shared = Location::atAddress($get('county'), $get('city'), $get('address'));

                                if ($shared !== null) {
                                    $set('name', $shared->name);
                                    $set('name_locked', true);

                                    $club = static::resolveClub($livewire);
                                    $alreadyMine = $club instanceof Club
                                        && $club->clubLocationAt($get('county'), $get('city'), $get('address'), $record?->getKey()) !== null;

                                    Notification::make()
                                        ->warning()
                                        ->title($alreadyMine ? 'Ai deja această locație' : 'Locație existentă')
                                        ->body($alreadyMine
                                            ? '„'.$shared->name.'" e deja în lista ta.'
                                            : '„'.$shared->name.'" există deja — o vei folosi cu numele ei.')
                                        ->send();
                                } else {
                                    $set('name_locked', false);
                                }

                                $set('known_location_id', $shared?->getKey());
                                $set('location', $coordinates);

                                $livewire->dispatch(
                                    'location-map-goto',
                                    lat: $coordinates['lat'],
                                    lng: $coordinates['lng'],
                                    zoom: 16,
                                    existing: $shared !== null,
                                );
                            }),
                    ),
                Hidden::make('name_locked')
                    ->dehydrated(false),
                LocationMap::make('location')
                    ->label('Hartă — mută pinul pentru poziția exactă')
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Nume locație')
                    ->required()
                    ->readOnly(fn ($get): bool => (bool) $get('name_locked')),
                Select::make('sports')
                    ->label('Sporturi predate aici')
                    ->helperText('Doar sporturile declarate la clubul tău.')
                    ->options(function (?Component $livewire): array {
                        $club = static::resolveClub($livewire);

                        return $club instanceof Club
                            ? $club->sports->mapWithKeys(fn (Sport $sport): array => [$sport->getKey() => $sport->translated_name])->all()
                            : [];
                    })
                    ->multiple()
                    ->searchable(),
                Hidden::make('known_location_id')
                    ->dehydrated(false),
                Placeholder::make('existing_facilities')
                    ->label('Facilități existente')
                    ->content(fn ($get): string => static::existingFacilitiesLabel($get('known_location_id')))
                    ->visible(fn ($get): bool => filled($get('known_location_id')))
                    ->columnSpanFull(),
                Select::make('new_facilities')
                    ->label('Adaugă facilități')
                    ->helperText('Facilitățile sunt ale locației (partajate): le poți adăuga, dar nu elimina pe cele existente.')
                    ->options(fn ($get): array => static::addableFacilities($get('known_location_id')))
                    ->multiple()
                    ->searchable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location.name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.county')
                    ->label('Județ')
                    ->sortable(),
                TextColumn::make('location.city')
                    ->label('Oraș')
                    ->searchable(),
                TextColumn::make('sports.translated_name')
                    ->label('Sporturi')
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, ClubLocation $record): array => static::fillFromRecord($data, $record))
                    ->using(fn (ClubLocation $record, array $data): ClubLocation => static::persist($data, $record)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Persist a club's presence at a location from submitted form data, reusing
     * or creating the shared location and syncing the sports taught there.
     *
     * @param  array<string, mixed>  $data
     */
    public static function persist(array $data, ?ClubLocation $record = null, ?Component $livewire = null): ClubLocation
    {
        $club = $record instanceof ClubLocation ? $record->club : static::resolveClub($livewire);

        if (! $club instanceof Club) {
            throw new RuntimeException('Nu există context de club pentru salvarea locației.');
        }

        $clubLocation = $club->syncLocation(
            [
                'county' => $data['county'],
                'city' => $data['city'],
                'address' => $data['address'],
                'name' => $data['name'],
                'latitude' => $data['location']['lat'] ?? null,
                'longitude' => $data['location']['lng'] ?? null,
            ],
            $data['sports'] ?? [],
            $record,
        );

        // Facilities belong to the shared location and are only ever added,
        // never removed (other clubs rely on them too).
        if (! empty($data['new_facilities'])) {
            $clubLocation->location->facilities()->syncWithoutDetaching($data['new_facilities']);
        }

        return $clubLocation;
    }

    /**
     * Hydrate the virtual form fields from the club-location's shared location.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillFromRecord(array $data, ClubLocation $record): array
    {
        $location = $record->location;

        return array_merge($data, [
            'county' => $location?->county,
            'city' => $location?->city,
            'address' => $location?->address,
            'name' => $location?->name,
            'location' => $location?->location,
            'name_locked' => true,
            'known_location_id' => $record->location_id,
            'new_facilities' => [],
            'sports' => $record->sports->pluck('id')->all(),
        ]);
    }

    /**
     * Comma-separated names of the facilities already on the shared location.
     */
    protected static function existingFacilitiesLabel(mixed $locationId): string
    {
        if (blank($locationId)) {
            return '—';
        }

        $names = Facility::query()
            ->whereHas('locations', fn (Builder $query) => $query->whereKey($locationId))
            ->orderBy('sort_order')
            ->pluck('name');

        return $names->isEmpty() ? 'Nicio facilitate încă' : $names->implode(', ');
    }

    /**
     * Facilities that can still be added to the location (all minus existing).
     *
     * @return array<int, string>
     */
    protected static function addableFacilities(mixed $locationId): array
    {
        $query = Facility::query()->orderBy('sort_order');

        if (filled($locationId)) {
            $query->whereDoesntHave('locations', fn (Builder $sub) => $sub->whereKey($locationId));
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * Gate creating a location behind the club's subscription plan limit.
     */
    public static function canCreate(): bool
    {
        $club = Filament::getTenant();

        return $club instanceof Club
            && $club->canAddLocation()
            && parent::canCreate();
    }

    /**
     * Build a Google-friendly address string, scoped to Romania.
     */
    protected static function composeAddress(?string $street, ?string $city, ?string $county): string
    {
        return implode(', ', array_filter([$street, $city, $county, 'România']));
    }

    /**
     * Geocode the address; on success set the map pin and tell the map to
     * centre + zoom to the given level. Returns whether it resolved.
     */
    protected static function geocodeAndGoto(callable $set, Component $livewire, string $address, int $zoom): bool
    {
        $coordinates = app(Geocoder::class)->geocode($address);

        if ($coordinates === null) {
            return false;
        }

        $set('location', $coordinates);

        $livewire->dispatch(
            'location-map-goto',
            lat: $coordinates['lat'],
            lng: $coordinates['lng'],
            zoom: $zoom,
        );

        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLocations::route('/'),
        ];
    }
}
