<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationIdempotencyKey extends Model
{
    protected $table = 'integration_idempotency_keys';

    protected $fillable = [
        'tenant_id',
        'source',
        'idempotency_key',
        'event_type',
        'resource_type',
        'resource_id',
        'response_body',
        'response_status',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
        ];
    }
}
