<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['leads', 'properties', 'deals', 'buyers'] as $table) {
            if (! Schema::hasColumn($table, 'external_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('external_id', 255)->nullable()->index();
                    $blueprint->string('external_source', 100)->nullable()->index();
                });
            }
        }

        if (! Schema::hasTable('integration_idempotency_keys')) {
            Schema::create('integration_idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('source', 100);
                $table->string('idempotency_key', 255);
                $table->string('event_type', 150)->nullable();
                $table->string('resource_type', 100)->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->json('response_body')->nullable();
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'source', 'idempotency_key'], 'integration_idempotency_unique');
                $table->index(['tenant_id', 'source', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('integration_idempotency_keys')) {
            Schema::drop('integration_idempotency_keys');
        }

        foreach (['leads', 'properties', 'deals', 'buyers'] as $table) {
            if (Schema::hasColumn($table, 'external_source')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('external_source');
                });
            }
            if (Schema::hasColumn($table, 'external_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('external_id');
                });
            }
        }
    }
};
