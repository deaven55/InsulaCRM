<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealAnalysis;
use App\Models\DealBuyerMatch;
use App\Models\Property;
use App\Services\DealRankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealRankingApiController extends Controller
{
    public function calculate(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $property = Property::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $deal->lead_id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'arv' => 'nullable|numeric|min:0',
            'repairs' => 'nullable|numeric|min:0',
            'asking_price' => 'nullable|numeric|min:0',
            'offer_target' => 'nullable|numeric|min:0',
            'arv_confidence' => 'nullable|numeric|min:0|max:100',
            'comp_count' => 'nullable|integer|min:0|max:100',
            'motivation_score' => 'nullable|numeric|min:0|max:100',
            'buyer_match_count' => 'nullable|integer|min:0|max:1000',
            'property_completeness' => 'nullable|numeric|min:0|max:100',
            'distress_signal_count' => 'nullable|integer|min:0|max:100',
            'risk_flag_count' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed.',
                'details' => $validator->errors(),
            ], 422);
        }

        $analysis = DealAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('deal_id', $deal->id)
            ->first();

        $lead = $deal->lead()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

        $propertyFacts = [
            'owner_name', 'mailing_address', 'parcel_id', 'assessed_value',
            'land_value', 'building_value', 'acreage', 'tax_amount', 'tax_status',
            'delinquent_tax_amount', 'zoning', 'legal_description',
            'last_verified_sale_date', 'last_verified_sale_price', 'permit_records',
        ];

        $present = 0;
        foreach ($propertyFacts as $field) {
            $value = $property->{$field};
            if ($value !== null && $value !== '' && $value !== []) {
                $present++;
            }
        }
        $propertyCompleteness = round(($present / count($propertyFacts)) * 100, 2);

        $riskFlags = is_array($analysis?->risk_flags) ? $analysis->risk_flags : [];
        $compSummary = is_array($analysis?->comp_summary) ? $analysis->comp_summary : [];
        $motivation = $lead?->ai_motivation_score ?? $lead?->motivation_score;

        $input = array_merge([
            'arv' => $analysis?->arv,
            'repairs' => $analysis?->repair_estimate,
            'asking_price' => $property->asking_price,
            'offer_target' => $analysis?->offer_target,
            'arv_confidence' => $analysis?->arv_confidence,
            'comp_count' => count($compSummary),
            'motivation_score' => $motivation,
            'buyer_match_count' => DealBuyerMatch::where('deal_id', $deal->id)
                ->whereIn('status', ['pending', 'interested', 'responded'])
                ->count(),
            'property_completeness' => $propertyCompleteness,
            'distress_signal_count' => 0,
            'risk_flag_count' => count($riskFlags),
        ], $validator->validated());

        $result = app(DealRankingService::class)->calculate($input);

        $analysis ??= new DealAnalysis([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
        ]);

        $analysis->fill([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'ranking_score' => $result['ranking_score'],
            'ranking_grade' => $result['ranking_grade'],
            'ranking_factors' => $result['factors'],
            'ranking_risk_flags' => $result['risk_flags'],
            'ranked_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'deal_id' => $deal->id,
            'property_id' => $property->id,
            'ranking' => $result,
        ]);
    }
}
