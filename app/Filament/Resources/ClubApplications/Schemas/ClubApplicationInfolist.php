<?php

namespace App\Filament\Resources\ClubApplications\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ClubApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('club_name'),
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
                TextEntry::make('message')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('reviewed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('reviewed_by')
                    ->numeric()
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
