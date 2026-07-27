<?php

use App\Enums\FacilityStatus;
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
        // Clubs may propose amenities, but the vocabulary is shared and shown
        // publicly, so a suggestion stays invisible until an admin approves it.
        // Whatever is already here is curated reference data, hence approved.
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('status')->default(FacilityStatus::Approved->value)->after('icon');
            $table->foreignId('suggested_by_club_id')->nullable()->after('status')
                ->constrained('clubs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_by_club_id');
            $table->dropColumn('status');
        });
    }
};
