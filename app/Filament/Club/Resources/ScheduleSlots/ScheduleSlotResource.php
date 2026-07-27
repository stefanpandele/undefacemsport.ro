<?php

namespace App\Filament\Club\Resources\ScheduleSlots;

use App\Enums\Weekday;
use App\Filament\Club\Resources\ScheduleSlots\Pages\ManageScheduleSlots;
use App\Filament\Concerns\ResolvesClub;
use App\Models\AgeGroup;
use App\Models\Club;
use App\Models\ClubLocationSport;
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
    use ResolvesClub;

    protected static ?string $model = ScheduleSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'interval din orar';

    protected static ?string $pluralModelLabel = 'orar';

    protected static ?string $navigationLabel = 'Orar';

    /**
     * Editing one existing interval: everything, including where it happens.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::locationSportSelect()
                    ->afterStateUpdated(fn ($set) => $set('coach_id', null)),
                ...static::intervalFields('club_location_sport_id'),
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
                // The coach options are scoped to the sport, so a pair change
                // clears the chosen coaches but keeps the days and hours typed.
                ->afterStateUpdated(function ($get, $set): void {
                    $slots = $get('slots');

                    $set('slots', collect(is_array($slots) ? $slots : [])
                        ->map(fn (array $slot): array => [...$slot, 'coach_id' => null])
                        ->all());
                })
                ->columnSpanFull(),
            Repeater::make('slots')
                ->label('Intervale')
                ->schema(static::intervalFields('../../club_location_sport_id'))
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
        $locationSport = ClubLocationSport::query()
            ->with('clubLocation')
            ->whereKey($data['club_location_sport_id'])
            ->firstOrFail();

        $slots = $data['slots'] ?? [];

        return collect(is_array($slots) ? $slots : [])
            ->map(fn (array $slot): ScheduleSlot => ScheduleSlot::create([
                'club_id' => $locationSport->clubLocation->club_id,
                'club_location_sport_id' => $locationSport->getKey(),
                'day_of_week' => $slot['day_of_week'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'age_group_id' => $slot['age_group_id'] ?? null,
                'coach_id' => $slot['coach_id'] ?? null,
            ]))
            ->first();
    }

    protected static function locationSportSelect(): Select
    {
        return Select::make('club_location_sport_id')
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
            Select::make('coach_id')
                ->label('Antrenor')
                ->options(fn ($get, ?Component $livewire): array => static::coachOptions($get($locationSportPath), $livewire))
                ->searchable(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clubLocationSport.clubLocation.location.name')
                    ->label('Locație')
                    ->sortable(),
                TextColumn::make('clubLocationSport.sport.translated_name')
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
                TextColumn::make('coach.name')
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
        $club = static::resolveClub($livewire);

        if (! $club instanceof Club) {
            return [];
        }

        return ClubLocationSport::query()
            ->whereHas('clubLocation', fn (Builder $query) => $query->where('club_id', $club->getKey()))
            ->with(['clubLocation.location', 'sport'])
            ->get()
            ->mapWithKeys(fn (ClubLocationSport $item): array => [
                $item->getKey() => sprintf(
                    '%s — %s',
                    $item->clubLocation->location->name,
                    $item->sport->translated_name,
                ),
            ])
            ->all();
    }

    /**
     * The club's coaches, limited to those teaching the selected slot's sport.
     *
     * @return array<int, string>
     */
    protected static function coachOptions(mixed $clubLocationSportId, ?Component $livewire = null): array
    {
        $club = static::resolveClub($livewire);

        if (! $club instanceof Club) {
            return [];
        }

        $query = $club->coaches()->orderBy('name');

        $sportId = filled($clubLocationSportId)
            ? ClubLocationSport::query()->whereKey($clubLocationSportId)->value('sport_id')
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

    public static function getPages(): array
    {
        return [
            'index' => ManageScheduleSlots::route('/'),
        ];
    }
}
