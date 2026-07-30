<?php

namespace App\Filament\Club\Resources\Locations;

use App\Enums\FacilityStatus;
use App\Enums\LocationCorrectionField;
use App\Filament\Club\Resources\Locations\Pages\ManageLocations;
use App\Filament\Concerns\ResolvesClub;
use App\Filament\Forms\Components\LocationMap;
use App\Filament\Forms\Components\WebpUpload;
use App\Models\Club;
use App\Models\ClubLocation;
use App\Models\County;
use App\Models\Facility;
use App\Models\Locality;
use App\Models\Location;
use App\Models\LocationCorrection;
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
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use RuntimeException;

class LocationResource extends Resource
{
    use ResolvesClub;

    /**
     * The `location_choice` value that means "none of these — it really is a new
     * place". An explicit answer, so that saving past a proximity warning is
     * always a decision and never an oversight.
     */
    public const CHOICE_NEW = 'new';

    protected static ?string $model = ClubLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'locație';

    protected static ?string $pluralModelLabel = 'locații';

    protected static ?string $navigationLabel = 'Locații';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Locație')->schema(static::locationFields()),
                        Tab::make('Facilități')->schema(static::facilityFields()),
                    ]),
            ]);
    }

    /**
     * Where the place is and what is taught there.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected static function locationFields(): array
    {
        return [
            Select::make('county')
                ->label('Județ')
                ->options(fn (): array => County::query()->orderBy('name')->pluck('name', 'name')->all())
                ->searchable()
                ->live()
                ->required()
                ->afterStateUpdated(function ($state, $set, Component $livewire): void {
                    $set('city', null);
                    static::forgetResolvedPlace($set);
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
                ->afterStateUpdated(fn ($set) => static::forgetResolvedPlace($set))
                ->rule(static function ($get, ?ClubLocation $record, ?Component $livewire): Closure {
                    return static function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $livewire): void {
                        $club = static::resolveClub($livewire);

                        if ($club instanceof Club && $club->clubLocationAt($get('county'), $get('city'), $value, $record?->getKey()) !== null) {
                            $fail('Ai deja o locație la această adresă.');
                        }
                    };
                })
                // The search button below is optional, and imports never press it.
                // This runs on every save: if the pin lands next to places that
                // are probably the same one, the club has to say which — or say
                // out loud that this is a new place.
                ->rule(static function ($get, ?ClubLocation $record): Closure {
                    return static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $candidates = static::unresolvedNeighbours($get, $record);

                        if ($candidates->isEmpty()) {
                            return;
                        }

                        $fail(
                            'Există deja '.trans_choice('{1} o locație|[2,*] :count locații', $candidates->count())
                            .' la mai puțin de '.Location::NEARBY_METERS.' m: '
                            .$candidates->map(fn (Location $location): string => '„'.$location->name.'"')->implode(', ')
                            .'. Apasă „Caută" și alege locația potrivită, sau confirmă că este una nouă.',
                        );
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

                            $geocoded = app(Geocoder::class)->geocode(
                                static::composeAddress($get('address'), $get('city'), $get('county')),
                            );

                            if ($geocoded === null) {
                                Notification::make()
                                    ->warning()
                                    ->title('Adresa nu a putut fi găsită')
                                    ->body('Verifică strada, orașul și județul, apoi încearcă din nou.')
                                    ->send();

                                return;
                            }

                            $set('google_place_id', $geocoded->placeId);
                            $set('location', $geocoded->coordinates());

                            // Google's place id, then the exact address: both mean
                            // "certainly the same place", so the shared record is
                            // adopted without asking. Its canonical name comes with it.
                            $shared = Location::exactMatch(
                                $geocoded->placeId,
                                $get('county'),
                                $get('city'),
                                $get('address'),
                            );

                            if ($shared !== null) {
                                static::adoptExistingPlace($set, $shared);

                                Notification::make()
                                    ->warning()
                                    ->title(static::isAlreadyMine($shared, $livewire, $record) ? 'Ai deja această locație' : 'Locație existentă')
                                    ->body(static::isAlreadyMine($shared, $livewire, $record)
                                        ? '„'.$shared->name.'" e deja în lista ta.'
                                        : '„'.$shared->name.'" există deja — o vei folosi cu numele ei.')
                                    ->send();
                            } else {
                                // Nothing certain. Anything close by is only a
                                // question: two halls can stand 50m apart, so the
                                // club decides, never the radius.
                                $candidates = Location::nearby(
                                    $geocoded->latitude,
                                    $geocoded->longitude,
                                    excludeId: $record?->location_id,
                                );

                                static::offerNeighbours($set, $candidates);

                                if ($candidates->isNotEmpty()) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Am găsit locații foarte apropiate')
                                        ->body('Alege locația potrivită din listă, sau confirmă că este una nouă.')
                                        ->persistent()
                                        ->send();
                                }
                            }

                            $livewire->dispatch(
                                'location-map-goto',
                                lat: $geocoded->latitude,
                                lng: $geocoded->longitude,
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
            Radio::make('location_choice')
                ->label('Este una dintre locațiile deja existente?')
                ->helperText('Dacă alegi una existentă, o vei folosi cu numele ei — locațiile sunt partajate între cluburi.')
                ->options(fn ($get): array => static::candidateOptions($get('nearby_candidates')))
                ->visible(fn ($get): bool => filled($get('nearby_candidates')))
                ->required(fn ($get): bool => filled($get('nearby_candidates')))
                ->live()
                ->afterStateUpdated(function ($state, $set): void {
                    // Anything that is not an id — CHOICE_NEW, or nothing yet —
                    // means the club is not adopting an existing place.
                    if (! is_numeric($state)) {
                        $set('chosen_location_id', null);
                        $set('name_locked', false);

                        return;
                    }

                    $chosen = Location::query()->whereKey((int) $state)->first();

                    if ($chosen !== null) {
                        static::adoptExistingPlace($set, $chosen);
                        $set('location_choice', (string) $chosen->getKey());
                    }
                })
                ->dehydrated(false)
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
            // The shared location the club explicitly settled on when the address
            // alone was ambiguous. Distinct from `known_location_id`, which only
            // says which place the facilities tab is talking about and must not
            // override an address the club is deliberately changing.
            Hidden::make('chosen_location_id')
                ->dehydrated(),
            Hidden::make('google_place_id')
                ->dehydrated(),
            Hidden::make('nearby_candidates')
                ->dehydrated(false),
        ];
    }

    /**
     * Drop everything the last address resolution concluded. Called whenever the
     * county, city or street changes, so a stale answer can never be saved
     * against a new address.
     */
    protected static function forgetResolvedPlace(callable $set): void
    {
        $set('name_locked', false);
        $set('known_location_id', null);
        $set('chosen_location_id', null);
        $set('location_choice', null);
        $set('nearby_candidates', null);
        $set('google_place_id', null);
    }

    /**
     * Take on a shared location: its canonical name, locked, and its id recorded
     * as both the facilities context and the club's explicit choice.
     */
    protected static function adoptExistingPlace(callable $set, Location $location): void
    {
        $set('name', $location->name);
        $set('name_locked', true);
        $set('known_location_id', $location->getKey());
        $set('chosen_location_id', $location->getKey());
        $set('nearby_candidates', null);
    }

    /**
     * Put the nearby places in front of the club as a question to answer.
     *
     * @param  Collection<int, Location>  $candidates
     */
    protected static function offerNeighbours(callable $set, Collection $candidates): void
    {
        $set('name_locked', false);
        $set('known_location_id', null);
        $set('chosen_location_id', null);
        $set('location_choice', null);
        $set('nearby_candidates', $candidates->isEmpty() ? null : $candidates
            ->mapWithKeys(fn (Location $location): array => [
                (string) $location->getKey() => $location->name.' — la '.round((float) $location->distance_meters).' m'
                    .' ('.trans_choice('{0} niciun club|{1} un club|[2,*] :count cluburi', $location->clubLocations()->count()).')',
            ])
            ->all());
    }

    /**
     * @param  mixed  $candidates  the `nearby_candidates` form state
     * @return array<string, string>
     */
    protected static function candidateOptions(mixed $candidates): array
    {
        $options = is_array($candidates) ? $candidates : [];

        return $options + [self::CHOICE_NEW => 'Niciuna — este o locație nouă'];
    }

    /**
     * Nearby places the club has neither adopted nor waved off, if any.
     *
     * @return Collection<int, Location>
     */
    protected static function unresolvedNeighbours(callable $get, ?ClubLocation $record): Collection
    {
        return static::neighboursNeedingAnswer([
            'location' => $get('location'),
            'google_place_id' => $get('google_place_id'),
            'county' => $get('county'),
            'city' => $get('city'),
            'address' => $get('address'),
            'chosen_location_id' => $get('chosen_location_id'),
            'location_choice' => $get('location_choice'),
        ], $record?->location_id);
    }

    /**
     * Nearby places that still need a human answer before this form may be saved.
     * Empty whenever the address is already certain, there is no pin to compare
     * against, or the club has answered — by picking one, or by saying out loud
     * that this is a new place.
     *
     * Takes plain state rather than the form, so the rule that guards saving and
     * the button that offers the choice cannot drift apart.
     *
     * @param  array<string, mixed>  $state
     * @return Collection<int, Location>
     */
    public static function neighboursNeedingAnswer(array $state, ?int $excludeLocationId = null): Collection
    {
        if (filled($state['chosen_location_id'] ?? null) || ($state['location_choice'] ?? null) === self::CHOICE_NEW) {
            return new Collection;
        }

        $latitude = $state['location']['lat'] ?? null;
        $longitude = $state['location']['lng'] ?? null;

        // No pin means no proximity question to ask; `name` and `address` are
        // required anyway, so nothing gets in silently.
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return new Collection;
        }

        // A certain match needs no question: syncLocation will reuse that record.
        $certain = Location::exactMatch(
            is_string($state['google_place_id'] ?? null) ? $state['google_place_id'] : null,
            $state['county'] ?? null,
            $state['city'] ?? null,
            $state['address'] ?? null,
        );

        if ($certain !== null) {
            return new Collection;
        }

        return Location::nearby((float) $latitude, (float) $longitude, excludeId: $excludeLocationId);
    }

    /**
     * Whether this club is already present at the given shared location, ignoring
     * the presence currently being edited.
     */
    protected static function isAlreadyMine(Location $location, ?Component $livewire, ?ClubLocation $record): bool
    {
        $club = static::resolveClub($livewire);

        return $club instanceof Club
            && $club->clubLocations()
                ->where('location_id', $location->getKey())
                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                ->exists();
    }

    /**
     * The amenities of the shared place. A club may add missing ones — either
     * picking from the vocabulary or proposing a new entry, which is created as
     * pending and attached here at the same time.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected static function facilityFields(): array
    {
        return [
            Placeholder::make('existing_facilities')
                ->label('Facilitățile locației')
                ->content(fn ($get): string => static::existingFacilitiesLabel($get('known_location_id')))
                ->columnSpanFull(),
            Select::make('new_facilities')
                ->label('Adaugă facilități')
                ->helperText(fn ($get): string => filled($get('known_location_id'))
                    ? 'Locația e partajată cu alte cluburi: poți adăuga facilități, dar nu le poți elimina pe cele existente. O facilitate propusă de tine e vizibilă public doar după ce o aprobă un administrator.'
                    : 'Salvează întâi locația. După aceea poți alege facilități din listă sau propune una nouă, cu poză.')
                ->options(fn ($get): array => static::addableFacilities(
                    $get('known_location_id'),
                    $get('new_facilities'),
                ))
                ->multiple()
                ->searchable()
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Denumire')
                        ->required()
                        ->maxLength(255)
                        // ignoreRecord must stay off: inside an edit form Filament
                        // defaults it on, and the record here is the ClubLocation
                        // being edited — which would filter `facilities` by
                        // `club_location.id`. This modal only ever creates.
                        ->unique(table: Facility::class, column: 'name', ignoreRecord: false),
                    TextInput::make('icon')
                        ->label('Emoji')
                        ->maxLength(16),
                    WebpUpload::make('proof_photo')
                        ->label('Poză cu facilitatea')
                        ->helperText('Fotografiaz-o chiar la această locație. Administratorul se uită la poză înainte să aprobe, așa că e obligatorie.')
                        ->square(1200)
                        ->disk('s3')
                        ->directory('facilities/proof')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->createOptionModalHeading('Propune o facilitate nouă')
                ->createOptionUsing(function (array $data, $get, $set, ?Component $livewire): int {
                    $facilityId = static::createSuggestedFacility($data, static::resolveClub($livewire));
                    $photo = is_string($data['proof_photo'] ?? null) ? $data['proof_photo'] : null;
                    $locationId = $get('known_location_id');

                    // Attach as soon as it is proposed, whenever the place is
                    // already known. Waiting for the form to be saved meant that
                    // abandoning it left the suggestion with no location and no
                    // photo — reaching the admin stripped of the very evidence
                    // the proof requirement exists to collect.
                    if (filled($locationId)) {
                        Location::query()->whereKey($locationId)->firstOrFail()
                            ->facilities()
                            ->syncWithoutDetaching([$facilityId => ['photo_path' => $photo]]);
                    }

                    // Still remembered for the save, for a location that does
                    // not exist yet and therefore has nothing to attach to.
                    $photos = $get('new_facility_photos');
                    $photos = is_array($photos) ? $photos : [];
                    $photos[$facilityId] = $photo;
                    $set('new_facility_photos', $photos);

                    return $facilityId;
                })
                // Filament defaults this to a bare "+" icon glued to the field,
                // which reads as decoration — a labelled button says what it does.
                ->createOptionAction(fn (Action $action): Action => $action
                    ->label('Nu găsesc facilitatea — propune una nouă')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->button()
                    ->outlined()
                    ->color('primary')
                    // Hidden until the place exists: a proposal made before that
                    // has nowhere to attach, so abandoning the form would leave
                    // the admin a suggestion with no location and no photo.
                    ->visible(fn ($get): bool => filled($get('known_location_id'))))
                ->columnSpanFull(),
            Hidden::make('new_facility_photos')
                ->dehydrated(),
        ];
    }

    /**
     * Add a club's proposal to the shared vocabulary. The club chooses neither
     * the status nor the credit: a new entry always lands pending, stamped with
     * who asked for it, and reaches visitors only once an admin approves it.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createSuggestedFacility(array $data, ?Club $club): int
    {
        return Facility::create([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
            'status' => FacilityStatus::Pending,
            'suggested_by_club_id' => $club?->getKey(),
            'sort_order' => 0,
        ])->getKey();
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
                static::proposeCorrectionAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Ask an administrator to fix a field of the shared location.
     *
     * The escape valve that makes the duplicate check safe: a club cannot edit a
     * place other clubs rely on, so without a way to report a wrong record its
     * only remaining move would be to create a duplicate on purpose.
     */
    public static function proposeCorrectionAction(): Action
    {
        return Action::make('proposeCorrection')
            ->label('Propune o corecție')
            ->icon(Heroicon::OutlinedFlag)
            ->color('gray')
            ->modalHeading('Propune o corecție')
            ->modalDescription('Locația e partajată cu alte cluburi, așa că nu îi poți schimba datele direct. Un administrator verifică propunerea înainte să se vadă public.')
            ->modalSubmitActionLabel('Trimite propunerea')
            ->schema([
                Placeholder::make('current')
                    ->label('Datele actuale')
                    ->content(fn (ClubLocation $record): string => implode(' · ', array_filter([
                        $record->location?->name,
                        $record->location?->address,
                        $record->location?->city,
                        $record->location?->county,
                    ]))),
                Select::make('field')
                    ->label('Ce este greșit?')
                    ->options(LocationCorrectionField::options())
                    ->required(),
                TextInput::make('suggested_value')
                    ->label('Valoarea corectă')
                    ->required()
                    ->maxLength(255),
                Textarea::make('note')
                    ->label('De unde știi? (opțional)')
                    ->helperText('Un administrator citește asta înainte să aplice corecția.')
                    ->maxLength(1000),
            ])
            ->action(function (array $data, ClubLocation $record): void {
                LocationCorrection::create([
                    'location_id' => $record->location_id,
                    'club_id' => $record->club_id,
                    'field' => $data['field'],
                    'suggested_value' => $data['suggested_value'],
                    'note' => $data['note'] ?? null,
                ]);

                Notification::make()
                    ->success()
                    ->title('Propunerea a fost trimisă')
                    ->body('Îți mulțumim — un administrator o verifică în curând.')
                    ->send();
            });
    }

    /**
     * Persist a club's presence at a location from submitted form data, reusing
     * or creating the shared location and syncing the sports taught there.
     *
     * @param  array<string, mixed>  $data
     * @param  Club|null  $club  the owning club when there is no panel context to read it from
     */
    public static function persist(
        array $data,
        ?ClubLocation $record = null,
        ?Component $livewire = null,
        ?Club $club = null,
    ): ClubLocation {
        $club ??= $record instanceof ClubLocation ? $record->club : static::resolveClub($livewire);

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
                'google_place_id' => is_string($data['google_place_id'] ?? null) ? $data['google_place_id'] : null,
            ],
            $data['sports'] ?? [],
            $record,
            static::chosenLocationId($data),
        );

        // Facilities belong to the shared location and are only ever added,
        // never removed (other clubs rely on them too). A newly proposed one
        // carries the photo that proves it exists here.
        $newFacilities = is_array($data['new_facilities'] ?? null) ? $data['new_facilities'] : [];

        if ($newFacilities !== []) {
            $photos = is_array($data['new_facility_photos'] ?? null) ? $data['new_facility_photos'] : [];
            $attach = [];

            foreach ($newFacilities as $facilityId) {
                $attach[(int) $facilityId] = ['photo_path' => $photos[$facilityId] ?? null];
            }

            $clubLocation->location->facilities()->syncWithoutDetaching($attach);
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
            // Left empty on purpose: a club editing this form may be deliberately
            // moving its presence to another address, and a pre-filled choice
            // would pin it to the old place and swallow that edit.
            'chosen_location_id' => null,
            'location_choice' => null,
            'nearby_candidates' => null,
            'google_place_id' => $location?->google_place_id,
            'new_facilities' => [],
            'new_facility_photos' => [],
            'sports' => $record->sports->pluck('id')->all(),
        ]);
    }

    /**
     * The shared location the club settled on, if the form asked it to choose.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function chosenLocationId(array $data): ?int
    {
        $chosen = $data['chosen_location_id'] ?? null;

        return is_numeric($chosen) ? (int) $chosen : null;
    }

    /**
     * The facilities already on the shared location, with the club's own
     * unreviewed suggestions marked so it knows they are not public yet.
     */
    protected static function existingFacilitiesLabel(mixed $locationId): string
    {
        if (blank($locationId)) {
            return 'Salvează locația, apoi îi poți adăuga facilități.';
        }

        $tenant = Filament::getTenant();

        $names = Facility::constrainUsable(
            Facility::query(),
            $tenant instanceof Club ? $tenant : null,
        )
            ->whereHas('locations', fn (Builder $query) => $query->whereKey($locationId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Facility $facility): string => trim(($facility->icon ?? '').' '.$facility->name)
                .($facility->isPending() ? ' (în așteptare)' : ''));

        return $names->isEmpty() ? 'Nicio facilitate încă' : $names->implode(' · ');
    }

    /**
     * Facilities that can still be added to the location (all minus existing).
     * A club also sees its own pending suggestions, so it can use one straight
     * away instead of waiting for the review.
     *
     * Anything currently picked in the field stays in the list even once it is
     * attached: a newly proposed facility is attached the moment it is created,
     * and dropping it from the options would leave the select with a value it
     * has no label for — which renders as a bare id.
     *
     * @param  mixed  $selected  ids currently chosen in the field
     * @return array<int, string>
     */
    public static function addableFacilities(mixed $locationId, mixed $selected = null): array
    {
        $tenant = Filament::getTenant();

        $query = Facility::constrainUsable(
            Facility::query(),
            $tenant instanceof Club ? $tenant : null,
        )
            ->orderBy('sort_order')
            ->orderBy('name');

        if (filled($locationId)) {
            $selectedIds = is_array($selected) ? array_map(intval(...), $selected) : [];

            $query->where(function (Builder $addable) use ($locationId, $selectedIds): void {
                $addable->whereDoesntHave('locations', fn (Builder $sub) => $sub->whereKey($locationId));

                if ($selectedIds !== []) {
                    $addable->orWhereIn('facilities.id', $selectedIds);
                }
            });
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
        $geocoded = app(Geocoder::class)->geocode($address);

        if ($geocoded === null) {
            return false;
        }

        // The place id is deliberately not kept: this resolves a county or a city,
        // and Google's identity for those is not the identity of a venue in them.
        $set('location', $geocoded->coordinates());

        $livewire->dispatch(
            'location-map-goto',
            lat: $geocoded->latitude,
            lng: $geocoded->longitude,
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
