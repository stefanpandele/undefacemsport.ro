<script>
    (function () {
        if (window.__locationMapRegistered) {
            return;
        }
        window.__locationMapRegistered = true;

        window.__loadGoogleMaps = function (key) {
            if (window.google && window.google.maps) {
                return Promise.resolve();
            }
            if (window.__googleMapsPromise) {
                return window.__googleMapsPromise;
            }
            window.__googleMapsPromise = new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = 'https://maps.googleapis.com/maps/api/js?key=' + key;
                s.async = true;
                s.defer = true;
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
            return window.__googleMapsPromise;
        };

        var register = function (Alpine) {
            Alpine.data('locationMap', function (statePath, webKey) {
                return {
                    map: null,
                    marker: null,

                    async init() {
                        try {
                            await window.__loadGoogleMaps(webKey);
                        } catch (e) {
                            console.error('[LocationMap] Google Maps failed to load', e);
                            return;
                        }

                        var romania = { lat: 45.9432, lng: 24.9668 };
                        var state = this.$wire.get(statePath);
                        var has = state && state.lat && state.lng;
                        var start = has ? { lat: +state.lat, lng: +state.lng } : romania;

                        this.map = new google.maps.Map(this.$refs.map, {
                            center: start,
                            zoom: has ? 15 : 7,
                            mapTypeControl: false,
                            streetViewControl: false,
                        });

                        this.marker = new google.maps.Marker({
                            map: this.map,
                            draggable: true,
                            position: has ? start : null,
                        });

                        var self = this;
                        this.marker.addListener('dragend', function (e) {
                            self.$wire.set(statePath, { lat: e.latLng.lat(), lng: e.latLng.lng() });
                        });
                    },

                    goto(detail) {
                        var d = (detail && detail.params) ? detail.params : detail;
                        if (!this.map || !d || d.lat == null) {
                            return;
                        }
                        var p = { lat: +d.lat, lng: +d.lng };
                        this.map.setCenter(p);
                        this.map.setZoom(d.zoom || 13);
                        this.marker.setPosition(p);
                        // Our own amber pin when the address is an existing location.
                        this.marker.setIcon(d.existing ? {
                            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
                                '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24">' +
                                '<path fill="#f59e0b" stroke="#ffffff" stroke-width="1.2" d="M12 2C8.1 2 5 5.1 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.9-3.1-7-7-7z"/>' +
                                '<circle cx="12" cy="9" r="2.6" fill="#ffffff"/></svg>'),
                            scaledSize: new google.maps.Size(44, 44),
                            anchor: new google.maps.Point(22, 44),
                        } : null);
                    },
                };
            });
        };

        if (window.Alpine) {
            register(window.Alpine);
        } else {
            document.addEventListener('alpine:init', function () {
                register(window.Alpine);
            });
        }
    })();
</script>
