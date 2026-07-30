<?php

namespace App\Filament\Admin\Resources\Facilities;

use App\Enums\FacilityStatus;
use App\Filament\Admin\Resources\Facilities\Pages\ManageFacilities;
use App\Models\Facility;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'facilitate';

    protected static ?string $pluralModelLabel = 'facilități';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // A club's suggestion is reviewed here: the photo it sent is the
                // evidence, so it sits above the status the admin is about to
                // change. Admin-created amenities need no photo — there is
                // nobody to vouch for, and the entry is trusted by definition.
                // One proof per place it was attached to, each shown with the
                // location it was taken at — a photo of a bar means nothing
                // without knowing which hall it is in.
                RepeatableEntry::make('proofs')
                    ->label('Dovezi trimise de cluburi')
                    ->state(fn (?Facility $record): array => $record?->proofPhotos() ?? [])
                    ->visible(fn (?Facility $record): bool => filled($record?->proofPhotos()))
                    ->schema([
                        ImageEntry::make('url')
                            ->hiddenLabel()
                            ->height(220),
                        TextEntry::make('location')
                            ->label('Fotografiată la'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                TextEntry::make('proposal')
                    ->label('Propusă de')
                    ->state(fn (?Facility $record): string => $record?->suggestedByOrganization->name ?? '—')
                    ->visible(fn (?Facility $record): bool => $record?->suggested_by_organization_id !== null)
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Denumire')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('icon')
                    ->label('Emoji')
                    ->maxLength(16),
                Select::make('status')
                    ->label('Status')
                    ->helperText('Trecerea pe „Aprobată" o face vizibilă pe paginile publice, pentru oricare club.')
                    ->options(fn (): array => collect(FacilityStatus::cases())
                        ->mapWithKeys(fn (FacilityStatus $status): array => [$status->value => $status->label()])
                        ->all())
                    ->default(FacilityStatus::Approved)
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Waiting suggestions first: they are the only rows needing action.
            ->defaultSort('status')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('locations'))
            ->columns([
                TextColumn::make('icon')
                    ->label('Emoji'),
                ImageColumn::make('proof')
                    ->label('Dovezi')
                    // Stacked thumbnails, so a facility proven at several places
                    // does not look like it was proven at one.
                    ->state(fn (Facility $record): array => array_column($record->proofPhotos(), 'url'))
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText(),
                TextColumn::make('name')
                    ->label('Denumire')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (FacilityStatus $state): string => $state->label())
                    ->color(fn (FacilityStatus $state): string => $state === FacilityStatus::Approved ? 'success' : 'warning'),
                TextColumn::make('suggestedByOrganization.name')
                    ->label('Propusă de')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('locations_count')
                    ->label('Locații')
                    ->counts('locations'),
                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->sortable(),
            ])
            // Status is filtered by the tabs above the table, which also carry
            // the counts — a select filter here would say the same thing twice.
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            // Deliberately no bulk approve: approving is meant to be an act of
            // looking at one club's photo, and a bulk action would skip exactly
            // the check this whole flow exists for.
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFacilities::route('/'),
        ];
    }
}
