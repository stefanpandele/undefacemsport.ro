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
        // Controlled vocabulary of how far along a training group is, reused by
        // the weekly schedule slots.
        //
        // A separate dimension from the age group, not a finer one: a child and
        // an adult can both be learning to swim, and both can be training for
        // competitions. Folding the two together would mean inventing
        // "8–14 ani inițiere" as a group distinct from "8–14 ani performanță",
        // duplicating every age for every level.
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
