<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_analyses', function (Blueprint $table) {
            $table->decimal('offer_low', 12, 2)->nullable()->after('arv');
            $table->decimal('offer_target', 12, 2)->nullable()->after('offer_low');
            $table->decimal('offer_high', 12, 2)->nullable()->after('offer_target');
            $table->string('decision', 30)->nullable()->after('offer_high');
            $table->decimal('decision_score', 5, 2)->nullable()->after('decision');
            $table->json('decision_factors')->nullable()->after('decision_score');
            $table->json('offer_assumptions')->nullable()->after('decision_factors');
        });
    }

    public function down(): void
    {
        Schema::table('deal_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'offer_low','offer_target','offer_high','decision','decision_score',
                'decision_factors','offer_assumptions',
            ]);
        });
    }
};
