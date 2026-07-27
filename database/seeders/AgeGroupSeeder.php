<?php

namespace Database\Seeders;

use App\Models\AgeGroup;
use Illuminate\Database\Seeder;

class AgeGroupSeeder extends Seeder
{
    /**
     * The audience/age groups clubs assign to their sports and schedule slots.
     *
     * @var list<string>
     */
    private const GROUPS = [
        '0–2 ani',
        '3–7 ani',
        '8–14 ani',
        '15–17 ani',
        'Adulți',
        'Seniori',
        'Persoane cu dizabilități',
    ];

    public function run(): void
    {
        foreach (self::GROUPS as $order => $name) {
            AgeGroup::updateOrCreate(['name' => $name], ['sort_order' => $order]);
        }
    }
}
