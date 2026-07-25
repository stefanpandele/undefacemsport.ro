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
        // The sports a club teaches at one of its locations. Id-backed so a
        // schedule can later hang off each club-location-sport row.
        Schema::create('club_location_sport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_location_id')->constrained('club_location')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['club_location_id', 'sport_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_location_sport');
    }
};
