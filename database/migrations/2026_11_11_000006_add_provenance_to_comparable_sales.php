<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparable_sales', function (Blueprint $table) {
            $table->string('external_id', 255)->nullable()->after('property_id');
            $table->string('external_source', 100)->nullable()->after('external_id');
            $table->string('source_record_id', 255)->nullable()->after('external_source');
            $table->string('source_url', 2048)->nullable()->after('source_record_id');
            $table->timestamp('source_observed_at')->nullable()->after('source_url');
            $table->decimal('confidence', 5, 2)->nullable()->after('source_observed_at');
            $table->string('property_type', 100)->nullable()->after('address');
            $table->string('city', 100)->nullable()->after('property_type');
            $table->string('state', 2)->nullable()->after('city');
            $table->string('zip_code', 10)->nullable()->after('state');
            $table->decimal('latitude', 10, 7)->nullable()->after('zip_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('similarity_score', 6, 2)->nullable()->after('distance_miles');
            $table->index(['tenant_id', 'property_id', 'external_source', 'external_id'], 'comps_tenant_property_source_ext_idx');
            $table->index(['tenant_id', 'property_id', 'sale_date'], 'comps_tenant_property_sale_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comparable_sales', function (Blueprint $table) {
            $table->dropIndex('comps_tenant_property_source_ext_idx');
            $table->dropIndex('comps_tenant_property_sale_date_idx');
            $table->dropColumn([
                'external_id', 'external_source', 'source_record_id', 'source_url',
                'source_observed_at', 'confidence', 'property_type', 'city', 'state',
                'zip_code', 'latitude', 'longitude', 'similarity_score',
            ]);
        });
    }
};
