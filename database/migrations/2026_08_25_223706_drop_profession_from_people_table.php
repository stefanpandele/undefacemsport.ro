<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A person carried both a free-text `role` — the job title the public page
     * actually prints — and a structured `profession` nothing ever asked for.
     * The column defaulted to "coach", so every receptionist a club entered was
     * filed as a coach: the exact false claim it was added to prevent.
     *
     * Its only live reader was a label suffix in one panel select, and the line
     * that matters — what counts as a medical extra — is drawn on
     * `specialties.is_medical`, not on who sells the service. What a person is
     * now lives in `role`, in their own words.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['profession']);
            $table->dropColumn('profession');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('profession')->default('coach')->index()->after('name');
        });
    }
};
