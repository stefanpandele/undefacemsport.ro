<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a space is played on, as a vocabulary rather than a sentence.
 *
 * It was a free-text column, which could describe a court but never find one:
 * "gazon sintetic", "sintetic" and "Gazon Sintetic" are three answers to one
 * question, and none of them can back a filter. Clay or hard is the first thing
 * a tennis player asks, so it has to be answerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surfaces', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
        });

        // Which surfaces a sport is plausibly played on. This is what makes the
        // question disappear where it has no answer: a pool is linked to none, so
        // the field never appears for swimming.
        Schema::create('sport_surface', function (Blueprint $table) {
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->foreignId('surface_id')->constrained()->cascadeOnDelete();

            $table->primary(['sport_id', 'surface_id']);
        });

        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn('surface');
            // nullOnDelete: tidying the vocabulary must not take the court with
            // it. A space with no surface is one nobody has answered for.
            $table->foreignId('surface_id')->nullable()->after('sport_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('surface_id');
            $table->string('surface')->nullable();
        });

        Schema::dropIfExists('sport_surface');
        Schema::dropIfExists('surfaces');
    }
};
