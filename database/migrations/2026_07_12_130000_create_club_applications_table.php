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
        Schema::create('club_applications', function (Blueprint $table) {
            $table->id();
            $table->string('club_name');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('fiscal_code');
            $table->string('company_name')->nullable();
            $table->string('contact_role')->nullable();
            $table->boolean('is_vat_payer')->nullable();
            $table->string('address')->nullable();
            $table->string('county')->nullable();
            $table->string('city')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_applications');
    }
};
