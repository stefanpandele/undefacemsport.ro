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
        // What a practice sells: a consultation, a session, an assessment.
        //
        // The third kind of offer, beside a club's programme and a space's access.
        // Priced per appointment and bounded by a duration, which is what makes it
        // neither of the other two.
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            // Where it is offered. Null means everywhere the organization is — a
            // single-branch practice never has to think about it, while a clinic
            // with two branches can say a service is only at one of them.
            $table->foreignId('organization_location_id')->nullable()
                ->constrained('organization_location')->nullOnDelete();
            // Who provides it, when the practice wants to say. A clinic with six
            // physiotherapists may leave it open.
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->string('price_notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The sports this service is for, ticked by whoever offers it: "I do
        // recovery for football and basketball."
        //
        // On the service rather than on the specialty, because it is a claim about
        // one practitioner's work and not a fact about the field — a global link
        // would say every physiotherapist treats footballers. And on the service
        // rather than the organization, because a clinic's recovery may be for
        // contact sports while its nutrition is for everyone.
        Schema::create('service_sport', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_id', 'sport_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_sport');
        Schema::dropIfExists('services');
    }
};
