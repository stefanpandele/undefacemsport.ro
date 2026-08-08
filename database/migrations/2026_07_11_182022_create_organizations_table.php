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
        // The account holder: a legal entity with a plan, staff and an approval
        // flow. The same thing whether it runs training programmes, rents out
        // pitches, or treats athletes.
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // No type column, deliberately. What an organization is — club,
            // venue, practice — is read from what it has published, because a
            // stored answer outlives the offer it stood for.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
