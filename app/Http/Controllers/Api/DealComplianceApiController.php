<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Property;
use App\Services\JurisdictionComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealComplianceApiController extends Controller
{
    public function check(Request $request, int $dealId)
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
            'activity' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed.',
                'details' => $validator->errors(),
            ], 422);
        }

        $state = $request->input('state', $property->state);
        $activity = $request->input('activity', 'acquisition');

        $result = app(JurisdictionComplianceService::class)->check([
            'state' => $state,
            'activity' => $activity,
        ]);

        return response()->json([
            'success' => true,
            'deal_id' => $deal->id,
            'property_id' => $property->id,
            'compliance' => $result,
        ]);
    }
}
