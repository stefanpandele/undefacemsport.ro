<?php

namespace App\Filament\Admin\Resources\OrganizationApplications\Schemas;

use App\Enums\OrganizationApplicationStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganizationApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('club_name')
                    ->required(),
                TextInput::make('fiscal_code')
                    ->required(),
                TextInput::make('company_name')
                    ->default(null),
                Toggle::make('is_vat_payer'),
                TextInput::make('contact_name')
                    ->required(),
                TextInput::make('contact_role')
                    ->default(null),
                TextInput::make('contact_email')
                    ->email()
                    ->required(),
                TextInput::make('contact_phone')
                    ->tel()
                    ->default(null),
                TextInput::make('county')
                    ->default(null),
                TextInput::make('city')
                    ->default(null),
                Textarea::make('message')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(OrganizationApplicationStatus::class)
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('reviewed_at'),
                TextInput::make('reviewed_by')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
