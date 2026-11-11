<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealEvidence extends Model
{
    protected $fillable = [
        'tenant_id',
        'deal_id',
        'evidence_type',
        'source',
        'source_record_id',
        'content_hash',
        'observed_at',
        'confidence',
        'payload',
    ];

    protected $casts = [
        'observed_at' => 'datetime',
        'confidence' => 'decimal:2',
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($query) {
            $tenant = request()->attributes->get('tenant');
            if ($tenant) {
                $query->where($query->getModel()->getTable() . '.tenant_id', $tenant->id);
            }
        });
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}