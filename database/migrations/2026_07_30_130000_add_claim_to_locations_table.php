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
        Schema::table('locations', function (Blueprint $table) {
            // Who holds the pen on this place's own fields — name, address, photos,
            // amenities. Null means nobody does, which is how every location
            // starts: they are created as a side effect of a club saying it trains
            // there, and that club has no claim to the record itself.
            //
            // nullOnDelete rather than cascade: the place outlives the company.
            $table->foreignId('claimed_by_organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claimed_by_organization_id');
            $table->dropColumn('claimed_at');
        });
    }
};
