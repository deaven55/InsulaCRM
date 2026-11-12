<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComparableSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'property_id', 'external_id', 'external_source', 'source_record_id', 'source_url',
        'source_observed_at', 'confidence', 'address', 'property_type', 'city', 'state', 'zip_code',
        'latitude', 'longitude', 'sale_price', 'sale_date', 'sqft', 'beds', 'baths', 'lot_size',
        'year_built', 'distance_miles', 'similarity_score', 'condition', 'adjustments', 'adjusted_price', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'sale_date' => 'date',
            'source_observed_at' => 'datetime',
            'confidence' => 'decimal:2',
            'adjustments' => 'array',
            'adjusted_price' => 'decimal:2',
            'baths' => 'decimal:1',
            'lot_size' => 'decimal:2',
            'distance_miles' => 'decimal:2',
            'similarity_score' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function calculateAdjustedPrice(): float
    {
        $adjustments = $this->adjustments ?? [];
        $adjustmentTotal = array_sum(array_values($adjustments));

        return (float) $this->sale_price + $adjustmentTotal;
    }
}
