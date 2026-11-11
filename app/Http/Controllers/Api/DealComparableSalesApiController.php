<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComparableSale;
use App\Models\Deal;
use App\Models\Property;
use App\Services\ComparableSalesArvService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealComparableSalesApiController extends Controller
{
    public function index(Request $request, int $dealId)
    {
        [$tenant, $deal, $property] = $this->context($request, $dealId);

        $comps = ComparableSale::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('property_id', $property->id)
            ->latest('sale_date')
            ->get();

        return response()->json(['deal_id' => $deal->id, 'property_id' => $property->id, 'comps' => $comps]);
    }

    public function recalculate(Request $request, int $dealId)
    {
        [$tenant, $deal, $property] = $this->context($request, $dealId);

        $validator = Validator::make($request->all(), [
            'comps' => 'required|array|min:1|max:50',
            'comps.*.address' => 'required|string|max:255',
            'comps.*.sale_price' => 'required|numeric|min:1',
            'comps.*.sale_date' => 'required|date',
            'comps.*.external_id' => 'nullable|string|max:255',
            'comps.*.external_source' => 'nullable|string|max:100',
            'comps.*.source_record_id' => 'nullable|string|max:255',
            'comps.*.source_url' => 'nullable|url|max:2048',
            'comps.*.source_observed_at' => 'nullable|date',
            'comps.*.confidence' => 'nullable|numeric|min:0|max:100',
            'comps.*.property_type' => 'nullable|string|max:100',
            'comps.*.city' => 'nullable|string|max:100',
            'comps.*.state' => 'nullable|string|max:2',
            'comps.*.zip_code' => 'nullable|string|max:10',
            'comps.*.latitude' => 'nullable|numeric',
            'comps.*.longitude' => 'nullable|numeric',
            'comps.*.sqft' => 'nullable|integer|min:0',
            'comps.*.beds' => 'nullable|integer|min:0',
            'comps.*.baths' => 'nullable|numeric|min:0',
            'comps.*.lot_size' => 'nullable|numeric|min:0',
            'comps.*.year_built' => 'nullable|integer|min:1800|max:2100',
            'comps.*.distance_miles' => 'nullable|numeric|min:0',
            'comps.*.condition' => 'nullable|string|max:30',
            'comps.*.adjustments' => 'nullable|array',
            'comps.*.adjusted_price' => 'nullable|numeric|min:1',
            'comps.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed.', 'details' => $validator->errors()], 422);
        }

        $subject = [
            'property_type' => $property->property_type,
            'square_footage' => $property->square_footage,
            'bedrooms' => $property->bedrooms,
            'bathrooms' => $property->bathrooms,
            'year_built' => $property->year_built,
            'acreage' => $property->acreage,
        ];

        $persisted = [];
        foreach ($validator->validated()['comps'] as $comp) {
            $comp['tenant_id'] = $tenant->id;
            $comp['property_id'] = $property->id;
            $comp['state'] = isset($comp['state']) ? strtoupper($comp['state']) : null;

            $key = ['tenant_id' => $tenant->id, 'property_id' => $property->id];
            if (!empty($comp['external_source']) && !empty($comp['external_id'])) {
                $key['external_source'] = $comp['external_source'];
                $key['external_id'] = $comp['external_id'];
            } else {
                $key['address'] = $comp['address'];
                $key['sale_date'] = $comp['sale_date'];
            }

            $persisted[] = ComparableSale::withoutGlobalScopes()->updateOrCreate($key, $comp);
        }

        $calculation = app(ComparableSalesArvService::class)->calculate(
            $subject,
            array_map(fn ($model) => $model->toArray(), $persisted)
        );

        if ($calculation['arv'] !== null) {
            $analysis = \App\Models\DealAnalysis::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('deal_id', $deal->id)
                ->first();

            $analysisInput = [
                'arv' => $calculation['arv'],
                'arv_confidence' => $calculation['confidence'],
                'comp_summary' => $calculation['selected_comps'],
                'methodology' => 'weighted_comp_analysis',
                'source' => 'comparable_sales_api',
                'assumptions' => $calculation['assumptions'],
            ];

            $data = app(\App\Services\DealAnalysisService::class)->calculate($analysisInput, $analysis);

            \App\Models\DealAnalysis::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'deal_id' => $deal->id],
                $data
            );
        }

        return response()->json([
            'success' => true,
            'deal_id' => $deal->id,
            'property_id' => $property->id,
            'calculation' => $calculation,
            'comps' => $persisted,
        ]);
    }

    private function context(Request $request, int $dealId): array
    {
        $tenant = $request->attributes->get('tenant');

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $property = Property::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $deal->lead_id)
            ->firstOrFail();

        return [$tenant, $deal, $property];
    }
}
