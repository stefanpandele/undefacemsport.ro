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
        // The audience/age groups a club serves for a given sport.
        Schema::create('organization_sport_age_group', function (Blueprint $table) {
            $table->foreignId('organization_sport_id')->constrained('organization_sport')->cascadeOnDelete();
            $table->foreignId('age_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['organization_sport_id', 'age_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_sport_age_group');
    }
};
