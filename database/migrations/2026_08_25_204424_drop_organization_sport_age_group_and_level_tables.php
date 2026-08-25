<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who a club teaches and how far along they are was declared once per club
     * and sport, while the timetable underneath said something else: a club with
     * a children's pool and an adults' pool showed all its groups at both
     * addresses. Both facts live on the schedule slot, which already carries
     * `age_group_id` and `level_id` per hour, so the declared lists are dropped
     * rather than kept in sync with something that contradicts them.
     */
    public function up(): void
    {
        Schema::dropIfExists('organization_sport_age_group');
        Schema::dropIfExists('organization_sport_level');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('organization_sport_age_group', function (Blueprint $table) {
            $table->foreignId('organization_sport_id')->constrained('organization_sport')->cascadeOnDelete();
            $table->foreignId('age_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['organization_sport_id', 'age_group_id']);
        });

        Schema::create('organization_sport_level', function (Blueprint $table) {
            $table->foreignId('organization_sport_id')->constrained('organization_sport')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->primary(['organization_sport_id', 'level_id']);
        });
    }
};
