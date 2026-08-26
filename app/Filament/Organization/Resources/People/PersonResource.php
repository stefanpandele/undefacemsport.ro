<?php

namespace App\Filament\Organization\Resources\People;

use App\Filament\Concerns\ResolvesOrganization;
use App\Filament\Forms\Components\WebpUpload;
use App\Filament\Organization\Resources\People\Pages\ManagePeople;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Sport;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class PersonResource extends Resource
{
    use ResolvesOrganization;

    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'persoană';

    // Plural is the screen's own name; the singular stays a person, so the
    // buttons read "Adaugă persoană" rather than "Adaugă echipă".
    protected static ?string $pluralModelLabel = 'echipă';

    protected static ?string $navigationLabel = 'Echipă';

    protected static ?int $navigationSort = 2;

    /**
     * Outside the offer groups, beside the locations: the same people run the
     * courses and give the services, and which hall and sport each of them is
     * tied to is answered by the schedule, one row per hour.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nume')
                    ->required(),
                // One field for what somebody is here, in their own words: a
                // second structured column asking the same question defaulted
                // every person to "coach" and was dropped.
                TextInput::make('role')
                    ->label('Rol')
                    ->placeholder('Antrenor principal, Fizioterapeut, Președinte'),
                WebpUpload::make('photo_path')
                    ->label('Poză')
                    ->avatar()
                    ->square(600)
                    ->disk('s3')
                    ->directory('people'),
                // Empty is a real answer, not a gap: a club has a reception and
                // an office as well as a poolside, and nobody at the desk works
                // "for a sport". Everything below that only makes sense for
                // somebody who teaches is asked only once this is filled.
                Select::make('sports')
                    ->label('Pentru ce sporturi lucrează')
                    ->helperText('Lasă gol pentru recepție, administrativ sau oricine nu predă. Se aleg doar sporturile declarate la clubul tău.')
                    ->relationship('sports', 'name', fn (Builder $query, ?Component $livewire): Builder => static::scopeToClubSports($query, $livewire))
                    ->getOptionLabelFromRecordUsing(fn (Sport $record): string => $record->translated_name)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->columnSpanFull(),
                Textarea::make('bio')
                    ->label('Descriere')
                    ->columnSpanFull(),
                // Asked per sport, not once per person: a coach who gives
                // individual swimming lessons and only group basketball used to
                // claim both. Its options are whatever is ticked above — you
                // cannot offer one to one in something you do not do at all,
                // and somebody who teaches nothing is never asked.
                Select::make('private_session_sports')
                    ->label('La care dintre ele dă antrenamente 1:1')
                    ->helperText('Lasă gol dacă lucrează doar cu grupe.')
                    ->visible(fn ($get): bool => filled($get('sports')))
                    ->options(fn ($get): array => Sport::query()
                        ->whereKey(array_map(intval(...), (array) $get('sports')))
                        ->get()
                        ->sortBy(fn (Sport $sport): string => $sport->translated_name)
                        ->mapWithKeys(fn (Sport $sport): array => [$sport->getKey() => $sport->translated_name])
                        ->all())
                    ->multiple()
                    ->searchable()
                    ->columnSpanFull()
                    ->afterStateHydrated(function (Select $component, ?Person $record): void {
                        $component->state($record?->privateSessionSportIds() ?? []);
                    })
                    // The sports relationship is saved by the field above, so
                    // this one only marks which of the rows it wrote carry 1:1.
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function (?Person $record, mixed $state): void {
                        if (! $record instanceof Person) {
                            return;
                        }

                        $solo = array_map(intval(...), (array) $state);

                        foreach ($record->sports()->pluck('sports.id') as $sportId) {
                            $record->sports()->updateExistingPivot($sportId, [
                                'offers_private_sessions' => in_array((int) $sportId, $solo, strict: true),
                            ]);
                        }

                        $record->unsetRelation('sports');
                    }),
                // Not a job title — that is "Rol" above, in their own words.
                // This is the one person the public pages speak through.
                Toggle::make('is_primary')
                    ->label('Reprezintă organizația')
                    ->helperText('Numele afișat în antetul paginii publice și ca persoană de contact. Bifând pe cineva, se ia de la altul.'),
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
                ImageColumn::make('photo_path')
                    ->label('')
                    ->disk('s3')
                    ->circular(),
                TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Rol'),
                TextColumn::make('sports.translated_name')
                    ->label('Sporturi')
                    ->badge(),
                TextColumn::make('private_session_sports')
                    ->label('1:1')
                    ->badge()
                    ->state(fn (Person $record): array => $record->sports
                        ->filter(fn (Sport $sport): bool => $record->offersPrivateSessionsIn($sport->getKey()))
                        ->map(fn (Sport $sport): string => $sport->translated_name)
                        ->values()
                        ->all()),
                IconColumn::make('is_primary')
                    ->label('Reprezintă')
                    ->boolean(),
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
     * Limit the sport options to the ones the current club has declared.
     *
     * @param  Builder<Sport>  $query
     * @return Builder<Sport>
     */
    protected static function scopeToClubSports(Builder $query, ?Component $livewire = null): Builder
    {
        $organization = static::resolveOrganization($livewire);

        if (! $organization instanceof Organization) {
            return $query;
        }

        return $query->whereIn('sports.id', $organization->organizationSports()->select('sport_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePeople::route('/'),
        ];
    }
}
