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
        // The levels a club teaches for a given sport. On the sport rather than
        // the organization, because one club commonly runs beginners' groups for
        // children and competition training for juniors at the same time.
        Schema::create('organization_sport_level', function (Blueprint $table) {
            $table->foreignId('organization_sport_id')->constrained('organization_sport')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->primary(['organization_sport_id', 'level_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_sport_level');
    }
};
