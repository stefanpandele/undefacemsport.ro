<?php

namespace App\Filament\Organization\Resources\ScheduleSlots;

use App\Enums\NavigationGroup;
use App\Enums\ScheduleSlotKind;
use App\Enums\Weekday;
use App\Filament\Concerns\ResolvesOrganization;
use App\Filament\Organization\Resources\ScheduleSlots\Pages\ManageScheduleSlots;
use App\Models\AgeGroup;
use App\Models\Level;
use App\Models\Organization;
use App\Models\OrganizationLocationSport;
use App\Models\ScheduleSlot;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ScheduleSlotResource extends Resource
{
    use ResolvesOrganization;

    protected static ?string $model = ScheduleSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'interval din orar';

    protected static ?string $pluralModelLabel = 'orar';

    protected static ?string $navigationLabel = 'Orar';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Courses;

    protected static ?int $navigationSort = 2;

    /**
     * Câte intervale de antrenament are săptămâna. Absent rather than zero: an offer an organization has not made yet is
     * quieter without a badge than with a nought beside it.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Editing one existing interval: everything, including where it happens.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::locationSportSelect()
                    ->afterStateUpdated(fn ($set) => $set('person_id', null)),
                ...static::intervalFields('organization_location_sport_id'),
            ]);
    }

    /**
     * Adding intervals: the (location — sport) pair is picked once, then each
     * repeater row becomes its own schedule slot on submit.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function bulkFormComponents(): array
    {
        return [
            static::locationSportSelect()
                // The person options are scoped to the sport, so a pair change
                // clears the chosen people but keeps the days and hours typed.
                ->afterStateUpdated(function ($get, $set): void {
                    $slots = $get('slots');

                    $set('slots', collect(is_array($slots) ? $slots : [])
                        ->map(fn (array $slot): array => [...$slot, 'person_id' => null])
                        ->all());
                })
                ->columnSpanFull(),
            Repeater::make('slots')
                ->label('Intervale')
                ->schema(static::intervalFields('../../organization_location_sport_id'))
                ->columns(5)
                ->defaultItems(1)
                ->minItems(1)
                ->addActionLabel('Adaugă interval')
                ->columnSpanFull(),
        ];
    }

    /**
     * Create one schedule slot per row of the bulk form.
     *
     * @param  array<string, mixed>  $data
     */
    public static function persistMany(array $data): ?ScheduleSlot
    {
        $locationSport = OrganizationLocationSport::query()
            ->with('organizationLocation')
            ->whereKey($data['organization_location_sport_id'])
            ->firstOrFail();

        $slots = $data['slots'] ?? [];

        return collect(is_array($slots) ? $slots : [])
            ->map(fn (array $slot): ScheduleSlot => ScheduleSlot::create([
                'organization_id' => $locationSport->organizationLocation->organization_id,
                'organization_location_sport_id' => $locationSport->getKey(),
                'day_of_week' => $slot['day_of_week'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'age_group_id' => $slot['age_group_id'] ?? null,
                'level_id' => $slot['level_id'] ?? null,
                'person_id' => $slot['person_id'] ?? null,
            ]))
            ->first();
    }

    protected static function locationSportSelect(): Select
    {
        return Select::make('organization_location_sport_id')
            ->label('Locație & sport')
            ->options(fn (?Component $livewire): array => static::locationSportOptions($livewire))
            ->searchable()
            ->live()
            ->required();
    }

    /**
     * The fields describing one weekly interval. `$locationSportPath` is where
     * the selected (location — sport) pair lives relative to these fields.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected static function intervalFields(string $locationSportPath): array
    {
        return [
            Select::make('day_of_week')
                ->label('Ziua')
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
            Select::make('age_group_id')
                ->label('Grupă')
                ->options(fn (): array => AgeGroup::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                ->required(),
            // Required alongside the group: the public pages read who a club
            // teaches and how far along they are off these two columns, so a
            // blank one is a chip missing from the club's own page.
            Select::make('level_id')
                ->label('Nivel')
                ->options(fn (): array => Level::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                ->required(),
            Select::make('person_id')
                ->label('Antrenor')
                ->options(fn ($get, ?Component $livewire): array => static::coachOptions($get($locationSportPath), $livewire))
                ->searchable(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organizationLocationSport.organizationLocation.location.name')
                    ->label('Locație')
                    ->sortable(),
                TextColumn::make('organizationLocationSport.sport.translated_name')
                    ->label('Sport')
                    ->badge(),
                TextColumn::make('day_of_week')
                    ->label('Ziua')
                    ->formatStateUsing(fn (mixed $state): string => static::weekdayLabel($state))
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('De la')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : ''),
                TextColumn::make('end_time')
                    ->label('Până la')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : ''),
                TextColumn::make('ageGroup.name')
                    ->label('Grupă')
                    ->badge(),
                TextColumn::make('person.name')
                    ->label('Antrenor')
                    ->placeholder('—'),
            ])
            ->defaultSort('day_of_week')
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
     * The club's (location — sport) pairs the schedule can be attached to.
     *
     * @return array<int, string>
     */
    protected static function locationSportOptions(?Component $livewire = null): array
    {
        $organization = static::resolveOrganization($livewire);

        if (! $organization instanceof Organization) {
            return [];
        }

        return OrganizationLocationSport::query()
            ->whereHas('organizationLocation', fn (Builder $query) => $query->where('organization_id', $organization->getKey()))
            ->with(['organizationLocation.location', 'sport'])
            ->get()
            ->mapWithKeys(fn (OrganizationLocationSport $item): array => [
                $item->getKey() => sprintf(
                    '%s — %s',
                    $item->organizationLocation->location->name,
                    $item->sport->translated_name,
                ),
            ])
            ->all();
    }

    /**
     * The club's people, limited to those teaching the selected slot's sport.
     *
     * @return array<int, string>
     */
    protected static function coachOptions(mixed $clubLocationSportId, ?Component $livewire = null): array
    {
        $organization = static::resolveOrganization($livewire);

        if (! $organization instanceof Organization) {
            return [];
        }

        $query = $organization->people()->orderBy('name');

        $sportId = filled($clubLocationSportId)
            ? OrganizationLocationSport::query()->whereKey($clubLocationSportId)->value('sport_id')
            : null;

        if ($sportId !== null) {
            $query->whereHas('sports', fn (Builder $subQuery) => $subQuery->whereKey($sportId));
        }

        return $query->pluck('name', 'id')->all();
    }

    protected static function weekdayLabel(mixed $state): string
    {
        if ($state instanceof Weekday) {
            return $state->label();
        }

        return Weekday::tryFrom((int) $state)?->label() ?? '';
    }

    /**
     * Trainings only.
     *
     * A space's tariffs are credited to the organization that wrote them, so they
     * sit in this table under the same tenant key. They are not sessions anybody
     * teaches, and they belong on the Spaces screen — showing them here would put
     * rows in a club's timetable that it never taught and cannot edit from here.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('kind', ScheduleSlotKind::Training);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageScheduleSlots::route('/'),
        ];
    }
}
