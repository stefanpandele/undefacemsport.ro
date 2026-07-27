<?php

namespace App\Filament\Club\Resources\ClubSports;

use App\Filament\Club\Resources\ClubSports\Pages\ManageClubSports;
use App\Filament\Concerns\ResolvesClub;
use App\Filament\Forms\Components\WebpUpload;
use App\Models\Club;
use App\Models\ClubSport;
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

class ClubSportResource extends Resource
{
    use ResolvesClub;

    protected static ?string $model = ClubSport::class;

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
                    ->options(fn (?ClubSport $record, ?Component $livewire): array => static::availableSports($record, $livewire))
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
        $club = Filament::getTenant();

        return $club instanceof Club
            && $club->canAddSport()
            && parent::canCreate();
    }

    /**
     * How many gallery photos the club may attach to one sport, or null for no
     * cap. Identical across plans by design — visitors compare clubs on the
     * same page, so presentation quality is not a paid tier.
     */
    protected static function galleryLimit(?Component $livewire = null): ?int
    {
        $club = static::resolveClub($livewire);

        return $club instanceof Club
            ? $club->planLimit('gallery_images')
            : null;
    }

    /**
     * Sports the current club can still add (global taxonomy minus the ones it
     * already offers), keeping the record's own sport selectable while editing.
     *
     * @return array<int, string>
     */
    protected static function availableSports(?ClubSport $record, ?Component $livewire = null): array
    {
        $club = static::resolveClub($livewire);

        $taken = $club instanceof Club
            ? $club->clubSports()
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
            'index' => ManageClubSports::route('/'),
        ];
    }
}
