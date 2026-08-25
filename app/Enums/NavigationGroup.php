<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The organization panel's sidebar: one group per offer, in the order a visitor
 * meets them on the public side.
 *
 * The three are the three ways in — a programme you enrol in, a space you pay to
 * enter, a session you book — which is also what decides whether an organization
 * reads as a club, a venue or a practice. Nothing is hidden when an organization
 * does not do one of them: offers are additive, and a club that wanted to start
 * renting its hall would never find the screen if the group only appeared once
 * it already had a space.
 *
 * Locations and people sit outside any group, above them all: every offer hangs
 * off an address, and the same people run the courses and give the services.
 */
enum NavigationGroup implements HasLabel
{
    case Courses;

    case Leisure;

    case Services;

    public function getLabel(): string
    {
        return match ($this) {
            self::Courses => 'Cursuri și clase',
            self::Leisure => 'Agrement și închiriere',
            self::Services => 'Servicii',
        };
    }

    /**
     * No icon on purpose: Filament refuses a group icon when its items carry
     * their own, and the items are the ones a person clicks.
     */
}
