<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('properties', 'owner_name')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->string('owner_name', 255)->nullable()->after('lead_id');
                $table->string('mailing_address', 255)->nullable()->after('owner_name');
                $table->string('parcel_id', 100)->nullable()->index()->after('zip_code');
                $table->decimal('assessed_value', 15, 2)->nullable()->after('estimated_value');
                $table->decimal('land_value', 15, 2)->nullable()->after('assessed_value');
                $table->decimal('building_value', 15, 2)->nullable()->after('land_value');
                $table->decimal('acreage', 12, 4)->nullable()->after('lot_size');
                $table->decimal('tax_amount', 15, 2)->nullable()->after('sold_price');
                $table->string('tax_status', 100)->nullable()->after('tax_amount');
                $table->decimal('delinquent_tax_amount', 15, 2)->nullable()->after('tax_status');
                $table->string('zoning', 100)->nullable()->after('delinquent_tax_amount');
                $table->text('legal_description')->nullable()->after('zoning');
                $table->date('last_verified_sale_date')->nullable()->after('legal_description');
                $table->decimal('last_verified_sale_price', 15, 2)->nullable()->after('last_verified_sale_date');
                $table->json('permit_records')->nullable()->after('last_verified_sale_price');
                $table->json('intelligence_provenance')->nullable()->after('permit_records');
                $table->timestamp('intelligence_last_reconciled_at')->nullable()->after('intelligence_provenance');
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'owner_name','mailing_address','parcel_id','assessed_value','land_value','building_value',
            'acreage','tax_amount','tax_status','delinquent_tax_amount','zoning','legal_description',
            'last_verified_sale_date','last_verified_sale_price','permit_records',
            'intelligence_provenance','intelligence_last_reconciled_at'
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('properties', $column)) {
                Schema::table('properties', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
