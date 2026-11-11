<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyIntelligenceApiController extends Controller
{
    private const FIELD_PRIORITY = [
        'land_record' => 100,
        'property_record' => 95,
        'tax_record' => 90,
        'tax_delinquency' => 90,
        'permit_record' => 85,
        'county_dataset' => 80,
        'assessment_record' => 80,
        'public_record' => 75,
        'user_supplied' => 50,
    ];

    private const CANONICAL_FIELDS = [
        'owner_name','mailing_address','property_address','city','state','zip_code','parcel_id',
        'property_type','bedrooms','bathrooms','building_sqft','square_footage','year_built',
        'lot_size','acreage','assessed_value','land_value','building_value','tax_amount','tax_status',
        'delinquent_tax_amount','zoning','legal_description','sale_date','sale_price','tax_status',
        'permit_records'
    ];

    public function reconcile(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');
        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('lead.property')
            ->findOrFail($dealId);

        if (!$deal->lead || !$deal->lead->property) {
            return response()->json([
                'success' => false,
                'error' => 'Deal has no canonical property linked through its lead.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'evidence' => 'required|array|min:1',
            'evidence.*.source' => 'nullable|string|max:150',
            'evidence.*.evidence_type' => 'nullable|string|max:100',
            'evidence.*.confidence' => 'nullable|numeric|min:0|max:1',
            'evidence.*.observed_at' => 'nullable|date',
            'evidence.*.payload' => 'required|array',
            'evidence.*.payload.structured_facts' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed.', 'details' => $validator->errors()], 422);
        }

        $property = $deal->lead->property;
        $candidates = [];

        foreach ($request->input('evidence', []) as $evidence) {
            $facts = $evidence['payload']['structured_facts'] ?? [];
            $source = $evidence['source'] ?? 'unknown';
            $type = $evidence['evidence_type'] ?? 'public_record';
            $priority = self::FIELD_PRIORITY[$type] ?? 40;
            $confidence = isset($evidence['confidence']) ? (float) $evidence['confidence'] : 0.5;
            $observedAt = $evidence['observed_at'] ?? now()->toISOString();

            foreach ($facts as $field => $value) {
                if (!in_array($field, self::CANONICAL_FIELDS, true) || $value === null || $value === '' || $value === []) {
                    continue;
                }
                $candidates[$field][] = [
                    'value' => $value,
                    'source' => $source,
                    'evidence_type' => $type,
                    'priority' => $priority,
                    'confidence' => $confidence,
                    'observed_at' => $observedAt,
                ];
            }
        }

        $selected = [];
        $provenance = [];

        foreach ($candidates as $field => $values) {
            usort($values, function ($a, $b) {
                return [$b['priority'], $b['confidence'], $b['observed_at']]
                    <=> [$a['priority'], $a['confidence'], $a['observed_at']];
            });

            $winner = $values[0];
            $selected[$field] = $winner['value'];
            $provenance[$field] = [
                'selected' => $winner,
                'alternatives' => array_slice($values, 1, 4),
            ];
        }

        $mapping = [
            'property_address' => 'address',
            'building_sqft' => 'square_footage',
            'sale_date' => 'last_verified_sale_date',
            'sale_price' => 'last_verified_sale_price',
        ];

        $update = [];
        foreach ($selected as $field => $value) {
            $column = $mapping[$field] ?? $field;
            if ($column === 'permit_records' && !is_array($value)) {
                $value = [$value];
            }
            $update[$column] = $value;
        }

        // Never overwrite a populated canonical value with a lower-confidence reconciliation.
        foreach ($update as $column => $value) {
            if ($property->{$column} !== null && $property->{$column} !== '' && !array_key_exists($column, $provenance)) {
                unset($update[$column]);
            }
        }

        $update['intelligence_provenance'] = array_merge(
            is_array($property->intelligence_provenance) ? $property->intelligence_provenance : [],
            $provenance
        );
        $update['intelligence_last_reconciled_at'] = now();

        $property->update($update);
        AuditLog::log('property.intelligence_reconciled', $property);

        return response()->json([
            'success' => true,
            'deal_id' => $deal->id,
            'property_id' => $property->id,
            'updated_fields' => array_keys($update),
            'property' => $property->fresh(),
            'provenance' => $provenance,
        ]);
    }
}
