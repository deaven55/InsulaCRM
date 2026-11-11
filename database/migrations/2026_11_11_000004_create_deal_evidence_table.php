<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deal_evidence')) {
            return;
        }

        Schema::create('deal_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->string('evidence_type', 50);
            $table->string('source', 100);
            $table->string('source_record_id', 255)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->index(['tenant_id', 'deal_id', 'evidence_type']);
            $table->index(['tenant_id', 'deal_id', 'source']);
            $table->index(['tenant_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_evidence');
    }
};