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
        // Free-form trust chips a club shows for a sport (e.g. "Licențiat FR
        // Natație", "120+ elevi").
        Schema::create('club_sport_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_sport_id')->constrained('club_sport')->cascadeOnDelete();
            $table->string('icon', 16)->nullable();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_sport_benefits');
    }
};
