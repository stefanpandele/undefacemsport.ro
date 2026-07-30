<?php

namespace App\Filament\Admin\Resources\LocationClaims;

use App\Enums\LocationClaimStatus;
use App\Filament\Admin\Resources\LocationClaims\Pages\ManageLocationClaims;
use App\Models\LocationClaim;
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

/**
 * Requests to become the authoritative editor of a shared location.
 *
 * Reviewed by hand on purpose: at this volume a person reading the evidence is
 * cheaper and safer than matching a fiscal code against an address nobody has
 * verified either. Approving hands over the fields describing the place, and
 * nothing belonging to the clubs training there.
 */
class LocationClaimResource extends Resource
{
    protected static ?string $model = LocationClaim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'revendicare de locație';

    protected static ?string $pluralModelLabel = 'revendicări de locații';

    protected static ?string $navigationLabel = 'Revendicări';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('location.name')
                    ->label('Locația')
                    ->helperText(fn (LocationClaim $record): string => (string) $record->location->address),
                TextEntry::make('organization.name')
                    ->label('Cine revendică'),
                TextEntry::make('organization.company_name')
                    ->label('Firma')
                    ->placeholder('—'),
                TextEntry::make('organization.fiscal_code')
                    ->label('CUI')
                    ->placeholder('—'),
                // The whole review is reading this, so it gets the full width.
                TextEntry::make('evidence')
                    ->label('Ce spune organizația')
                    ->columnSpanFull(),
                TextEntry::make('current')
                    ->label('Deținută acum de')
                    ->state(fn (LocationClaim $record): string => static::holderName($record, 'Nimeni'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Waiting claims first: the only rows needing a decision.
            ->defaultSort('status')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'location.claimedByOrganization',
                'organization',
            ]))
            ->columns([
                TextColumn::make('location.name')
                    ->label('Locația')
                    ->description(fn (LocationClaim $record): string => (string) $record->location->city)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization.name')
                    ->label('Cine revendică')
                    ->searchable(),
                TextColumn::make('organization.fiscal_code')
                    ->label('CUI')
                    ->placeholder('—'),
                TextColumn::make('holder')
                    ->label('Deținută acum')
                    ->state(fn (LocationClaim $record): string => static::holderName($record, 'nimeni')),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (LocationClaimStatus $state): string => $state->label())
                    ->color(fn (LocationClaimStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Trimisă')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprobă')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobă revendicarea')
                    ->modalDescription(fn (LocationClaim $record): string => $record->organization->name
                        .' devine cea care ține la zi datele locației „'.$record->location->name
                        .'". Programele și orarele cluburilor de acolo rămân neatinse. Orice altă cerere pentru acest loc se respinge automat.')
                    ->visible(fn (LocationClaim $record): bool => $record->isPending())
                    ->action(function (LocationClaim $record): void {
                        $reviewer = Filament::auth()->user();

                        if (! $reviewer instanceof User) {
                            return;
                        }

                        $record->approve($reviewer);

                        Notification::make()
                            ->success()
                            ->title('Revendicarea a fost aprobată')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Respinge')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LocationClaim $record): bool => $record->isPending())
                    ->action(function (LocationClaim $record): void {
                        $reviewer = Filament::auth()->user();

                        if (! $reviewer instanceof User) {
                            return;
                        }

                        $record->reject($reviewer);

                        Notification::make()
                            ->success()
                            ->title('Revendicarea a fost respinsă')
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            // Deliberately no bulk approve: handing a place to the wrong company is
            // exactly the mistake reading the evidence exists to prevent.
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Who holds the pen on the place this claim is about, or that nobody does.
     */
    protected static function holderName(LocationClaim $claim, string $fallback): string
    {
        $holder = $claim->location->claimedByOrganization;

        return $holder === null ? $fallback : $holder->name;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLocationClaims::route('/'),
        ];
    }
}
