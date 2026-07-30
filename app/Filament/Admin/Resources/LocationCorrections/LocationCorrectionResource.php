<?php

namespace App\Filament\Admin\Resources\LocationCorrections;

use App\Enums\LocationCorrectionField;
use App\Enums\LocationCorrectionStatus;
use App\Filament\Admin\Resources\LocationCorrections\Pages\ManageLocationCorrections;
use App\Models\LocationCorrection;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationCorrectionResource extends Resource
{
    protected static ?string $model = LocationCorrection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $modelLabel = 'corecție de locație';

    protected static ?string $pluralModelLabel = 'corecții de locații';

    protected static ?string $navigationLabel = 'Corecții locații';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('location.name')
                    ->label('Locația'),
                TextEntry::make('club.name')
                    ->label('Propusă de')
                    ->placeholder('—'),
                TextEntry::make('field')
                    ->label('Câmpul semnalat')
                    ->formatStateUsing(fn (LocationCorrectionField $state): string => $state->label()),
                // The two values side by side: the whole review is deciding
                // between them, so nothing else belongs between them.
                TextEntry::make('current_value')
                    ->label('Valoarea actuală')
                    ->state(fn (LocationCorrection $record): string => (string) $record->location?->{$record->field->value}),
                TextEntry::make('suggested_value')
                    ->label('Valoarea propusă'),
                TextEntry::make('note')
                    ->label('Motivul clubului')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Waiting requests first: they are the only rows needing action.
            ->defaultSort('status')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'club']))
            ->columns([
                TextColumn::make('location.name')
                    ->label('Locația')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('field')
                    ->label('Câmp')
                    ->badge()
                    ->formatStateUsing(fn (LocationCorrectionField $state): string => $state->label()),
                TextColumn::make('current_value')
                    ->label('Acum')
                    ->state(fn (LocationCorrection $record): string => (string) $record->location?->{$record->field->value})
                    ->wrap(),
                TextColumn::make('suggested_value')
                    ->label('Propus')
                    ->wrap(),
                TextColumn::make('club.name')
                    ->label('Propusă de')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (LocationCorrectionStatus $state): string => $state->label())
                    ->color(fn (LocationCorrectionStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Trimisă')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('apply')
                    ->label('Aplică')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aplică corecția')
                    ->modalDescription(fn (LocationCorrection $record): string => 'Câmpul „'.$record->field->label()
                        .'" al locației devine „'.$record->suggested_value.'". Se vede imediat public, pentru toate cluburile de la această locație.')
                    ->visible(fn (LocationCorrection $record): bool => $record->isPending())
                    ->action(function (LocationCorrection $record): void {
                        $reviewer = Filament::auth()->user();

                        if (! $reviewer instanceof User) {
                            return;
                        }

                        $record->apply($reviewer);

                        Notification::make()
                            ->success()
                            ->title('Corecția a fost aplicată')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Respinge')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LocationCorrection $record): bool => $record->isPending())
                    ->action(function (LocationCorrection $record): void {
                        $reviewer = Filament::auth()->user();

                        if (! $reviewer instanceof User) {
                            return;
                        }

                        $record->reject($reviewer);

                        Notification::make()
                            ->success()
                            ->title('Propunerea a fost respinsă')
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            // Deliberately no bulk apply: each request is a claim about the real
            // world that someone has to actually weigh.
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLocationCorrections::route('/'),
        ];
    }
}
