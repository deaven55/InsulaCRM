<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealEvidence;
use App\Services\IntegrationIdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealEvidenceApiController extends Controller
{
    public function index(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $query = DealEvidence::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('deal_id', $deal->id);

        if ($request->filled('evidence_type')) {
            $query->where('evidence_type', $request->string('evidence_type'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->string('source'));
        }

        return response()->json([
            'deal_id' => $deal->id,
            'evidence' => $query->orderByDesc('observed_at')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');
        $idempotency = app(IntegrationIdempotencyService::class);

        if ($replay = $idempotency->replay($request, $tenant->id, 'property_intelligence')) {
            return $replay;
        }

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $validator = Validator::make($request->all(), [
            'evidence_type' => 'required|string|max:50',
            'source' => 'required|string|max:100',
            'source_record_id' => 'nullable|string|max:255',
            'content_hash' => 'nullable|string|size:64',
            'observed_at' => 'nullable|date',
            'confidence' => 'nullable|numeric|min:0|max:100',
            'payload' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed.', 'details' => $validator->errors()], 422);
        }

        $input = $validator->validated();
        $evidence = DealEvidence::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            ...$input,
        ]);

        $response = response()->json([
            'success' => true,
            'deal_id' => $deal->id,
            'evidence' => $evidence->fresh(),
        ], 201);

        $idempotency->remember(
            $request,
            $tenant->id,
            'property_intelligence',
            'evidence.create',
            'deal_evidence',
            $evidence->id,
            $response
        );

        return $response;
    }
}