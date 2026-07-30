<?php

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
        Schema::table('locations', function (Blueprint $table) {
            // Google's identity for the place — the strongest dedup signal we can
            // get, because two clubs picking the same building get the same value
            // whatever they typed. Deliberately NOT unique: Google can hold two
            // entries for one place (the POI and its street address) and place ids
            // do change over time, so this is a signal, never a constraint.
            $table->string('google_place_id')->nullable()->index();

            // Bounding-box prefilter for the proximity fallback.
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['google_place_id']);
            $table->dropColumn('google_place_id');
        });
    }
};
