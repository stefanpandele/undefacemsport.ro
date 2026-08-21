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
     * A managed space with no tariff yet — a room somebody has entered but not
     * yet said how you get into. States add the tariff, because a price without
     * a way in is not a thing this model can hold.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'organization_location_id' => OrganizationLocation::factory(),
            'name' => 'Bazin '.fake()->unique()->numberBetween(1, 100000),
            'status' => FacilityStatus::Approved,
            'sort_order' => 0,
        ];
    }

    /**
     * Sold by the entry, hours unstated — the commonest case, a pool or a gym.
     */
    public function openAccess(float $price = 45): static
    {
        return $this->tariff(SpaceAccessMode::OpenAccess, $price, PriceUnit::Entry);
    }

    /**
     * Nobody operates it and nobody charges for it — the hoop in a park.
     */
    public function unmanaged(): static
    {
        return $this
            ->state(fn (): array => ['organization_location_id' => null])
            ->tariff(SpaceAccessMode::OpenAccess, 0);
    }

    /**
     * Booked whole, by the hour.
     */
    public function rental(float $pricePerHour = 120): static
    {
        return $this->tariff(SpaceAccessMode::ExclusiveRental, $pricePerHour, PriceUnit::Hour);
    }

    /**
     * One tariff with no hours: the way in and the price are known, the
     * timetable is not.
     */
    public function tariff(SpaceAccessMode $mode, ?float $price, ?PriceUnit $unit = null): static
    {
        return $this->afterCreating(function (Space $space) use ($mode, $price, $unit): void {
            ScheduleSlot::create([
                'kind' => ScheduleSlotKind::Access,
                'organization_id' => $space->organizationLocation?->organization_id,
                'space_id' => $space->getKey(),
                'access_mode' => $mode,
                'price' => $price,
                'price_unit' => $unit,
            ]);
        });
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
     * Give the space a tariff with real hours, the same interval every day unless
     * told otherwise, because most venues do exactly that.
     *
     * @param  list<Weekday>|null  $days
     */
    public function openDaily(
        string $start = '07:00',
        string $end = '22:00',
        ?array $days = null,
        ?float $price = null,
        SpaceAccessMode $mode = SpaceAccessMode::OpenAccess,
        ?PriceUnit $unit = null,
    ): static {
        return $this->afterCreating(function (Space $space) use ($start, $end, $days, $price, $mode, $unit): void {
            foreach ($days ?? Weekday::cases() as $day) {
                ScheduleSlot::create([
                    'kind' => ScheduleSlotKind::Access,
                    'organization_id' => $space->organizationLocation?->organization_id,
                    'space_id' => $space->getKey(),
                    'access_mode' => $mode,
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'price' => $price,
                    'price_unit' => $unit,
                ]);
            }
        });
    }
}
