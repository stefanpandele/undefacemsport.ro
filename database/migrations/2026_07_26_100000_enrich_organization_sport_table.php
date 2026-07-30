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
        Schema::table('organization_sport', function (Blueprint $table) {
            // The single cover is replaced by a gallery (polymorphic images);
            // the per-sport description isn't shown on the club page.
            $table->dropColumn(['cover_path', 'description']);
            $table->boolean('offers_private_sessions')->default(false)->after('sport_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_sport', function (Blueprint $table) {
            $table->dropColumn('offers_private_sessions');
            $table->string('cover_path')->nullable();
            $table->text('description')->nullable();
        });
    }
};
