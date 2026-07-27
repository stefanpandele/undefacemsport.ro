<?php

namespace App\Filament\Club\Resources\Coaches;

use App\Filament\Club\Resources\Coaches\Pages\ManageCoaches;
use App\Filament\Concerns\ResolvesClub;
use App\Filament\Forms\Components\WebpUpload;
use App\Models\Club;
use App\Models\Coach;
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

class CoachResource extends Resource
{
    use ResolvesClub;

    protected static ?string $model = Coach::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'antrenor';

    protected static ?string $pluralModelLabel = 'antrenori';

    protected static ?string $navigationLabel = 'Antrenori';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nume')
                    ->required(),
                TextInput::make('role')
                    ->label('Rol')
                    ->placeholder('Antrenor principal'),
                WebpUpload::make('photo_path')
                    ->label('Poză')
                    ->avatar()
                    ->square(600)
                    ->disk('s3')
                    ->directory('coaches'),
                Select::make('sports')
                    ->label('Sporturi predate')
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
        $club = static::resolveClub($livewire);

        if (! $club instanceof Club) {
            return $query;
        }

        return $query->whereIn('sports.id', $club->clubSports()->select('sport_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCoaches::route('/'),
        ];
    }
}
