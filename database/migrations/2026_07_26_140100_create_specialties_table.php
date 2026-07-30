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
        // What a practice does: physiotherapy, sports medicine, nutrition.
        //
        // Kept apart from `sports` on purpose. Physiotherapy is not a sport, and
        // `sports` already carries product logic — Sport::withReach(), the popular
        // sports on the homepage, the explore filters, age groups, levels. Putting
        // medical specialties in there would pollute every one of those queries.
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        });

        // The sports a specialty is commonly sought for — "recuperare sportivă"
        // next to football and athletics. Optional, and only ever used to relate
        // the two worlds on a page, never to count one as the other.
        Schema::create('specialty_sport', function (Blueprint $table) {
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->primary(['specialty_id', 'sport_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialty_sport');
        Schema::dropIfExists('specialties');
    }
};
