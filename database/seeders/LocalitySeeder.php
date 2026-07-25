<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Locality;
use Illuminate\Database\Seeder;

class LocalitySeeder extends Seeder
{
    /**
     * Seed Romanian localities from database/data/localities.json (grouped by
     * county), sourced from Sameday's coverage list. Requires CountySeeder to
     * have run first. Bulk-inserted in chunks; idempotent.
     */
    public function run(): void
    {
        if (Locality::query()->exists()) {
            return;
        }

        /** @var array<string, list<string>> $data */
        $data = json_decode((string) file_get_contents(database_path('data/localities.json')), true);

        $countyIds = County::pluck('id', 'name');

        $rows = [];
        foreach ($data as $countyName => $localities) {
            $countyId = $countyIds[$countyName] ?? null;
            if ($countyId === null) {
                continue;
            }

            foreach ($localities as $name) {
                $rows[] = ['county_id' => $countyId, 'name' => $name];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            Locality::insert($chunk);
        }
    }
}
