@php
    $statePath = $getStatePath();
    $webKey = config('filament-google-maps.keys.web_key');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="locationMap(@js($statePath), @js($webKey))"
        x-on:location-map-goto.window="goto($event.detail)"
    >
        <div x-ref="map" class="rounded-lg overflow-hidden" style="height: 420px; width: 100%;"></div>
    </div>
</x-dynamic-component>
