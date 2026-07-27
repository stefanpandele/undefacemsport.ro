<?php

use App\Models\Location;
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
        // Public URL key for a location (/locatii/{slug}).
        Schema::table('locations', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        Location::query()->whereNull('slug')->cursor()->each(
            fn (Location $location) => $location->forceFill([
                'slug' => Location::uniqueSlug($location->name, $location->city),
            ])->save(),
        );

        // Every location is addressable, so the public pages can always link
        // to one without guarding against a missing slug.
        Schema::table('locations', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
