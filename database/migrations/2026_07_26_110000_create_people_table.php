<?php

use App\Enums\PersonProfession;
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
        // Someone an organization puts in front of the public: a coach at a club,
        // a doctor or physiotherapist at a practice. Same card on the same page,
        // so one table with a profession.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('profession')->default(PersonProfession::Coach->value)->index();
            // The free-text job title shown publicly ("Antrenor principal",
            // "Coordonator") — a different thing from the profession above.
            $table->string('role')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('offers_private_sessions')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
