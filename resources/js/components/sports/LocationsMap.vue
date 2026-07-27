<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { loadGoogleMaps, MAP_OPTIONS, pinIcon } from '@/lib/maps';

export type MapLocation = {
    slug: string;
    name: string;
    lat: number | null;
    lng: number | null;
};

const props = defineProps<{
    locations: MapLocation[];
    apiKey: string;
    /** Falls back to this when no location has coordinates yet. */
    center?: { lat: number; lng: number } | null;
    activeSlug?: string | null;
    /** Zoom used when there is a single pin to show. */
    singleZoom?: number;
}>();

const emit = defineEmits<{ select: [slug: string | null] }>();

const canvas = ref<HTMLElement | null>(null);
const failed = ref(false);

let map: any = null;
let markers: any[] = [];

/** Locations we can actually put on a map. */
function mappable(): MapLocation[] {
    return props.locations.filter((l) => l.lat !== null && l.lng !== null);
}

function clearMarkers(): void {
    markers.forEach((marker) => marker.setMap(null));
    markers = [];
}

function draw(): void {
    const google = (window as any).google;

    if (!google?.maps || !map) {
        return;
    }

    clearMarkers();

    const points = mappable();
    const bounds = new google.maps.LatLngBounds();

    points.forEach((location) => {
        const position = {
            lat: location.lat as number,
            lng: location.lng as number,
        };

        const marker = new google.maps.Marker({
            position,
            map,
            title: location.name,
            icon: pinIcon(location.slug === props.activeSlug),
            zIndex: location.slug === props.activeSlug ? 999 : undefined,
        });

        marker.addListener('click', () => emit('select', location.slug));
        markers.push(marker);
        bounds.extend(position);
    });

    if (points.length > 1) {
        map.fitBounds(bounds, 48);

        return;
    }

    if (points.length === 1) {
        map.setCenter(bounds.getCenter());
        map.setZoom(props.singleZoom ?? 15);
    }
}

onMounted(async () => {
    try {
        await loadGoogleMaps(props.apiKey);
    } catch {
        failed.value = true;

        return;
    }

    const google = (window as any).google;

    map = new google.maps.Map(canvas.value, {
        center: props.center ?? { lat: 45.9432, lng: 24.9668 },
        zoom: props.center ? 12 : 7,
        ...MAP_OPTIONS,
    });

    // Clicking empty map closes whatever card is open.
    map.addListener('click', () => emit('select', null));

    draw();
});

watch(() => [props.locations, props.activeSlug], draw, { deep: true });

onBeforeUnmount(clearMarkers);
</script>

<template>
    <div class="relative h-full w-full">
        <div ref="canvas" class="h-full w-full" />
        <div
            v-if="failed"
            class="absolute inset-0 flex items-center justify-center bg-[#eef2ea] text-center text-[13.5px] text-sage"
        >
            Harta nu a putut fi încărcată.
        </div>
    </div>
</template>
