<?php

namespace App\Filament\Admin\Resources\OrganizationApplications\Schemas;

use App\Enums\OrganizationApplicationStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrganizationApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('fiscal_code'),
                TextEntry::make('company_name')
                    ->placeholder('-'),
                IconEntry::make('is_vat_payer')
                    ->boolean()
                    ->placeholder('-'),
                TextEntry::make('contact_name'),
                TextEntry::make('contact_role')
                    ->placeholder('-'),
                TextEntry::make('contact_email'),
                TextEntry::make('contact_phone')
                    ->placeholder('-'),
                TextEntry::make('county')
                    ->placeholder('-'),
                TextEntry::make('city')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->label('Stare')
                    ->badge()
                    ->formatStateUsing(fn (OrganizationApplicationStatus $state): string => $state->label())
                    ->color(fn (OrganizationApplicationStatus $state): string => $state->color()),
                TextEntry::make('organization.name')
                    ->label('Organizația creată')
                    ->placeholder('-'),
                TextEntry::make('rejection_reason')
                    ->label('Motivul respingerii')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('reviewed_at')
                    ->label('Analizată')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('reviewer.name')
                    ->label('De către')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
