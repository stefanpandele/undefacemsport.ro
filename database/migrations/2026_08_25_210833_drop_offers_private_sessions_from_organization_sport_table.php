<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A club ticked "runs 1:1 sessions" for a sport while its people each
     * carried the same flag, so the page could claim one-to-one training with
     * nobody on the list who gives it. The people are the ones a visitor books,
     * so the answer is read from them — see `Organization::offersPrivateSessionsFor()`.
     */
    public function up(): void
    {
        Schema::table('organization_sport', function (Blueprint $table) {
            $table->dropColumn('offers_private_sessions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_sport', function (Blueprint $table) {
            $table->boolean('offers_private_sessions')->default(false)->after('sport_id');
        });
    }
};
