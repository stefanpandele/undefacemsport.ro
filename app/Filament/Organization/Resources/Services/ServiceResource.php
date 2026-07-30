<?php

namespace App\Filament\Organization\Resources\Services;

use App\Filament\Concerns\ResolvesOrganization;
use App\Filament\Organization\Resources\Services\Pages\ManageServices;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\Person;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Sport;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * What an organization sells one appointment at a time: consultations, sessions,
 * massage.
 *
 * Deliberately open to every type, like spaces. For a practice these *are* the
 * activity; for a pilates studio or a hotel the massage is an extra alongside the
 * main thing. Type decides the shape of the public page, never what may be
 * published — gating this to practices contradicted that and left a studio unable
 * to publish the massage it actually sells.
 */
class ServiceResource extends Resource
{
    use ResolvesOrganization;

    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'serviciu';

    protected static ?string $pluralModelLabel = 'servicii';

    protected static ?string $navigationLabel = 'Servicii';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Denumire')
                    ->placeholder('Consultație inițială, Ședință de kinetoterapie')
                    ->required()
                    ->maxLength(255),
                Select::make('specialty_id')
                    ->label('Specialitate')
                    ->options(fn (): array => Specialty::query()
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn (Specialty $specialty): array => [
                            $specialty->getKey() => $specialty->translated_name,
                        ])
                        ->all())
                    ->searchable(),
                Select::make('sports')
                    ->label('Pentru ce sporturi')
                    ->helperText('Bifează sporturile pentru care oferi acest serviciu. Aici te vor găsi sportivii lor.')
                    ->relationship('sports', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Sport $record): string => $record->translated_name)
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
                Select::make('organization_location_id')
                    ->label('Unde se oferă')
                    ->helperText('Lasă gol dacă se oferă la toate locațiile tale.')
                    ->options(fn (?Component $livewire): array => static::presenceOptions($livewire))
                    ->searchable(),
                Select::make('person_id')
                    ->label('Cine îl oferă')
                    ->helperText('Lasă gol dacă îl oferă oricine din echipă.')
                    ->options(fn (?Component $livewire): array => static::peopleOptions($livewire))
                    ->searchable(),
                TextInput::make('duration_minutes')
                    ->label('Durată (minute)')
                    ->numeric()
                    ->minValue(5),
                TextInput::make('price')
                    ->label('Preț (lei)')
                    ->helperText('0 pentru gratuit. Gol înseamnă că nu se știe, și pagina spune asta.')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01'),
                TextInput::make('price_notes')
                    ->label('Notă la preț')
                    ->placeholder('Pachet de 5 ședințe: 900 lei')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Descriere')
                    ->maxLength(2000)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['specialty', 'person', 'sports', 'organizationLocation.location']))
            ->columns([
                TextColumn::make('name')
                    ->label('Denumire')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('specialty.translated_name')
                    ->label('Specialitate')
                    ->placeholder('—'),
                TextColumn::make('sports.translated_name')
                    ->label('Pentru')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('organizationLocation.location.name')
                    ->label('Unde')
                    ->placeholder('toate locațiile'),
                TextColumn::make('person.name')
                    ->label('Cine îl oferă')
                    ->placeholder('oricine din echipă'),
                TextColumn::make('duration_minutes')
                    ->label('Durată')
                    ->state(fn (Service $record): string => $record->durationLabel() ?? '—'),
                TextColumn::make('price')
                    ->label('Preț')
                    ->state(fn (Service $record): string => $record->priceLabel() ?? 'nespecificat'),
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

    /**
     * The organization's locations, as presences — a service is offered at a place
     * the organization is actually at.
     *
     * @return array<int, string>
     */
    protected static function presenceOptions(?Component $livewire = null): array
    {
        $organization = static::resolveOrganization($livewire);

        if (! $organization instanceof Organization) {
            return [];
        }

        return $organization->organizationLocations()
            ->with('location')
            ->get()
            ->mapWithKeys(fn (OrganizationLocation $presence): array => [
                $presence->getKey() => (string) $presence->location?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function peopleOptions(?Component $livewire = null): array
    {
        $organization = static::resolveOrganization($livewire);

        if (! $organization instanceof Organization) {
            return [];
        }

        return $organization->people()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Person $person): array => [
                $person->getKey() => trim($person->name.' · '.$person->profession->label()),
            ])
            ->all();
    }

    /**
     * Gate creating a service behind the plan limit, the same shape the sports and
     * spaces resources use.
     */
    public static function canCreate(): bool
    {
        $organization = Filament::getTenant();

        return $organization instanceof Organization
            && $organization->canAddService()
            && parent::canCreate();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServices::route('/'),
        ];
    }
}
