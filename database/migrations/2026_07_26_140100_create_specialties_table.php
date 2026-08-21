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
            // Whether somebody with an injury would search for this. Nearly all of
            // them qualify — sports massage is the wellness extra that does not,
            // and listing it under recovery would answer a question nobody asked.
            $table->boolean('is_medical')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
