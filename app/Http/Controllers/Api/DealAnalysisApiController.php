<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealAnalysis;
use App\Services\DealAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealAnalysisApiController extends Controller
{
    public function show(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $analysis = DealAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('deal_id', $deal->id)
            ->first();

        return response()->json(['deal_id' => $deal->id, 'analysis' => $analysis]);
    }

    public function store(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $validator = Validator::make($request->all(), [
            'arv' => 'nullable|numeric|min:0',
            'repair_estimate' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'assignment_fee_target' => 'nullable|numeric|min:0',
            'mao' => 'nullable|numeric|min:0',
            'arv_confidence' => 'nullable|numeric|min:0|max:100',
            'repair_confidence' => 'nullable|numeric|min:0|max:100',
            'exit_strategy' => 'nullable|string|max:50',
            'methodology' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:100',
            'comp_summary' => 'nullable|array',
            'repair_scope' => 'nullable|array',
            'assumptions' => 'nullable|array',
            'risk_flags' => 'nullable|array',
            'arv_human_override' => 'nullable|boolean',
            'repair_human_override' => 'nullable|boolean',
            'mao_human_override' => 'nullable|boolean',
            'human_override_reason' => 'nullable|string|max:1000',
            'analyzed_by' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed.', 'details' => $validator->errors()], 422);
        }

        $existing = DealAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('deal_id', $deal->id)
            ->first();

        $data = app(DealAnalysisService::class)->calculate($validator->validated(), $existing);

        $analysis = DealAnalysis::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'deal_id' => $deal->id],
            $data
        );

        return response()->json([
            'success' => true,
            'deal_id' => $deal->id,
            'analysis' => $analysis->fresh(),
        ], $existing ? 200 : 201);
    }

    public function update(Request $request, int $dealId)
    {
        return $this->store($request, $dealId);
    }
}