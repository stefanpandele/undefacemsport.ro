<?php

namespace App\Filament\Organization\Resources\Spaces;

use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Filament\Concerns\ResolvesOrganization;
use App\Filament\Organization\Resources\Spaces\Pages\ManageSpaces;
use App\Models\Location;
use App\Models\Organization;
use App\Models\ScheduleSlot;
use App\Models\Space;
use App\Models\Sport;
use App\Models\Surface;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * The spaces at an organization's locations: a pool, a pitch, a court, a gym.
 *
 * Publishing one is what makes an organization a venue — nothing is declared, and
 * a club that rents out its dead hours becomes one without ceasing to be a club.
 */
class SpaceResource extends Resource
{
    use ResolvesOrganization;

    protected static ?string $model = Space::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $modelLabel = 'spațiu';

    protected static ?string $pluralModelLabel = 'spații';

    protected static ?string $navigationLabel = 'Spații';

    protected static string|\UnitEnum|null $navigationGroup = 'Agrement și închiriere';

    protected static ?int $navigationSort = 1;

    /**
     * Scoped by hand rather than by Filament's tenancy: a space belongs to a
     * location, and only sometimes to an organization, so there is no
     * `organization_id` for the panel to filter on.
     */
    protected static bool $isScopedToTenant = false;

    /**
     * The most units one submit may create. High enough for the biggest padel
     * club, low enough that a typo in the quantity field is not a disaster.
     */
    private const BULK_LIMIT = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('location_id')
                    ->label('Locația')
                    ->options(fn (?Component $livewire): array => static::locationOptions($livewire))
                    ->searchable()
                    // Live because the quota depends on it: a court at an address
                    // already selling this sport is free.
                    ->live()
                    ->required(),
                Toggle::make('is_operated_by_us')
                    ->label('Îl administrăm noi')
                    ->helperText(fn (?Component $livewire, $get): string => static::atSpaceLimit($livewire, $get)
                        ? 'Ai atins limita din planul tău pentru sporturi noi la adrese noi. Poți în continuare să adaugi terenuri la ce vinzi deja, și spații publice — niciunul nu consumă din limită.'
                        : 'Stins înseamnă spațiu public: îl semnalezi pentru toată lumea, fără să-l administrezi. Nu consumă din limita planului.')
                    // Disabled at the quota rather than hiding the whole form: a
                    // club that has run out of its own spaces may still put a park
                    // court on the map, and the plan has no business stopping it.
                    ->disabled(fn (?Component $livewire, $get): bool => static::atSpaceLimit($livewire, $get))
                    ->default(true)
                    ->dehydrated()
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Nume')
                    ->helperText('Numele acestui teren, nu al sportului — „Teren 1", „Masa 2", „Bazin de înot". Sportul se alege alături.')
                    ->placeholder('Teren 1')
                    ->required()
                    ->maxLength(255),
                Select::make('sport_id')
                    ->label('Sport')
                    ->helperText('Lasă gol pentru un spațiu care nu e un sport — saună, vestiar.')
                    ->options(fn (): array => Sport::query()
                        ->get()
                        ->sortBy(fn (Sport $sport): string => $sport->translated_name)
                        ->mapWithKeys(fn (Sport $sport): array => [$sport->getKey() => $sport->translated_name])
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('surface_id', null)),
                Select::make('surface_id')
                    ->label('Suprafață')
                    ->options(fn ($get): array => Surface::optionsFor($get('sport_id')))
                    // Hidden rather than empty: a sport with no surfaces is one
                    // nobody asks the question about, and an empty select would
                    // invite an answer that does not exist.
                    ->visible(fn ($get): bool => Surface::optionsFor($get('sport_id')) !== []),
                TextInput::make('capacity')
                    ->label('Capacitate')
                    // Bricks are counted by rows now — six courts are six spaces.
                    // So this can only mean people, which is what the public card
                    // has always printed it as ("5 locuri").
                    ->helperText('Câte persoane încap. Numărul de terenuri nu se scrie aici — fiecare teren e un spațiu al lui.')
                    ->numeric()
                    ->minValue(1),
                Toggle::make('is_indoor')
                    ->label('Acoperit'),
                Toggle::make('has_floodlights')
                    ->label('Nocturnă'),
                Repeater::make('accessSlots')
                    ->label('Tarife')
                    ->helperText('Un rând spune cum se intră, cât costă și când. Aceeași zi poate avea mai multe — un tarif până la 18:00 și altul seara. Lasă ziua și orele goale dacă programul nu e cunoscut; o zi fără niciun tarif înseamnă închis.')
                    ->relationship()
                    ->schema([
                        Select::make('access_mode')
                            ->label('Cum se intră')
                            ->options(SpaceAccessMode::options())
                            ->default(SpaceAccessMode::OpenAccess)
                            ->live()
                            ->afterStateUpdated(function (mixed $state, $set): void {
                                $mode = is_string($state) ? SpaceAccessMode::tryFrom($state) : null;

                                if ($mode instanceof SpaceAccessMode) {
                                    $set('price_unit', $mode->defaultPriceUnit()->value);
                                }
                            })
                            ->required(),
                        TextInput::make('price')
                            ->label('Preț')
                            ->helperText('0 pentru gratuit. Gol înseamnă că nu se știe, și pagina spune asta.')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01'),
                        Select::make('price_unit')
                            ->label('Pe')
                            ->options(PriceUnit::options())
                            ->placeholder(fn ($get): string => static::impliedUnit($get('access_mode'))),
                        Select::make('day_of_week')
                            ->label('Zi')
                            ->options(Weekday::options())
                            ->placeholder('Nu se știe'),
                        TimePicker::make('start_time')
                            ->label('De la')
                            ->seconds(false),
                        TimePicker::make('end_time')
                            ->label('Până la')
                            ->seconds(false),
                        TextInput::make('price_notes')
                            ->label('Notă la preț')
                            ->placeholder('Abonament 380 lei / 10 intrări')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->addActionLabel('Adaugă tarif')
                    // The relationship's `where kind` never reaches an insert, and
                    // the model defaults a slot to `training`, so both have to be
                    // set here or the hours would be filed as trainings.
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, ?Component $livewire): array => static::accessSlotData($data, $livewire))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, ?Component $livewire): array => static::accessSlotData($data, $livewire))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The form for adding a whole set of courts at once.
     *
     * One row per physical unit is the rule, and typing eleven identical forms is
     * what it costs. This pays it once: the shared facts and the tariffs are
     * entered a single time, the courts come out numbered, and whatever differs
     * — the one outdoor court, the one with the glass wall — is edited after.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function bulkFormComponents(): array
    {
        return [
            Select::make('location_id')
                ->label('Locația')
                ->options(fn (?Component $livewire): array => static::locationOptions($livewire))
                ->searchable()
                ->live()
                ->required(),
            Toggle::make('is_operated_by_us')
                ->label('Le administrăm noi')
                ->helperText(fn (?Component $livewire, $get): string => static::atSpaceLimit($livewire, $get)
                    ? 'Ai atins limita din planul tău pentru sporturi noi la adrese noi. Poți în continuare să adaugi terenuri la ce vinzi deja, și spații publice — niciunul nu consumă din limită.'
                    : 'Oricâte terenuri adaugi aici consumă o singură unitate din plan: sunt același lucru vândut, la aceeași adresă.')
                ->disabled(fn (?Component $livewire, $get): bool => static::atSpaceLimit($livewire, $get))
                ->default(true)
                ->dehydrated()
                ->columnSpanFull(),
            TextInput::make('name')
                ->label('Nume de bază')
                ->helperText('Se numerotează singur: „Teren" devine Teren 1, Teren 2, Teren 3. Pentru un singur spațiu, numele rămâne neatins.')
                ->placeholder('Teren')
                ->required()
                ->maxLength(240),
            TextInput::make('quantity')
                ->label('Câte')
                ->numeric()
                ->minValue(1)
                ->maxValue(self::BULK_LIMIT)
                ->default(2)
                ->required(),
            Select::make('sport_id')
                ->label('Sport')
                ->helperText('Lasă gol pentru spații care nu sunt un sport — saune, vestiare.')
                ->options(fn (): array => Sport::query()
                    ->get()
                    ->sortBy(fn (Sport $sport): string => $sport->translated_name)
                    ->mapWithKeys(fn (Sport $sport): array => [$sport->getKey() => $sport->translated_name])
                    ->all())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn ($set) => $set('surface_id', null)),
            Select::make('surface_id')
                ->label('Suprafață')
                ->options(fn ($get): array => Surface::optionsFor($get('sport_id')))
                ->visible(fn ($get): bool => Surface::optionsFor($get('sport_id')) !== []),
            Toggle::make('is_indoor')
                ->label('Acoperite'),
            Toggle::make('has_floodlights')
                ->label('Nocturnă'),
            Repeater::make('tariffs')
                ->label('Tarife')
                ->helperText('Se scriu o dată și se aplică tuturor. Bifează toate zilele cu același tarif — se desfac într-un rând pe zi, ca să poți corecta o singură zi mai târziu.')
                ->schema([
                    Select::make('access_mode')
                        ->label('Cum se intră')
                        ->options(SpaceAccessMode::options())
                        ->default(SpaceAccessMode::OpenAccess)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, $set): void {
                            $mode = is_string($state) ? SpaceAccessMode::tryFrom($state) : null;

                            if ($mode instanceof SpaceAccessMode) {
                                $set('price_unit', $mode->defaultPriceUnit()->value);
                            }
                        })
                        ->required(),
                    TextInput::make('price')
                        ->label('Preț')
                        ->helperText('0 pentru gratuit. Gol înseamnă că nu se știe.')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01'),
                    Select::make('price_unit')
                        ->label('Pe')
                        ->options(PriceUnit::options())
                        ->placeholder(fn ($get): string => static::impliedUnit($get('access_mode'))),
                    Select::make('days')
                        ->label('Zile')
                        ->helperText('Gol = program necunoscut.')
                        ->options(Weekday::options())
                        ->multiple()
                        ->placeholder('Nu se știe'),
                    TimePicker::make('start_time')
                        ->label('De la')
                        ->seconds(false),
                    TimePicker::make('end_time')
                        ->label('Până la')
                        ->seconds(false),
                ])
                ->columns(3)
                ->defaultItems(1)
                ->minItems(1)
                ->addActionLabel('Adaugă tarif')
                ->columnSpanFull(),
        ];
    }

    /**
     * Create one space per unit, each with its own copy of the tariffs.
     *
     * A day multi-select is expanded here rather than stored: the table keeps one
     * row per weekday, so a venue can later correct a single Tuesday without
     * touching the rest of the week.
     *
     * @param  array<string, mixed>  $data
     */
    public static function persistMany(array $data, ?Component $livewire = null, ?Organization $organization = null): Space
    {
        $organization ??= static::resolveOrganization($livewire);
        $quantity = max(1, min(self::BULK_LIMIT, (int) ($data['quantity'] ?? 1)));
        $name = trim((string) $data['name']);

        $attributes = static::resolveOwnership([
            'location_id' => $data['location_id'],
            'is_operated_by_us' => $data['is_operated_by_us'] ?? false,
        ], $livewire, $organization);

        $created = [];

        foreach (range(1, $quantity) as $number) {
            $space = Space::create($attributes + [
                // A lone space keeps the name as typed: "Bazin de înot 1" would
                // claim there is a second one.
                'name' => $quantity === 1 ? $name : $name.' '.$number,
                'sport_id' => $data['sport_id'] ?? null,
                'surface_id' => $data['surface_id'] ?? null,
                'is_indoor' => $data['is_indoor'] ?? null,
                'has_floodlights' => $data['has_floodlights'] ?? null,
            ]);

            static::writeTariffs($space, $data['tariffs'] ?? [], $organization);

            $created[] = $space;
        }

        // At least one, because the quantity is clamped to a minimum of one.
        return $created[0];
    }

    /**
     * Write each tariff onto the space, once per weekday ticked — or once with no
     * hours at all, when nobody knows the timetable.
     *
     * @param  array<int, array<string, mixed>>  $tariffs
     */
    protected static function writeTariffs(Space $space, array $tariffs, ?Organization $organization): void
    {
        foreach ($tariffs as $tariff) {
            $days = array_values(array_filter(
                (array) ($tariff['days'] ?? []),
                fn (mixed $day): bool => filled($day),
            ));

            foreach ($days === [] ? [null] : $days as $day) {
                ScheduleSlot::create([
                    'kind' => ScheduleSlotKind::Access,
                    'organization_id' => $organization?->getKey(),
                    'space_id' => $space->getKey(),
                    'access_mode' => $tariff['access_mode'],
                    'price' => $tariff['price'] ?? null,
                    'price_unit' => $tariff['price_unit'] ?? null,
                    'day_of_week' => $day,
                    // Hours without a day would be a timetable nobody could read.
                    'start_time' => $day === null ? null : ($tariff['start_time'] ?? null),
                    'end_time' => $day === null ? null : ($tariff['end_time'] ?? null),
                ]);
            }
        }
    }

    /**
     * What the price would be measured in if the operator says nothing — shown as
     * the select's placeholder, so the implied unit is visible before it is used.
     */
    protected static function impliedUnit(mixed $mode): string
    {
        $mode = is_string($mode) ? SpaceAccessMode::tryFrom($mode) : null;

        return ($mode ?? SpaceAccessMode::OpenAccess)->defaultPriceUnit()->label();
    }

    /**
     * Every slot the repeater writes is a tariff of this space, credited to the
     * organization that operates it — or to nobody, for a public space.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function accessSlotData(array $data, ?Component $livewire = null): array
    {
        $organization = static::resolveOrganization($livewire);

        return $data + [
            'kind' => ScheduleSlotKind::Access->value,
            'organization_id' => $organization?->getKey(),
        ];
    }

    /**
     * Turn the "we operate it" toggle into the column that actually carries it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolveOwnership(array $data, ?Component $livewire = null, ?Organization $organization = null): array
    {
        $operated = (bool) ($data['is_operated_by_us'] ?? false);
        unset($data['is_operated_by_us']);

        $organization ??= static::resolveOrganization($livewire);
        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;

        $data['organization_location_id'] = $operated && $organization instanceof Organization && $locationId !== null
            ? $organization->organizationLocations()->where('location_id', $locationId)->value('id')
            : null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillOwnership(array $data, Space $record): array
    {
        return array_merge($data, ['is_operated_by_us' => $record->isManaged()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'sport', 'accessSlots']))
            ->columns([
                TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label('Locația')
                    ->searchable(),
                TextColumn::make('access_mode')
                    ->label('Cum se intră')
                    ->badge()
                    // One row can carry both: the hall rented by the hour that
                    // opens on Friday evenings.
                    ->state(fn (Space $record): array => $record->accessModes()
                        ->map(fn (SpaceAccessMode $mode): string => $mode->label())
                        ->all())
                    ->placeholder('—'),
                TextColumn::make('sport.translated_name')
                    ->label('Sport')
                    ->placeholder('—'),
                TextColumn::make('price')
                    ->label('Preț')
                    ->state(fn (Space $record): string => $record->priceFromLabel() ?? 'nespecificat'),
                IconColumn::make('is_operated_by_us')
                    ->label('Administrat de noi')
                    ->boolean()
                    ->state(fn (Space $record): bool => $record->isManaged()),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Space $record): array => static::fillOwnership($data, $record))
                    ->mutateDataUsing(fn (array $data, ?Component $livewire): array => static::resolveOwnership($data, $livewire)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The spaces at this organization's locations, minus the ones another
     * organization operates. A public space at one of my locations is mine to
     * correct; a competitor's pool is not.
     */
    public static function getEloquentQuery(): Builder
    {
        $organization = Filament::getTenant();

        if (! $organization instanceof Organization) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        $ownPresences = $organization->organizationLocations()->pluck('id');

        return parent::getEloquentQuery()
            ->whereIn('location_id', $organization->organizationLocations()->pluck('location_id'))
            ->where(fn (Builder $query) => $query
                ->whereNull('organization_location_id')
                ->orWhereIn('organization_location_id', $ownPresences));
    }

    /**
     * @return array<int, string>
     */
    protected static function locationOptions(?Component $livewire = null): array
    {
        $organization = static::resolveOrganization($livewire);

        if (! $organization instanceof Organization) {
            return [];
        }

        return $organization->locations()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->getKey() => $location->name])
            ->all();
    }

    /**
     * Whether this particular space would exceed the plan.
     *
     * Asked of the location and sport chosen in the form, because another court
     * of something already on offer costs nothing: the limit counts offers, not
     * bricks.
     */
    protected static function atSpaceLimit(?Component $livewire = null, ?callable $get = null): bool
    {
        $organization = static::resolveOrganization($livewire);

        if (! $organization instanceof Organization) {
            return false;
        }

        $locationId = $get === null ? null : $get('location_id');
        $sportId = $get === null ? null : $get('sport_id');

        return ! $organization->canAddSpace(
            filled($locationId) ? (int) $locationId : null,
            filled($sportId) ? (int) $sportId : null,
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSpaces::route('/'),
        ];
    }
}
