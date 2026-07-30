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
        // An organization asking to become the authoritative editor of a shared
        // location it operates.
        //
        // Reviewed by hand rather than verified automatically: at this volume a
        // human reading the claim is both cheaper and safer than matching a CUI
        // against an address nobody has verified either.
        Schema::create('location_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->text('evidence')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One open claim per organization per place: asking twice is not more
            // evidence.
            $table->unique(['location_id', 'organization_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_claims');
    }
};
