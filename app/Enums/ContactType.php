<?php

namespace App\Enums;

enum ContactType: string
{
    case Phone = 'phone';
    case Email = 'email';
    case Website = 'website';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Tiktok = 'tiktok';
    case Youtube = 'youtube';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Telefon',
            self::Email => 'Email',
            self::Website => 'Website',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Tiktok => 'TikTok',
            self::Youtube => 'YouTube',
        };
    }
}
