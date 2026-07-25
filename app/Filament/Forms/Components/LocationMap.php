<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * A Google map field with a draggable marker, bound to a {lat, lng} state.
 *
 * Unlike the packaged Map field it exposes per-step centre/zoom control via a
 * `location-map-goto` browser event (dispatched from the form when the county /
 * city / address is geocoded), so each step can zoom to the right level.
 */
class LocationMap extends Field
{
    protected string $view = 'filament.forms.components.location-map';
}
