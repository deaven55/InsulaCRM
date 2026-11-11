<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'deal_id', 'arv', 'repair_estimate', 'purchase_price',
        'assignment_fee_target', 'mao', 'projected_assignment_fee', 'estimated_equity',
        'arv_confidence', 'repair_confidence', 'overall_confidence', 'deal_score',
        'exit_strategy', 'methodology', 'source', 'comp_summary', 'repair_scope',
        'assumptions', 'risk_flags', 'calculation_snapshot',
        'arv_human_override', 'repair_human_override', 'mao_human_override',
        'offer_low', 'offer_target', 'offer_high', 'decision', 'decision_score', 'decision_factors', 'offer_assumptions',
        'human_override_reason', 'analyzed_at', 'analyzed_by',
    ];

    protected function casts(): array
    {
        return [
            'arv' => 'decimal:2',
            'repair_estimate' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'assignment_fee_target' => 'decimal:2',
            'mao' => 'decimal:2',
            'projected_assignment_fee' => 'decimal:2',
            'estimated_equity' => 'decimal:2',
            'arv_confidence' => 'decimal:2',
            'repair_confidence' => 'decimal:2',
            'overall_confidence' => 'decimal:2',
            'comp_summary' => 'array',
            'repair_scope' => 'array',
            'assumptions' => 'array',
            'risk_flags' => 'array',
            'calculation_snapshot' => 'array',
            'arv_human_override' => 'boolean',
            'repair_human_override' => 'boolean',
            'mao_human_override' => 'boolean',
            'analyzed_at' => 'datetime',
            'offer_low' => 'decimal:2',
            'offer_target' => 'decimal:2',
            'offer_high' => 'decimal:2',
            'decision_score' => 'decimal:2',
            'decision_factors' => 'array',
            'offer_assumptions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}