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
use App\Models\Space;
use App\Models\Sport;
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

    /**
     * Scoped by hand rather than by Filament's tenancy: a space belongs to a
     * location, and only sometimes to an organization, so there is no
     * `organization_id` for the panel to filter on.
     */
    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('location_id')
                    ->label('Locația')
                    ->options(fn (?Component $livewire): array => static::locationOptions($livewire))
                    ->searchable()
                    ->required(),
                Toggle::make('is_operated_by_us')
                    ->label('Îl administrăm noi')
                    ->helperText(fn (?Component $livewire): string => static::atSpaceLimit($livewire)
                        ? 'Ai atins limita de spații administrate din planul tău. Poți în continuare să adaugi un spațiu public — nu consumă din limită.'
                        : 'Stins înseamnă spațiu public: îl semnalezi pentru toată lumea, fără să-l administrezi. Nu consumă din limita planului.')
                    // Disabled at the quota rather than hiding the whole form: a
                    // club that has run out of its own spaces may still put a park
                    // court on the map, and the plan has no business stopping it.
                    ->disabled(fn (?Component $livewire): bool => static::atSpaceLimit($livewire))
                    ->default(true)
                    ->dehydrated()
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Nume')
                    ->placeholder('Bazin de înot, Teren 1, Saună')
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
                    ->searchable(),
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
                    ->options(PriceUnit::options()),
                TextInput::make('price_notes')
                    ->label('Notă la preț')
                    ->placeholder('Abonament 380 lei / 10 intrări')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('capacity')
                    ->label('Capacitate')
                    ->helperText('Informativ: 6 culoare, 2 terenuri.')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('surface')
                    ->label('Suprafață')
                    ->placeholder('Gazon sintetic, parchet, tartan')
                    ->maxLength(255),
                Toggle::make('is_indoor')
                    ->label('Acoperit'),
                Toggle::make('has_floodlights')
                    ->label('Nocturnă'),
                Repeater::make('accessSlots')
                    ->label('Program')
                    ->helperText('Câte un interval pe zi. O zi fără interval înseamnă închis. Un interval poate avea alt mod de acces decât spațiul — o sală închiriată pe oră care ține open-gym vinerea seara.')
                    ->relationship()
                    ->schema([
                        Select::make('day_of_week')
                            ->label('Zi')
                            ->options(Weekday::options())
                            ->required(),
                        TimePicker::make('start_time')
                            ->label('De la')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Până la')
                            ->seconds(false)
                            ->required(),
                        Select::make('access_mode')
                            ->label('Cum se intră')
                            ->helperText('Gol = ca spațiul.')
                            ->options(SpaceAccessMode::options())
                            ->placeholder('Ca spațiul'),
                        TextInput::make('price')
                            ->label('Preț pe interval')
                            ->helperText('Gol = prețul de bază.')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01'),
                        Select::make('price_unit')
                            ->label('Pe')
                            ->options(PriceUnit::options())
                            ->placeholder('Ca spațiul'),
                    ])
                    ->columns(3)
                    ->addActionLabel('Adaugă interval')
                    // The relationship's `where kind` never reaches an insert, and
                    // the model defaults a slot to `training`, so both have to be
                    // set here or the hours would be filed as trainings.
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, ?Component $livewire): array => static::accessSlotData($data, $livewire))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, ?Component $livewire): array => static::accessSlotData($data, $livewire))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Every slot the repeater writes is a space's own opening hours, credited to
     * the organization that operates it — or to nobody, for a public space.
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
    public static function resolveOwnership(array $data, ?Component $livewire = null): array
    {
        $operated = (bool) ($data['is_operated_by_us'] ?? false);
        unset($data['is_operated_by_us']);

        $organization = static::resolveOrganization($livewire);
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
                    ->formatStateUsing(fn (SpaceAccessMode $state): string => $state->label()),
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

    protected static function atSpaceLimit(?Component $livewire = null): bool
    {
        $organization = static::resolveOrganization($livewire);

        return $organization instanceof Organization && ! $organization->canAddSpace();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSpaces::route('/'),
        ];
    }
}
