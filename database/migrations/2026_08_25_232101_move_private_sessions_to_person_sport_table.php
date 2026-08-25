<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-to-one training was a flag on the person, so a coach who takes clients
     * alone for swimming claimed it for every other sport on their card too —
     * and the chip appeared on a basketball tab nobody had said it about.
     *
     * It belongs one grain finer, on the sport a person is asked about. Not on
     * the sport itself: deciding centrally that basketball does not do one-to-one
     * would silence a club that coaches shooting individually, and a claim about
     * this person's work is not a fact about the field.
     */
    public function up(): void
    {
        Schema::table('person_sport', function (Blueprint $table) {
            $table->boolean('offers_private_sessions')->default(false);
        });

        // Everyone who claimed it keeps it, for every sport they teach. Narrowing
        // it is the club's call, and taking the claim away silently would be us
        // deciding they never meant it.
        DB::table('person_sport')
            ->whereIn('person_id', DB::table('people')->where('offers_private_sessions', true)->select('id'))
            ->update(['offers_private_sessions' => true]);

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('offers_private_sessions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->boolean('offers_private_sessions')->default(false);
        });

        DB::table('people')
            ->whereIn('id', DB::table('person_sport')->where('offers_private_sessions', true)->select('person_id'))
            ->update(['offers_private_sessions' => true]);

        Schema::table('person_sport', function (Blueprint $table) {
            $table->dropColumn('offers_private_sessions');
        });
    }
};
