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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('slug');
            $table->string('fiscal_code')->nullable()->unique()->after('company_name');
            $table->boolean('is_vat_payer')->nullable()->after('fiscal_code');
            $table->string('address')->nullable()->after('is_vat_payer');
            $table->string('county')->nullable()->after('address');
            $table->string('city')->nullable()->after('county');
            $table->string('logo_path')->nullable()->after('city');
            $table->string('cover_path')->nullable()->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['fiscal_code']);
            $table->dropColumn([
                'company_name',
                'fiscal_code',
                'is_vat_payer',
                'address',
                'county',
                'city',
                'logo_path',
                'cover_path',
            ]);
        });
    }
};
