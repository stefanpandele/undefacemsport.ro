<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Well-known sports venues across six cities, so the explore page has real
     * places to list, filter and put on the map. Coordinates are approximate —
     * accurate enough to drop the pin in the right neighbourhood, not surveyed.
     *
     * Locations are shared between clubs by design, so a handful of venues per
     * city is what makes the "many clubs, one place" model visible.
     *
     * @var list<array{name: string, county: string, city: string, address: string, lat: float, lng: float, facilities: list<string>}>
     */
    private const VENUES = [
        // Cluj-Napoca
        ['name' => 'Sala Polivalentă Cluj-Napoca', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 4', 'lat' => 46.7625, 'lng' => 23.5720, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat', 'Acces persoane cu dizabilități']],
        ['name' => 'Cluj Arena', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 2', 'lat' => 46.7686, 'lng' => 23.5720, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn', 'Cabinet medical', 'Supraveghere video']],
        ['name' => 'Bazinul Olimpic Cluj', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 1', 'lat' => 46.7660, 'lng' => 23.5735, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Cabinet medical', 'Bar / Bufet']],

        // București
        ['name' => 'Sala Polivalentă București', 'county' => 'București', 'city' => 'București', 'address' => 'Bulevardul Tineretului 1', 'lat' => 44.4090, 'lng' => 26.1080, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat', 'Wi-Fi', 'Acces persoane cu dizabilități']],
        ['name' => 'Complexul Sportiv Lia Manoliu', 'county' => 'București', 'city' => 'București', 'address' => 'Bulevardul Basarabia 37-39', 'lat' => 44.4370, 'lng' => 26.1520, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn', 'Cabinet medical']],
        ['name' => 'Bazinul Dinamo', 'county' => 'București', 'city' => 'București', 'address' => 'Șoseaua Ștefan cel Mare 7-9', 'lat' => 44.4525, 'lng' => 26.1010, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Parcare', 'Bar / Bufet']],

        // Brașov
        ['name' => 'Sala Sporturilor Dumitru Popescu Colibași', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Strada Iuliu Maniu 27', 'lat' => 45.6520, 'lng' => 25.6100, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Încălzire']],
        ['name' => 'Stadionul Silviu Ploeșteanu', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Strada Stadionului 1', 'lat' => 45.6440, 'lng' => 25.5920, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn']],
        ['name' => 'Bazinul Olimpic Brașov', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Strada Lungă 12', 'lat' => 45.6480, 'lng' => 25.5800, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Cabinet medical', 'Acces persoane cu dizabilități']],

        // Timișoara
        ['name' => 'Sala Constantin Jude', 'county' => 'Timiș', 'city' => 'Timișoara', 'address' => 'Strada Ștefan cel Mare 2', 'lat' => 45.7480, 'lng' => 21.2270, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat']],
        ['name' => 'Stadionul Dan Păltinișanu', 'county' => 'Timiș', 'city' => 'Timișoara', 'address' => 'Aleea F.C. Ripensia 11', 'lat' => 45.7420, 'lng' => 21.2360, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn', 'Supraveghere video']],
        ['name' => 'Complexul Sportiv Bega', 'county' => 'Timiș', 'city' => 'Timișoara', 'address' => 'Splaiul Nicolae Titulescu 5', 'lat' => 45.7530, 'lng' => 21.2200, 'facilities' => ['Vestiare', 'Dușuri', 'Bar / Bufet', 'Wi-Fi']],

        // Iași
        ['name' => 'Sala Polivalentă Iași', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'Strada Sfântul Lazăr 47', 'lat' => 47.1600, 'lng' => 27.5900, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat', 'Wi-Fi']],
        ['name' => 'Stadionul Emil Alexandrescu', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'Strada Carol I 40', 'lat' => 47.1830, 'lng' => 27.5710, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn']],
        ['name' => 'Bazinul Olimpic Iași', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'Strada Toma Cozma 3', 'lat' => 47.1720, 'lng' => 27.5760, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Cabinet medical']],

        // Constanța
        ['name' => 'Sala Sporturilor Constanța', 'county' => 'Constanța', 'city' => 'Constanța', 'address' => 'Bulevardul Tomis 99', 'lat' => 44.1900, 'lng' => 28.6300, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat']],
        ['name' => 'Complexul de Nataţie Constanța', 'county' => 'Constanța', 'city' => 'Constanța', 'address' => 'Bulevardul Mamaia 132', 'lat' => 44.1980, 'lng' => 28.6420, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Bar / Bufet', 'Acces persoane cu dizabilități']],
        ['name' => 'Baza Sportivă Badea Cârțan', 'county' => 'Constanța', 'city' => 'Constanța', 'address' => 'Strada Badea Cârțan 12', 'lat' => 44.1750, 'lng' => 28.6390, 'facilities' => ['Parcare', 'Vestiare', 'Iluminat nocturn', 'Supraveghere video']],
    ];

    /**
     * Seed the shared venues and their amenities. Idempotent: locations are
     * keyed by (county, city, address) and amenities are only ever added, the
     * same rule the club panel follows for shared places.
     */
    public function run(): void
    {
        $facilityIds = Facility::query()->pluck('id', 'name');

        foreach (self::VENUES as $venue) {
            $location = Location::firstOrCreate(
                [
                    'county' => $venue['county'],
                    'city' => $venue['city'],
                    'address' => $venue['address'],
                ],
                [
                    'name' => $venue['name'],
                    'latitude' => $venue['lat'],
                    'longitude' => $venue['lng'],
                ],
            );

            $ids = collect($venue['facilities'])
                ->map(fn (string $name): ?int => $facilityIds->get($name))
                ->filter()
                ->all();

            $location->facilities()->syncWithoutDetaching($ids);
        }
    }
}
