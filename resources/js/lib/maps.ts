/**
 * The one place that decides how our maps load and look. Both the explore page
 * and the location page render the same map, so anything visual lives here
 * rather than in a component — otherwise the two drift apart the first time one
 * of them is tweaked.
 */

let loader: Promise<void> | null = null;

/** Loads the Google Maps JS API once per page, whoever asks first. */
export function loadGoogleMaps(key: string): Promise<void> {
    if (!key) {
        return Promise.reject(new Error('Missing Google Maps key'));
    }

    if ((window as any).google?.maps) {
        return Promise.resolve();
    }

    loader ??= new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');

        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}`;
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Google Maps failed to load'));

        document.head.appendChild(script);
    });

    return loader;
}

/**
 * One colour for every pin: on a map, colour should mean "this is ours".
 * Clay, the only warm tone on the site — against a map made of greens and
 * blues, nothing else competes with it. The selected pin goes a shade deeper.
 */
export const PIN_COLOR = '#FF5A2C';
export const PIN_COLOR_ACTIVE = '#C2350D';

/**
 * Google draws its own pins for parks, malls, schools and the rest. They look
 * exactly like ours and carry none of our meaning, so on a map whose only job
 * is "where can I train", they are noise the visitor has to filter out.
 *
 * Places are switched off whole, then parks and sports grounds come back as
 * shape only — no pin, no name. Together with the water they are what lets
 * someone recognise their own neighbourhood, which street lines never manage.
 */
export const MAP_STYLES = [
    { featureType: 'poi', stylers: [{ visibility: 'off' }] },
    {
        featureType: 'poi.park',
        elementType: 'geometry',
        stylers: [{ visibility: 'on' }, { color: '#c3e2c8' }],
    },
    {
        featureType: 'poi.sports_complex',
        elementType: 'geometry',
        stylers: [{ visibility: 'on' }, { color: '#cde7cf' }],
    },
    {
        featureType: 'transit',
        elementType: 'labels.icon',
        stylers: [{ visibility: 'off' }],
    },
    // Route shields (DN1, A3, E60) are for someone driving across the country,
    // not for someone picking a hall in their own city. Street names stay.
    {
        featureType: 'road',
        elementType: 'labels.icon',
        stylers: [{ visibility: 'off' }],
    },

    // Base map toned into the site's palette, but not washed out: water still
    // reads as water and main roads still stand out from side streets. Only the
    // hues Google uses to shout — the reds and yellows on arteries — are gone,
    // so the pins are the one saturated thing on screen.
    {
        elementType: 'labels.text.fill',
        stylers: [{ color: '#4a5a50' }],
    },
    {
        elementType: 'labels.text.stroke',
        stylers: [{ color: '#ffffff' }, { weight: 3 }],
    },
    {
        featureType: 'landscape.natural',
        elementType: 'geometry',
        stylers: [{ color: '#dcecd8' }],
    },
    // Built-up ground, grey enough that the white roads cut through it. When
    // this sat near white, the street network dissolved into the background.
    {
        featureType: 'landscape.man_made',
        elementType: 'geometry',
        stylers: [{ color: '#e3e7de' }],
    },
    {
        featureType: 'administrative',
        elementType: 'geometry.stroke',
        stylers: [{ color: '#c9d4c2' }],
    },
    // Every road white. The hierarchy comes from the outline instead of the
    // fill: side streets barely outlined, arteries and highways darker, so the
    // network still reads without introducing a second warm colour.
    {
        featureType: 'road',
        elementType: 'geometry.fill',
        stylers: [{ color: '#ffffff' }],
    },
    {
        featureType: 'road',
        elementType: 'geometry.stroke',
        stylers: [{ color: '#dce3d6' }],
    },
    {
        featureType: 'road.arterial',
        elementType: 'geometry.stroke',
        stylers: [{ color: '#cbd6c2' }],
    },
    {
        featureType: 'road.highway',
        elementType: 'geometry.stroke',
        stylers: [{ color: '#b7c5ad' }],
    },
    {
        featureType: 'water',
        elementType: 'geometry',
        stylers: [{ color: '#a8cfe0' }],
    },
    {
        featureType: 'water',
        elementType: 'labels.text.fill',
        stylers: [{ color: '#4f7a8c' }],
    },
];

/** Chrome we always strip: the map is a picture of where things are, not a tool. */
export const MAP_OPTIONS = {
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: false,
    clickableIcons: false,
    styles: MAP_STYLES,
};

/** A teardrop pin, deeper and larger when it is the selected one. */
export function pinIcon(active = false): object {
    const google = (window as any).google;

    return {
        path: 'M 0,0 C -2,-20 -10,-22 -10,-30 A 10,10 0 1,1 10,-30 C 10,-22 2,-20 0,0 z',
        fillColor: active ? PIN_COLOR_ACTIVE : PIN_COLOR,
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 2,
        scale: active ? 0.9 : 0.7,
        anchor: new google.maps.Point(0, 0),
    };
}
