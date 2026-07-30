<?php

namespace Database\Factories;

use App\Enums\FacilityStatus;
use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\Location;
use App\Models\OrganizationLocation;
use App\Models\ScheduleSlot;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * A managed, open-access space by default — the commonest case, a pool or a
     * gym somebody sells entry to.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'organization_location_id' => OrganizationLocation::factory(),
            'name' => 'Bazin '.fake()->unique()->numberBetween(1, 100000),
            'access_mode' => SpaceAccessMode::OpenAccess,
            'price' => 45,
            'price_unit' => PriceUnit::Entry,
            'status' => FacilityStatus::Approved,
            'sort_order' => 0,
        ];
    }

    /**
     * Nobody operates it and nobody charges for it — the hoop in a park.
     */
    public function unmanaged(): static
    {
        return $this->state(fn (): array => [
            'organization_location_id' => null,
            'price' => 0,
            'price_unit' => null,
        ]);
    }

    /**
     * Booked whole, by the hour.
     */
    public function rental(float $pricePerHour = 120): static
    {
        return $this->state(fn (): array => [
            'access_mode' => SpaceAccessMode::ExclusiveRental,
            'price' => $pricePerHour,
            'price_unit' => PriceUnit::Hour,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => FacilityStatus::Pending]);
    }

    public function verifiedAt(?string $when): static
    {
        return $this->state(fn (): array => ['last_verified_at' => $when]);
    }

    /**
     * Give the space opening hours. Same interval every day unless told otherwise,
     * because most venues do exactly that.
     *
     * @param  list<Weekday>|null  $days
     */
    public function openDaily(string $start = '07:00', string $end = '22:00', ?array $days = null, ?float $price = null): static
    {
        return $this->afterCreating(function (Space $space) use ($start, $end, $days, $price): void {
            foreach ($days ?? Weekday::cases() as $day) {
                ScheduleSlot::create([
                    'kind' => ScheduleSlotKind::Access,
                    'organization_id' => $space->organizationLocation?->organization_id,
                    'space_id' => $space->getKey(),
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'price' => $price,
                ]);
            }
        });
    }
}
