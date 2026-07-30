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
        // Where a slug used to point. Merging two duplicate locations retires one
        // of them, and every indexed URL and link shared on WhatsApp has to keep
        // working — so the old slug lives on here and redirects.
        Schema::create('location_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_redirects');
    }
};
