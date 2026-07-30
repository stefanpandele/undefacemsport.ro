<?php

use App\Enums\FacilityStatus;
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
        // A pitch, a pool, a court, a gym — something you can actually use at a
        // location. First-class rather than an organization's offer, because the
        // basketball hoop in a park exists whether or not anybody monetizes it,
        // and a visitor asking "where do I play" deserves it in the answer.
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            // NULL means unmanaged: nobody operates it, nobody charges for it.
            // nullOnDelete rather than cascade on purpose — when an operator
            // leaves, the space is still there, it just goes back to unmanaged.
            $table->foreignId('organization_location_id')->nullable()
                ->constrained('organization_location')->nullOnDelete();
            $table->string('name');
            // Nullable: a sauna is not a sport.
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            // How you get in, which is the visitor's real question — not who owns
            // the place. A park court and a hotel pool share this value and differ
            // only on price.
            $table->string('access_mode')->index();
            $table->decimal('price', 8, 2)->nullable();
            $table->string('price_unit')->nullable();
            $table->string('price_notes')->nullable();
            // Descriptive only ("6 culoare"), never used to compute availability.
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_indoor')->nullable();
            $table->boolean('has_floodlights')->nullable();
            $table->string('surface')->nullable();
            // A club may propose a space it did not build, the same way it can
            // propose an amenity: it stays out of public pages until reviewed.
            $table->string('status')->default(FacilityStatus::Approved->value)->index();
            // Unmanaged spaces have nobody with an incentive to keep them true, so
            // the review queue is built on this.
            $table->timestamp('last_verified_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['location_id', 'access_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
