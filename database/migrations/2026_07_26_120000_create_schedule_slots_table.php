<?php

use App\Enums\ScheduleSlotKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One weekly interval, of one of two kinds: a club's training session, or
        // a space's own opening hours. Same shape — weekday plus a time range —
        // so "what is happening near me right now" stays a single query.
        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->default(ScheduleSlotKind::Training->value)->index();
            // Denormalized tenant key so the panel can scope directly. Nullable
            // because an unmanaged space's opening hours belong to no organization.
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            // Set on a training slot. Null on a space's own hours.
            $table->foreignId('organization_location_sport_id')->nullable()
                ->constrained('organization_location_sport')->cascadeOnDelete();
            // Two legitimate roles: on an `access` slot it says whose hours these
            // are; on a `training` slot it optionally says which pool the club
            // trains in, which is what lets the location's day view draw one row
            // per space.
            $table->foreignId('space_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Monday ... 7 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            // Overrides the space's base price for this interval — pools charge
            // less in the morning, more at the weekend.
            $table->decimal('price', 8, 2)->nullable();
            $table->foreignId('age_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_slots');
    }
};
