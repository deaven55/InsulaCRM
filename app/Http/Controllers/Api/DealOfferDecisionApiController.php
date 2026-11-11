<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealAnalysis;
use App\Models\Property;
use App\Services\DealOfferDecisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealOfferDecisionApiController extends Controller
{
    public function calculate(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');
        $deal = Deal::withoutGlobalScopes()->where('tenant_id',$tenant->id)->findOrFail($dealId);
        $property = Property::withoutGlobalScopes()->where('tenant_id',$tenant->id)->where('lead_id',$deal->lead_id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'arv'=>'nullable|numeric|min:0',
            'repairs'=>'nullable|numeric|min:0',
            'asking_price'=>'nullable|numeric|min:0',
            'assignment_fee_target'=>'nullable|numeric|min:0',
            'closing_costs'=>'nullable|numeric|min:0',
            'holding_costs'=>'nullable|numeric|min:0',
            'selling_costs'=>'nullable|numeric|min:0',
            'arv_confidence'=>'nullable|numeric|min:0|max:100',
            'comp_count'=>'nullable|integer|min:0|max:100',
        ]);
        if ($validator->fails()) return response()->json(['error'=>'Validation failed.','details'=>$validator->errors()],422);

        $analysis = DealAnalysis::withoutGlobalScopes()
            ->where('tenant_id',$tenant->id)->where('deal_id',$deal->id)->first();

        $input = array_merge([
            'arv'=>$analysis?->arv,
            'repairs'=>$analysis?->repairs,
            'arv_confidence'=>$analysis?->arv_confidence,
            'comp_count'=>is_array($analysis?->comp_summary) ? count($analysis->comp_summary) : 0,
            'asking_price'=>$property->asking_price,
        ], $validator->validated());

        $result = app(DealOfferDecisionService::class)->calculate($input);

        if ($result['status'] === 'calculated') {
            if (!$analysis) {
                $analysis = new DealAnalysis(['tenant_id'=>$tenant->id,'deal_id'=>$deal->id]);
            }
            $analysis->fill([
                'tenant_id'=>$tenant->id,
                'deal_id'=>$deal->id,
                'offer_low'=>$result['offer_low'],
                'offer_target'=>$result['offer_target'],
                'offer_high'=>$result['offer_high'],
                'decision'=>$result['decision'],
                'decision_score'=>$result['decision_score'],
                'decision_factors'=>$result['factors'],
                'offer_assumptions'=>$result['assumptions'],
            ])->save();
        }

        return response()->json([
            'success'=>true,'deal_id'=>$deal->id,'property_id'=>$property->id,'decision'=>$result,
        ]);
    }
}
