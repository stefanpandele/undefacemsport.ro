<?php

namespace App\Filament\Organization\Resources\OrganizationSports;

use App\Filament\Concerns\ClubOnlyResource;
use App\Filament\Concerns\ResolvesOrganization;
use App\Filament\Forms\Components\WebpUpload;
use App\Filament\Organization\Resources\OrganizationSports\Pages\ManageOrganizationSports;
use App\Models\Organization;
use App\Models\OrganizationSport;
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
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Component;

class OrganizationSportResource extends Resource
{
    use ClubOnlyResource, ResolvesOrganization;

    protected static ?string $model = OrganizationSport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'sport';

    protected static ?string $pluralModelLabel = 'sporturi';

    protected static ?string $navigationLabel = 'Sporturi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sport_id')
                    ->label('Sport')
                    ->options(fn (?OrganizationSport $record, ?Component $livewire): array => static::availableSports($record, $livewire))
                    ->searchable()
                    ->required(),
                Toggle::make('offers_private_sessions')
                    ->label('Oferă antrenamente 1:1'),
                Select::make('ageGroups')
                    ->label('Grupe / public-țintă')
                    ->relationship('ageGroups', 'name')
                    ->multiple()
                    ->preload()
                    ->columnSpanFull(),
                // Who the groups are for and how far along they are: two axes,
                // because a child and an adult can both be beginners.
                Select::make('levels')
                    ->label('Niveluri')
                    ->helperText('De la inițiere la performanță — ce nivel de pregătire ai la acest sport.')
                    ->relationship('levels', 'name')
                    ->multiple()
                    ->preload()
                    ->columnSpanFull(),
                Repeater::make('benefits')
                    ->label('Beneficii')
                    ->relationship()
                    ->schema([
                        TextInput::make('icon')
                            ->label('Emoji')
                            ->maxLength(16),
                        TextInput::make('label')
                            ->label('Text')
                            ->required()
                            ->columnSpan(2),
                    ])
                    ->columns(3)
                    ->orderColumn('sort_order')
                    ->addActionLabel('Adaugă beneficiu')
                    ->columnSpanFull(),
                Repeater::make('galleryImages')
                    ->label('Galerie foto')
                    ->relationship()
                    ->schema([
                        WebpUpload::make('path')
                            ->label('Poză')
                            ->square(1200)
                            ->disk('s3')
                            ->directory('club-sports/gallery')
                            ->required(),
                    ])
                    ->orderColumn('sort_order')
                    // The same cap on every plan — see config/plans.php.
                    ->maxItems(fn (?Component $livewire): ?int => static::galleryLimit($livewire))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['disk' => 's3', 'collection' => 'gallery'])
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => $data + ['disk' => 's3', 'collection' => 'gallery'])
                    ->addActionLabel('Adaugă poză')
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Ordine sport')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sport.translated_name')
                    ->label('Sport')
                    ->sortable(),
                IconColumn::make('offers_private_sessions')
                    ->label('1:1')
                    ->boolean(),
                TextColumn::make('ageGroups.name')
                    ->label('Grupe')
                    ->badge(),
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
     * Gate creating a sport behind the club's subscription plan limit. The
     * "New" button hides once the plan's `sports` limit is reached.
     */
    public static function canCreate(): bool
    {
        $organization = Filament::getTenant();

        return $organization instanceof Organization
            && $organization->canAddSport()
            && parent::canCreate();
    }

    /**
     * How many gallery photos the club may attach to one sport, or null for no
     * cap. Identical across plans by design — visitors compare clubs on the
     * same page, so presentation quality is not a paid tier.
     */
    protected static function galleryLimit(?Component $livewire = null): ?int
    {
        $organization = static::resolveOrganization($livewire);

        return $organization instanceof Organization
            ? $organization->planLimit('gallery_images')
            : null;
    }

    /**
     * Sports the current club can still add (global taxonomy minus the ones it
     * already offers), keeping the record's own sport selectable while editing.
     *
     * @return array<int, string>
     */
    protected static function availableSports(?OrganizationSport $record, ?Component $livewire = null): array
    {
        $organization = static::resolveOrganization($livewire);

        $taken = $organization instanceof Organization
            ? $organization->organizationSports()
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->pluck('sport_id')
            : collect();

        return Sport::query()
            ->whereNotIn('id', $taken)
            ->get()
            ->sortBy(fn (Sport $sport): string => $sport->translated_name)
            ->pluck('translated_name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrganizationSports::route('/'),
        ];
    }
}
