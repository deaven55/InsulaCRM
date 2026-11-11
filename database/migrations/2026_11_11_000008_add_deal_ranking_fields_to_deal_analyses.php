<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_analyses', function (Blueprint $table) {
            $table->decimal('ranking_score', 5, 2)->nullable()->after('decision_score');
            $table->string('ranking_grade', 5)->nullable()->after('ranking_score');
            $table->json('ranking_factors')->nullable()->after('ranking_grade');
            $table->json('ranking_risk_flags')->nullable()->after('ranking_factors');
            $table->timestamp('ranked_at')->nullable()->after('ranking_risk_flags');
        });
    }

    public function down(): void
    {
        Schema::table('deal_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'ranking_score','ranking_grade','ranking_factors',
                'ranking_risk_flags','ranked_at',
            ]);
        });
    }
};
