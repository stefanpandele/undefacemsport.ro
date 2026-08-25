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

    protected static ?string $modelLabel = 'antrenor';

    protected static ?string $pluralModelLabel = 'antrenori';

    protected static ?string $navigationLabel = 'Antrenori';

    protected static string|\UnitEnum|null $navigationGroup = 'Cursuri';

    protected static ?int $navigationSort = 3;

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
                Select::make('sports')
                    ->label('Pentru ce sporturi')
                    ->helperText('Doar sporturile declarate la clubul tău.')
                    ->relationship('sports', 'name', fn (Builder $query, ?Component $livewire): Builder => static::scopeToClubSports($query, $livewire))
                    ->getOptionLabelFromRecordUsing(fn (Sport $record): string => $record->translated_name)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Textarea::make('bio')
                    ->label('Descriere')
                    ->columnSpanFull(),
                Toggle::make('offers_private_sessions')
                    ->label('Oferă antrenamente 1:1'),
                Toggle::make('is_primary')
                    ->label('Antrenor principal (afișat în profilul clubului)'),
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
                IconColumn::make('offers_private_sessions')
                    ->label('1:1')
                    ->boolean(),
                IconColumn::make('is_primary')
                    ->label('Principal')
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
