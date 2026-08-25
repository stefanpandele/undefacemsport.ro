<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Well-known sports venues across the country, so the explore page has real
     * places to list, filter and put on the map. Coordinates are approximate —
     * accurate enough to drop the pin in the right neighbourhood, not surveyed.
     *
     * Locations are shared between clubs by design, so several venues per city is
     * what makes the "many clubs, one place" model visible.
     *
     * Some entries deliberately sit within a couple of hundred metres of each
     * other — the halls, pool and pitches of one complex really do share a corner
     * of a city. They are the hard case for duplicate detection, and the reason
     * proximity is only ever allowed to raise a question: a radius cannot tell a
     * second venue in the same complex from a second spelling of the first one.
     *
     * @var list<array{name: string, county: string, city: string, address: string, lat: float, lng: float, facilities: list<string>}>
     */
    private const VENUES = [
        // Cluj-Napoca — the Aleea Stadionului cluster is three distinct venues
        // within ~300m of each other.
        ['name' => 'Sala Polivalentă Cluj-Napoca', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 4', 'lat' => 46.7625, 'lng' => 23.5720, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat', 'Acces persoane cu dizabilități']],
        ['name' => 'Cluj Arena', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 2', 'lat' => 46.7686, 'lng' => 23.5720, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn', 'Cabinet medical', 'Supraveghere video']],
        ['name' => 'Bazinul Olimpic Cluj', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 1', 'lat' => 46.7660, 'lng' => 23.5735, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Cabinet medical', 'Bar / Bufet']],
        ['name' => 'Sala Sporturilor Horia Demian', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Splaiul Independenței 6', 'lat' => 46.7690, 'lng' => 23.5762, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Încălzire']],
        ['name' => 'Complexul Sportiv Iuliu Hațieganu', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Pandurilor 7', 'lat' => 46.7618, 'lng' => 23.5688, 'facilities' => ['Vestiare', 'Dușuri', 'Iluminat nocturn', 'Tribună']],
        ['name' => 'Baza Sportivă Gheorgheni', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Alexandru Vaida Voevod 53', 'lat' => 46.7712, 'lng' => 23.6208, 'facilities' => ['Parcare', 'Vestiare', 'Iluminat nocturn', 'Bar / Bufet']],
        ['name' => 'Bazinul Someș', 'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Ion Meșter 10', 'lat' => 46.7758, 'lng' => 23.5918, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire']],

        // București — Ștefan cel Mare 7-9 and 9 are the pool and the halls of the
        // same complex, ~80m apart.
        ['name' => 'Sala Polivalentă București', 'county' => 'București', 'city' => 'București - Sector 4', 'address' => 'Bulevardul Tineretului 1', 'lat' => 44.4090, 'lng' => 26.1080, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat', 'Wi-Fi', 'Acces persoane cu dizabilități']],
        ['name' => 'Complexul Sportiv Lia Manoliu', 'county' => 'București', 'city' => 'București - Sector 2', 'address' => 'Bulevardul Basarabia 37-39', 'lat' => 44.4370, 'lng' => 26.1520, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn', 'Cabinet medical']],
        ['name' => 'Bazinul Dinamo', 'county' => 'București', 'city' => 'București - Sector 2', 'address' => 'Șoseaua Ștefan cel Mare 7-9', 'lat' => 44.4525, 'lng' => 26.1010, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Parcare', 'Bar / Bufet']],
        ['name' => 'Sala Polivalentă Dinamo', 'county' => 'București', 'city' => 'București - Sector 2', 'address' => 'Șoseaua Ștefan cel Mare 9', 'lat' => 44.4531, 'lng' => 26.1016, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună', 'Parcare']],
        ['name' => 'Complexul Sportiv Giulești', 'county' => 'București', 'city' => 'București - Sector 6', 'address' => 'Calea Giulești 18', 'lat' => 44.4572, 'lng' => 26.0531, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn']],
        ['name' => 'Sala Sporturilor Apollo', 'county' => 'București', 'city' => 'București - Sector 2', 'address' => 'Strada Maior Coravu 22', 'lat' => 44.4318, 'lng' => 26.1424, 'facilities' => ['Vestiare', 'Dușuri', 'Aer condiționat', 'Wi-Fi']],
        ['name' => 'Baza Sportivă Tei', 'county' => 'București', 'city' => 'București - Sector 2', 'address' => 'Strada Barbu Văcărescu 5', 'lat' => 44.4632, 'lng' => 26.1005, 'facilities' => ['Parcare', 'Vestiare', 'Iluminat nocturn', 'Bar / Bufet', 'Supraveghere video']],
        ['name' => 'Complexul Sportiv Olimpia', 'county' => 'București', 'city' => 'București - Sector 2', 'address' => 'Strada Dr. Staicovici 42', 'lat' => 44.4260, 'lng' => 26.0745, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună', 'Parcare']],

        // Brașov
        ['name' => 'Sala Sporturilor Dumitru Popescu Colibași', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Strada Iuliu Maniu 27', 'lat' => 45.6520, 'lng' => 25.6100, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Încălzire']],
        ['name' => 'Stadionul Silviu Ploeșteanu', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Strada Stadionului 1', 'lat' => 45.6440, 'lng' => 25.5920, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn']],
        ['name' => 'Bazinul Olimpic Brașov', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Strada Lungă 12', 'lat' => 45.6480, 'lng' => 25.5800, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Cabinet medical', 'Acces persoane cu dizabilități']],
        ['name' => 'Patinoarul Olimpia Brașov', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Strada Nicolae Titulescu 1', 'lat' => 45.6512, 'lng' => 25.6042, 'facilities' => ['Vestiare', 'Dușuri', 'Bar / Bufet', 'Parcare']],
        ['name' => 'Baza Sportivă Tractorul', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Strada Turnului 5', 'lat' => 45.6698, 'lng' => 25.6015, 'facilities' => ['Parcare', 'Vestiare', 'Iluminat nocturn']],

        // Timișoara
        ['name' => 'Sala Constantin Jude', 'county' => 'Timiș', 'city' => 'Timișoara', 'address' => 'Strada Ștefan cel Mare 2', 'lat' => 45.7480, 'lng' => 21.2270, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat']],
        ['name' => 'Stadionul Dan Păltinișanu', 'county' => 'Timiș', 'city' => 'Timișoara', 'address' => 'Aleea F.C. Ripensia 11', 'lat' => 45.7420, 'lng' => 21.2360, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn', 'Supraveghere video']],
        ['name' => 'Complexul Sportiv Bega', 'county' => 'Timiș', 'city' => 'Timișoara', 'address' => 'Splaiul Nicolae Titulescu 5', 'lat' => 45.7530, 'lng' => 21.2200, 'facilities' => ['Vestiare', 'Dușuri', 'Bar / Bufet', 'Wi-Fi']],
        ['name' => 'Bazinul Olimpia Timișoara', 'county' => 'Timiș', 'city' => 'Timișoara', 'address' => 'Strada Aristide Demetriade 1', 'lat' => 45.7548, 'lng' => 21.2258, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire']],
        ['name' => 'Sala Olimpia', 'county' => 'Timiș', 'city' => 'Timișoara', 'address' => 'Strada Arieș 19', 'lat' => 45.7368, 'lng' => 21.2352, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună', 'Încălzire']],

        // Iași
        ['name' => 'Sala Polivalentă Iași', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'Strada Sfântul Lazăr 47', 'lat' => 47.1600, 'lng' => 27.5900, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat', 'Wi-Fi']],
        ['name' => 'Stadionul Emil Alexandrescu', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'Strada Carol I 40', 'lat' => 47.1830, 'lng' => 27.5710, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn']],
        ['name' => 'Bazinul Olimpic Iași', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'Strada Toma Cozma 3', 'lat' => 47.1720, 'lng' => 27.5760, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Cabinet medical']],
        ['name' => 'Sala Sporturilor Iași', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'Strada Sfântul Lazăr 49', 'lat' => 47.1608, 'lng' => 27.5912, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună']],
        ['name' => 'Baza Sportivă Ciric', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'Aleea Ciric 1', 'lat' => 47.1852, 'lng' => 27.6118, 'facilities' => ['Parcare', 'Vestiare', 'Iluminat nocturn', 'Bar / Bufet']],

        // Constanța
        ['name' => 'Sala Sporturilor Constanța', 'county' => 'Constanța', 'city' => 'Constanța', 'address' => 'Bulevardul Tomis 99', 'lat' => 44.1900, 'lng' => 28.6300, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat']],
        ['name' => 'Complexul de Nataţie Constanța', 'county' => 'Constanța', 'city' => 'Constanța', 'address' => 'Bulevardul Mamaia 132', 'lat' => 44.1980, 'lng' => 28.6420, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Bar / Bufet', 'Acces persoane cu dizabilități']],
        ['name' => 'Baza Sportivă Badea Cârțan', 'county' => 'Constanța', 'city' => 'Constanța', 'address' => 'Strada Badea Cârțan 12', 'lat' => 44.1750, 'lng' => 28.6390, 'facilities' => ['Parcare', 'Vestiare', 'Iluminat nocturn', 'Supraveghere video']],
        ['name' => 'Stadionul Gheorghe Hagi', 'county' => 'Constanța', 'city' => 'Constanța', 'address' => 'Strada Primăverii 2', 'lat' => 44.1842, 'lng' => 28.6208, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn']],

        // Oradea
        ['name' => 'Arena Antonio Alexe', 'county' => 'Bihor', 'city' => 'Oradea', 'address' => 'Strada Traian Blajovici 2', 'lat' => 47.0508, 'lng' => 21.9412, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat', 'Wi-Fi']],
        ['name' => 'Bazinul Olimpic Ioan Alexandrescu', 'county' => 'Bihor', 'city' => 'Oradea', 'address' => 'Strada Corneliu Coposu 6', 'lat' => 47.0532, 'lng' => 21.9358, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Cabinet medical']],
        ['name' => 'Stadionul Iuliu Bodola', 'county' => 'Bihor', 'city' => 'Oradea', 'address' => 'Strada Primăriei 3', 'lat' => 47.0478, 'lng' => 21.9295, 'facilities' => ['Parcare', 'Vestiare', 'Tribună', 'Iluminat nocturn']],

        // Sibiu
        ['name' => 'Sala Transilvania', 'county' => 'Sibiu', 'city' => 'Sibiu', 'address' => 'Strada Alba Iulia 96', 'lat' => 45.7902, 'lng' => 24.1252, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Încălzire']],
        ['name' => 'Stadionul Municipal Sibiu', 'county' => 'Sibiu', 'city' => 'Sibiu', 'address' => 'Aleea Mihai Eminescu 1', 'lat' => 45.7845, 'lng' => 24.1338, 'facilities' => ['Parcare', 'Vestiare', 'Tribună', 'Iluminat nocturn']],
        ['name' => 'Bazinul Olimpia Sibiu', 'county' => 'Sibiu', 'city' => 'Sibiu', 'address' => 'Strada Constituției 8', 'lat' => 45.7938, 'lng' => 24.1405, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire']],

        // Craiova
        ['name' => 'Stadionul Ion Oblemenco', 'county' => 'Dolj', 'city' => 'Craiova', 'address' => 'Bulevardul Știrbei Vodă 4', 'lat' => 44.3172, 'lng' => 23.7885, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Iluminat nocturn', 'Supraveghere video']],
        ['name' => 'Sala Polivalentă Craiova', 'county' => 'Dolj', 'city' => 'Craiova', 'address' => 'Bulevardul Știrbei Vodă 6', 'lat' => 44.3178, 'lng' => 23.7898, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat']],
        ['name' => 'Complexul Sportiv Craiovița', 'county' => 'Dolj', 'city' => 'Craiova', 'address' => 'Strada Bariera Vâlcii 15', 'lat' => 44.3255, 'lng' => 23.7628, 'facilities' => ['Vestiare', 'Iluminat nocturn', 'Parcare']],

        // Ploiești
        ['name' => 'Sala Sporturilor Olimpia Ploiești', 'county' => 'Prahova', 'city' => 'Ploiești', 'address' => 'Strada Democrației 68', 'lat' => 44.9382, 'lng' => 26.0195, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună', 'Încălzire']],
        ['name' => 'Stadionul Ilie Oană', 'county' => 'Prahova', 'city' => 'Ploiești', 'address' => 'Strada Stadionului 1', 'lat' => 44.9455, 'lng' => 26.0338, 'facilities' => ['Parcare', 'Vestiare', 'Tribună', 'Iluminat nocturn']],

        // Galați
        ['name' => 'Sala Sporturilor Galați', 'county' => 'Galați', 'city' => 'Galați', 'address' => 'Strada Stadionului 1', 'lat' => 45.4308, 'lng' => 28.0402, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună', 'Parcare']],
        ['name' => 'Bazinul Olimpic Galați', 'county' => 'Galați', 'city' => 'Galați', 'address' => 'Strada Gării 24', 'lat' => 45.4342, 'lng' => 28.0345, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire']],

        // Arad
        ['name' => 'Stadionul Francisc von Neuman', 'county' => 'Arad', 'city' => 'Arad', 'address' => 'Strada Gheorghe Lazăr 1', 'lat' => 46.1725, 'lng' => 21.3125, 'facilities' => ['Parcare', 'Vestiare', 'Tribună', 'Iluminat nocturn']],
        ['name' => 'Sala Sporturilor Victoria Arad', 'county' => 'Arad', 'city' => 'Arad', 'address' => 'Strada Mărăști 8', 'lat' => 46.1782, 'lng' => 21.3168, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună']],

        // Pitești
        ['name' => 'Sala Sporturilor Trivale', 'county' => 'Argeș', 'city' => 'Pitești', 'address' => 'Aleea Trivale 25', 'lat' => 44.8508, 'lng' => 24.8562, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună']],
        ['name' => 'Stadionul Nicolae Dobrin', 'county' => 'Argeș', 'city' => 'Pitești', 'address' => 'Bulevardul Petrochimiștilor 15', 'lat' => 44.8672, 'lng' => 24.8752, 'facilities' => ['Parcare', 'Vestiare', 'Tribună', 'Iluminat nocturn']],

        // Bacău
        ['name' => 'Sala Sporturilor Bacău', 'county' => 'Bacău', 'city' => 'Bacău', 'address' => 'Strada Pictor Aman 94', 'lat' => 46.5675, 'lng' => 26.9138, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună', 'Încălzire']],
        ['name' => 'Bazinul Olimpic Bacău', 'county' => 'Bacău', 'city' => 'Bacău', 'address' => 'Calea Mărășești 96', 'lat' => 46.5602, 'lng' => 26.9095, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Acces persoane cu dizabilități']],

        // Târgu Mureș
        ['name' => 'Sala Sporturilor Târgu Mureș', 'county' => 'Mureș', 'city' => 'Târgu Mureș', 'address' => 'Strada Victor Babeș 11', 'lat' => 46.5432, 'lng' => 24.5588, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună']],
        ['name' => 'Complexul de Nataţie Târgu Mureș', 'county' => 'Mureș', 'city' => 'Târgu Mureș', 'address' => 'Strada Plutelor 2', 'lat' => 46.5498, 'lng' => 24.5632, 'facilities' => ['Vestiare', 'Dușuri', 'Încălzire', 'Bar / Bufet']],

        // Baia Mare
        ['name' => 'Sala Polivalentă Lascăr Pană', 'county' => 'Maramureș', 'city' => 'Baia Mare', 'address' => 'Bulevardul Unirii 14', 'lat' => 47.6572, 'lng' => 23.5788, 'facilities' => ['Parcare', 'Vestiare', 'Dușuri', 'Tribună', 'Aer condiționat']],
        ['name' => 'Stadionul Viorel Mateianu', 'county' => 'Maramureș', 'city' => 'Baia Mare', 'address' => 'Strada Someș 3', 'lat' => 47.6608, 'lng' => 23.5702, 'facilities' => ['Vestiare', 'Tribună', 'Iluminat nocturn']],

        // Suceava
        ['name' => 'Sala Sporturilor Dumitru Bernicu', 'county' => 'Suceava', 'city' => 'Suceava', 'address' => 'Strada Universității 15', 'lat' => 47.6438, 'lng' => 26.2568, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună', 'Încălzire']],

        // Buzău
        ['name' => 'Sala Sporturilor Romeo Iamandi', 'county' => 'Buzău', 'city' => 'Buzău', 'address' => 'Bulevardul Stadionului 5', 'lat' => 45.1552, 'lng' => 26.8258, 'facilities' => ['Vestiare', 'Dușuri', 'Tribună', 'Parcare']],
    ];

    /**
     * Seed the shared venues and their amenities. Idempotent: locations are keyed
     * by (county, city, address) and amenities are only ever added, the same rule
     * the club panel follows for shared places.
     *
     * `google_place_id` is left null on purpose — a curated record has no Google
     * identity until somebody actually geocodes it through the panel.
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
