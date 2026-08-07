<?php

namespace App\Filament\Admin\Resources\OrganizationApplications\Tables;

use App\Enums\OrganizationApplicationStatus;
use App\Enums\OrganizationType;
use App\Models\OrganizationApplication;
use App\Models\User;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrganizationApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (OrganizationType $state): string => $state->label())
                    ->color(fn (OrganizationType $state): string => match ($state) {
                        OrganizationType::Club => 'success',
                        OrganizationType::Venue => 'warning',
                        OrganizationType::Practice => 'info',
                    })
                    ->sortable(),
                TextColumn::make('fiscal_code')
                    ->searchable(),
                TextColumn::make('company_name')
                    ->searchable(),
                IconColumn::make('is_vat_payer')
                    ->boolean(),
                TextColumn::make('contact_name')
                    ->searchable(),
                TextColumn::make('contact_role')
                    ->searchable(),
                TextColumn::make('contact_email')
                    ->searchable(),
                TextColumn::make('contact_phone')
                    ->searchable(),
                TextColumn::make('county')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stare')
                    ->badge()
                    ->formatStateUsing(fn (OrganizationApplicationStatus $state): string => $state->label())
                    ->color(fn (OrganizationApplicationStatus $state): string => $state->color()),
                TextColumn::make('rejection_reason')
                    ->label('Motivul respingerii')
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('organization.name')
                    ->label('Organizația creată')
                    ->placeholder('-'),
                TextColumn::make('reviewed_at')
                    ->label('Analizată')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewer.name')
                    ->label('De către')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip')
                    ->options(OrganizationType::options()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprobă')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobă cererea')
                    ->modalDescription(fn (OrganizationApplication $record): string => 'Se creează organizația „'.$record->name
                        .'" de tip '.$record->type->label().', pe planul gratuit. Nu se creează niciun cont de utilizator, '
                        .'deci solicitantul încă nu se poate autentifica.')
                    ->visible(fn (OrganizationApplication $record): bool => $record->isPending())
                    ->action(function (OrganizationApplication $record): void {
                        $reviewer = Filament::auth()->user();

                        if (! $reviewer instanceof User) {
                            return;
                        }

                        try {
                            $organization = $record->approve($reviewer);
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Cererea nu a putut fi aprobată')
                                ->body($exception->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Cererea a fost aprobată')
                            ->body('Organizația „'.$organization->name.'" a fost creată.')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Respinge')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->modalHeading('Respinge cererea')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Motivul respingerii')
                            ->helperText('Ce anume nu e în regulă. Solicitantul are dreptul să știe ce să corecteze.')
                            ->required()
                            ->maxLength(1000)
                            ->rows(4),
                    ])
                    ->visible(fn (OrganizationApplication $record): bool => $record->isPending())
                    ->action(function (OrganizationApplication $record, array $data): void {
                        $reviewer = Filament::auth()->user();

                        if (! $reviewer instanceof User) {
                            return;
                        }

                        $record->reject($reviewer, $data['rejection_reason']);

                        Notification::make()
                            ->success()
                            ->title('Cererea a fost respinsă')
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
