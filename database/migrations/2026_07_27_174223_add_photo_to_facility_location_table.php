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
        // Proof that the amenity really exists at this place. A club proposing
        // a new one must photograph it, and an admin looks at that photo before
        // approving — otherwise a club could pad its location with amenities it
        // does not have. Nullable: the seeded vocabulary predates the rule.
        Schema::table('facility_location', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_location', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
