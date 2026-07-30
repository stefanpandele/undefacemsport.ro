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
        // The sports an organization offers at one of its locations. Id-backed so a
        // schedule can later hang off each organization-location-sport row.
        Schema::create('organization_location_sport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_location_id')->constrained('organization_location')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Named, because the generated name is 68 characters and MySQL stops at
            // 64. SQLite does not, so the suite cannot catch this — SchemaTest
            // measures every index name for that reason.
            $table->unique(['organization_location_id', 'sport_id'], 'organization_location_sport_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_location_sport');
    }
};
