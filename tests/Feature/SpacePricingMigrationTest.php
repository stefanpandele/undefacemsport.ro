<?php

use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\Location;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one part of the move that the rest of the suite cannot reach.
 *
 * Every other test runs against a freshly migrated, empty database, so the step
 * that carries a space's price onto its tariffs never executes — and it is the
 * step that touches data somebody already published.
 */

/**
 * Undo the move, so the tables are back in the shape the old code wrote.
 */
function rollBackPricingMove(): void
{
    Artisan::call('migrate:rollback', ['--step' => 1]);
}

/**
 * A space as the previous schema stored it: the way in and the price on the
 * space itself.
 *
 * @param  array<string, mixed>  $attributes
 */
function oldSpace(array $attributes = []): int
{
    return DB::table('spaces')->insertGetId($attributes + [
        'location_id' => Location::factory()->create()->getKey(),
        'organization_location_id' => null,
        'name' => 'Bazin vechi',
        'access_mode' => SpaceAccessMode::OpenAccess->value,
        'price' => 45,
        'price_unit' => PriceUnit::Entry->value,
        'price_notes' => 'Abonament 380 lei / 10 intrări',
        'status' => 'approved',
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * An interval as the previous schema stored it: hours, and nothing else unless
 * it was overriding the space.
 *
 * @param  array<string, mixed>  $attributes
 */
function oldSlot(int $spaceId, array $attributes = []): void
{
    DB::table('schedule_slots')->insert($attributes + [
        'kind' => ScheduleSlotKind::Access->value,
        'space_id' => $spaceId,
        'day_of_week' => Weekday::Monday->value,
        'start_time' => '07:00:00',
        'end_time' => '22:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('an interval that said nothing inherits the price the space used to carry', function () {
    rollBackPricingMove();
    $spaceId = oldSpace();
    oldSlot($spaceId);

    Artisan::call('migrate');

    $tariff = DB::table('schedule_slots')->where('space_id', $spaceId)->first();

    expect($tariff->access_mode)->toBe(SpaceAccessMode::OpenAccess->value)
        ->and((float) $tariff->price)->toBe(45.0)
        ->and($tariff->price_unit)->toBe(PriceUnit::Entry->value)
        ->and($tariff->price_notes)->toBe('Abonament 380 lei / 10 intrări');
});

test('an interval on another way in does not walk off with the base price', function () {
    // The old rule, and the reason the move is not a blind copy: 180 lei an hour
    // must never become the price of an open-gym ticket.
    rollBackPricingMove();
    $spaceId = oldSpace([
        'access_mode' => SpaceAccessMode::ExclusiveRental->value,
        'price' => 180,
        'price_unit' => PriceUnit::Hour->value,
        'price_notes' => null,
    ]);
    oldSlot($spaceId, [
        'day_of_week' => Weekday::Friday->value,
        'access_mode' => SpaceAccessMode::OpenAccess->value,
    ]);

    Artisan::call('migrate');

    $tariff = DB::table('schedule_slots')->where('space_id', $spaceId)->first();

    expect($tariff->access_mode)->toBe(SpaceAccessMode::OpenAccess->value)
        ->and($tariff->price)->toBeNull()
        ->and($tariff->price_unit)->toBeNull();
});

test('a space that never had hours keeps its price, as a tariff without them', function () {
    // The park court an admin put on the map. Dropping it would delete a price
    // somebody published.
    rollBackPricingMove();
    $spaceId = oldSpace(['price' => 0, 'price_unit' => null, 'price_notes' => null]);

    Artisan::call('migrate');

    $tariffs = DB::table('schedule_slots')->where('space_id', $spaceId)->get();

    expect($tariffs)->toHaveCount(1)
        ->and($tariffs->first()->access_mode)->toBe(SpaceAccessMode::OpenAccess->value)
        ->and((float) $tariffs->first()->price)->toBe(0.0)
        ->and($tariffs->first()->day_of_week)->toBeNull()
        ->and($tariffs->first()->start_time)->toBeNull();
});

test('a second attempt after a half-applied one finishes the job', function () {
    // MariaDB commits each statement as it goes, so a failure on the last step
    // leaves the earlier ones applied and the migration unrecorded — the next
    // `migrate` starts from the top and must not trip over its own work.
    rollBackPricingMove();
    $spaceId = oldSpace();
    oldSlot($spaceId);

    $migration = require database_path('migrations/2026_08_21_204632_move_space_pricing_onto_tariffs.php');

    $migration->up();
    $migration->up();

    $tariffs = DB::table('schedule_slots')->where('space_id', $spaceId)->get();

    expect($tariffs)->toHaveCount(1)
        ->and((float) $tariffs->first()->price)->toBe(45.0)
        ->and(Schema::hasColumn('spaces', 'access_mode'))->toBeFalse();
});

test('the foreign key keeps an index once the composite one is gone', function () {
    // MySQL and MariaDB refuse to drop the index a foreign key is leaning on.
    rollBackPricingMove();
    oldSpace();

    Artisan::call('migrate');

    expect(Schema::hasIndex('spaces', 'spaces_location_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('spaces', 'spaces_location_id_access_mode_index'))->toBeFalse();
});

test('the space keeps nothing about how you get in or what it costs', function () {
    rollBackPricingMove();
    oldSpace();

    Artisan::call('migrate');

    foreach (['access_mode', 'price', 'price_unit', 'price_notes'] as $column) {
        expect(Schema::hasColumn('spaces', $column))->toBeFalse("spaces still has {$column}");
    }
});
