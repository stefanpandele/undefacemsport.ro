<?php

use App\Enums\OrganizationType;
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
            // The primary identity: it decides the public URL and the shape of
            // the page, never what may be published. Offers are additive, so a
            // club that owns its hall can also rent it out without needing a
            // second account.
            $table->string('type')->default(OrganizationType::Club->value)->index();
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
