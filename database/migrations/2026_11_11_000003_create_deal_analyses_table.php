<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deal_analyses')) {
            return;
        }

        Schema::create('deal_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->decimal('arv', 14, 2)->nullable();
            $table->decimal('repair_estimate', 14, 2)->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->decimal('assignment_fee_target', 14, 2)->nullable();
            $table->decimal('mao', 14, 2)->nullable();
            $table->decimal('projected_assignment_fee', 14, 2)->nullable();
            $table->decimal('estimated_equity', 14, 2)->nullable();
            $table->decimal('arv_confidence', 5, 2)->nullable();
            $table->decimal('repair_confidence', 5, 2)->nullable();
            $table->decimal('overall_confidence', 5, 2)->nullable();
            $table->unsignedSmallInteger('deal_score')->nullable();
            $table->string('exit_strategy', 50)->nullable();
            $table->string('methodology', 100)->nullable();
            $table->string('source', 100)->nullable();
            $table->json('comp_summary')->nullable();
            $table->json('repair_scope')->nullable();
            $table->json('assumptions')->nullable();
            $table->json('risk_flags')->nullable();
            $table->json('calculation_snapshot')->nullable();
            $table->boolean('arv_human_override')->default(false);
            $table->boolean('repair_human_override')->default(false);
            $table->boolean('mao_human_override')->default(false);
            $table->string('human_override_reason', 1000)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->string('analyzed_by', 100)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'deal_id']);
            $table->index(['tenant_id', 'overall_confidence']);
            $table->index(['tenant_id', 'deal_score']);
            $table->index(['tenant_id', 'exit_strategy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_analyses');
    }
};