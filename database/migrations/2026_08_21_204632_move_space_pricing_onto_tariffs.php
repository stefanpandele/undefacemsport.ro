<?php

use App\Enums\ScheduleSlotKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves how-you-get-in and what-it-costs off the space and onto its intervals,
 * leaving one place where both are written.
 *
 * The space used to carry a mode and a base price, and an interval could
 * override either — so the same fact lived in two places and the reader had to
 * know which one won. An interval is now a tariff: a way in, a price, and the
 * hours it applies to. The hours are nullable because a price can be known when
 * a timetable is not — the park hoop an admin puts on the map.
 */
return new class extends Migration
{
    /**
     * Written to survive being run twice.
     *
     * MariaDB commits each statement as it goes, so a migration that fails on its
     * last step leaves the earlier ones applied and its own row unwritten — the
     * next attempt starts from the top. Every step below therefore checks the
     * shape it is about to create.
     */
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            // A tariff can be known without its hours: "free, whenever it is
            // light" is a real answer, and better than a week of "închis".
            $table->unsignedTinyInteger('day_of_week')->nullable()->change();
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
        });

        if (! Schema::hasColumn('schedule_slots', 'price_notes')) {
            Schema::table('schedule_slots', function (Blueprint $table) {
                // Followed the price here: "Abonament 380 lei / 10 intrări"
                // belongs to a tariff, never to a physical room.
                $table->string('price_notes')->nullable()->after('price_unit');
            });
        }

        if (Schema::hasColumn('spaces', 'access_mode')) {
            $this->carryPricingOntoTariffs();
        }

        // MySQL and MariaDB keep a foreign key's backing index alive: the composite
        // index leads with `location_id`, so it is what the constraint has been
        // using, and dropping it is refused until another index covers the column.
        if (! Schema::hasIndex('spaces', 'spaces_location_id_index')) {
            Schema::table('spaces', function (Blueprint $table) {
                $table->index('location_id');
            });
        }

        Schema::table('spaces', function (Blueprint $table) {
            foreach (['spaces_location_id_access_mode_index', 'spaces_access_mode_index'] as $index) {
                if (Schema::hasIndex('spaces', $index)) {
                    $table->dropIndex($index);
                }
            }

            $columns = array_values(array_filter(
                ['access_mode', 'price', 'price_unit', 'price_notes'],
                fn (string $column): bool => Schema::hasColumn('spaces', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * Give every space a tariff that says what its columns used to say.
     *
     * An interval that already stated its own way in keeps it; one that did not
     * inherits the space's, which is exactly what the application computed at
     * read time until now. A space with no intervals becomes a single tariff
     * with no hours, so nothing that was published disappears.
     */
    private function carryPricingOntoTariffs(): void
    {
        DB::table('spaces')->orderBy('id')->each(function (object $space): void {
            $slots = DB::table('schedule_slots')
                ->where('kind', ScheduleSlotKind::Access->value)
                ->where('space_id', $space->id)
                ->get();

            if ($slots->isEmpty()) {
                DB::table('schedule_slots')->insert([
                    'kind' => ScheduleSlotKind::Access->value,
                    'organization_id' => DB::table('organization_location')
                        ->where('id', $space->organization_location_id)
                        ->value('organization_id'),
                    'space_id' => $space->id,
                    'access_mode' => $space->access_mode,
                    'price' => $space->price,
                    'price_unit' => $space->price_unit,
                    'price_notes' => $space->price_notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return;
            }

            foreach ($slots as $slot) {
                $mode = $slot->access_mode ?? $space->access_mode;
                // The old rule, kept: a price was only inherited when the way in
                // matched, so an open-gym evening never borrowed the hourly rate.
                $inherits = $mode === $space->access_mode;

                DB::table('schedule_slots')->where('id', $slot->id)->update([
                    'access_mode' => $mode,
                    'price' => $slot->price ?? ($inherits ? $space->price : null),
                    'price_unit' => $slot->price_unit ?? ($inherits ? $space->price_unit : null),
                    'price_notes' => $inherits ? $space->price_notes : null,
                ]);
            }
        });
    }

    /**
     * Puts the columns back, but not what they held: the prices now live on the
     * tariffs, and picking one of several to call "the base price" would be a
     * guess. The hours stay nullable for the same reason — re-tightening them
     * would fail on the very rows this migration created.
     */
    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->string('access_mode')->default('open_access')->index();
            $table->decimal('price', 8, 2)->nullable();
            $table->string('price_unit')->nullable();
            $table->string('price_notes')->nullable();

            $table->index(['location_id', 'access_mode']);
        });

        // Only once the composite index is back can this one go: it is what has
        // been holding up the foreign key in the meantime.
        if (Schema::hasIndex('spaces', 'spaces_location_id_index')) {
            Schema::table('spaces', function (Blueprint $table) {
                $table->dropIndex('spaces_location_id_index');
            });
        }

        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropColumn('price_notes');
        });
    }
};
