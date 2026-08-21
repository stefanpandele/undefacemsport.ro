<?php

namespace App\Filament\Admin\Resources\Spaces;

use App\Enums\FacilityStatus;
use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Filament\Admin\Resources\Spaces\Pages\ManageSpaces;
use App\Models\Location;
use App\Models\Space;
use App\Models\Sport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Two jobs in one screen.
 *
 * Putting the well-known public spaces on the map — the park courts and open
 * school pitches nobody will ever register — and keeping them honest afterwards.
 * Nobody has an incentive to maintain an unmanaged record, so the platform asks
 * itself: anything unverified for `Space::STALE_AFTER_MONTHS` comes back here.
 */
class SpaceResource extends Resource
{
    protected static ?string $model = Space::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $modelLabel = 'spațiu';

    protected static ?string $pluralModelLabel = 'spații';

    protected static ?string $navigationLabel = 'Spații';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('operator')
                    ->label('Administrat de')
                    ->state(fn (?Space $record): string => $record?->organizationLocation?->organization->name
                        ?? 'Nimeni — spațiu public')
                    ->columnSpanFull(),
                Select::make('location_id')
                    ->label('Locația')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Location $location): array => [
                            $location->getKey() => $location->name.' · '.($location->city ?? ''),
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('name')
                    ->label('Nume')
                    ->placeholder('Teren de baschet, Bazin de înot')
                    ->required()
                    ->maxLength(255),
                Select::make('sport_id')
                    ->label('Sport')
                    ->options(fn (): array => Sport::query()
                        ->get()
                        ->sortBy(fn (Sport $sport): string => $sport->translated_name)
                        ->mapWithKeys(fn (Sport $sport): array => [$sport->getKey() => $sport->translated_name])
                        ->all())
                    ->searchable(),
                TextInput::make('capacity')
                    ->label('Capacitate')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('surface')
                    ->label('Suprafață')
                    ->maxLength(255),
                Toggle::make('is_indoor')
                    ->label('Acoperit'),
                Toggle::make('has_floodlights')
                    ->label('Nocturnă'),
                Select::make('status')
                    ->label('Status')
                    ->helperText('Trecerea pe „Aprobată" îl face vizibil pe paginile publice.')
                    ->options(fn (): array => collect(FacilityStatus::cases())
                        ->mapWithKeys(fn (FacilityStatus $status): array => [$status->value => $status->label()])
                        ->all())
                    ->default(FacilityStatus::Approved)
                    ->required(),
                // Without a tariff a space says neither how you get in nor what it
                // costs, so it can be listed nowhere. For a park court one row is
                // the whole job: acces liber, gratuit, orele lăsate goale.
                Repeater::make('accessSlots')
                    ->label('Tarife')
                    ->helperText('Cel puțin un rând, altfel spațiul nu apare nicăieri. Lasă ziua și orele goale dacă nu se știe programul.')
                    ->relationship()
                    ->schema([
                        Select::make('access_mode')
                            ->label('Cum se intră')
                            ->options(SpaceAccessMode::options())
                            ->default(SpaceAccessMode::OpenAccess)
                            ->required(),
                        TextInput::make('price')
                            ->label('Preț')
                            ->helperText('0 pentru gratuit, gol pentru necunoscut.')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->default(0),
                        Select::make('price_unit')
                            ->label('Pe')
                            ->options(PriceUnit::options()),
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
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Adaugă tarif')
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['kind' => ScheduleSlotKind::Access->value])
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => $data + ['kind' => ScheduleSlotKind::Access->value])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Waiting proposals first: the only rows needing a decision.
            ->defaultSort('status')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'location',
                'sport',
                'accessSlots',
                'organizationLocation.organization',
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label('Locația')
                    ->description(fn (Space $record): string => (string) $record->location?->city)
                    ->searchable(),
                TextColumn::make('access_mode')
                    ->label('Cum se intră')
                    ->badge()
                    ->state(fn (Space $record): array => $record->accessModes()
                        ->map(fn (SpaceAccessMode $mode): string => $mode->label())
                        ->all())
                    ->placeholder('—'),
                TextColumn::make('price')
                    ->label('Preț')
                    ->state(fn (Space $record): string => $record->priceFromLabel() ?? 'nespecificat'),
                TextColumn::make('operator')
                    ->label('Administrat de')
                    ->state(fn (Space $record): string => $record->organizationLocation?->organization->name ?? 'public')
                    ->searchable(false),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (FacilityStatus $state): string => $state->label())
                    ->color(fn (FacilityStatus $state): string => $state === FacilityStatus::Approved ? 'success' : 'warning'),
                TextColumn::make('last_verified_at')
                    ->label('Verificat')
                    ->since()
                    ->placeholder('niciodată')
                    ->sortable(),
                IconColumn::make('is_stale')
                    ->label('De reverificat')
                    ->boolean()
                    ->state(fn (Space $record): bool => ! $record->isManaged() && (
                        $record->last_verified_at === null
                        || $record->last_verified_at->lt(now()->subMonths(Space::STALE_AFTER_MONTHS))
                    )),
            ])
            ->filters([
                SelectFilter::make('access_mode')
                    ->label('Cum se intră')
                    ->options(SpaceAccessMode::options())
                    // The mode lives on the tariffs now, so the filter asks them.
                    // Spelled out rather than calling Space::scopeOffering(): the
                    // filter's builder is not typed to a model, so the scope would
                    // be invisible to static analysis.
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('scheduleSlots', fn (BuilderContract $slots) => $slots
                            ->where('kind', ScheduleSlotKind::Access)
                            ->where('access_mode', SpaceAccessMode::from((string) $data['value'])))
                        : $query),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Am verificat')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmi că informația e corectă?')
                    ->modalDescription('Ștampilează data de azi. Spațiul iese din coada de reverificare pentru '.Space::STALE_AFTER_MONTHS.' luni.')
                    ->visible(fn (Space $record): bool => ! $record->isManaged())
                    ->action(function (Space $record): void {
                        $record->forceFill(['last_verified_at' => now()])->save();

                        Notification::make()
                            ->success()
                            ->title('Verificat')
                            ->send();
                    }),
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
            'index' => ManageSpaces::route('/'),
        ];
    }
}
