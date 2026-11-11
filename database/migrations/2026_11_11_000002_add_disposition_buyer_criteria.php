<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            if (!Schema::hasColumn('buyers', 'min_purchase_price')) {
                $table->decimal('min_purchase_price', 15, 2)->nullable()->after('max_purchase_price');
            }
            if (!Schema::hasColumn('buyers', 'preferred_strategies')) {
                $table->json('preferred_strategies')->nullable()->after('preferred_property_types');
            }
            if (!Schema::hasColumn('buyers', 'preferred_conditions')) {
                $table->json('preferred_conditions')->nullable()->after('preferred_strategies');
            }
            if (!Schema::hasColumn('buyers', 'monthly_capacity')) {
                $table->unsignedInteger('monthly_capacity')->nullable()->after('preferred_conditions');
            }
            if (!Schema::hasColumn('buyers', 'buyer_tier')) {
                $table->string('buyer_tier', 20)->nullable()->index()->after('buyer_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            foreach (['min_purchase_price','preferred_strategies','preferred_conditions','monthly_capacity','buyer_tier'] as $column) {
                if (Schema::hasColumn('buyers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
