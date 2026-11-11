<?php

namespace App\\Http\\Controllers\\Api;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Buyer;
use App\\Models\\Deal;
use App\\Models\\DealBuyerMatch;
use App\\Services\\BuyerMatchService;
use App\\Services\\IntegrationIdempotencyService;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Validator;

class DealBuyerMatchApiController extends Controller
{
    public function index(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $query = DealBuyerMatch::query()
            ->where('deal_id', $deal->id)
            ->with('buyer');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->orderByDesc('match_score')->paginate($request->integer('per_page', 25))
        );
    }

    public function match(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');
        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $matches = app(BuyerMatchService::class)->matchForDeal($deal);

        return response()->json([
            'success' => true,
            'deal_id' => $deal->id,
            'matches' => $matches->map(fn ($match) => [
                'buyer_id' => $match['buyer']->id,
                'match_score' => $match['score'],
            ])->values(),
        ]);
    }

    public function store(Request $request, int $dealId)
    {
        $tenant = $request->attributes->get('tenant');
        $idempotency = app(IntegrationIdempotencyService::class);

        $replay = $idempotency->replay($request, $tenant->id, 'disposition');
        if ($replay) {
            return $replay;
        }

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $validator = Validator::make($request->all(), [
            'buyer_id' => 'required|integer',
            'match_score' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'outreach_status' => 'nullable|string|max:50',
            'buyer_notes' => 'nullable|string',
            'notified_at' => 'nullable|date',
            'responded_at' => 'nullable|date',
            'last_contacted_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        Buyer::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($data['buyer_id']);

        $match = DealBuyerMatch::updateOrCreate(
            ['deal_id' => $deal->id, 'buyer_id' => $data['buyer_id']],
            array_merge(
                ['match_score' => 0],
                array_filter($data, fn ($value) => $value !== null)
            )
        );

        $response = response()->json([
            'success' => true,
            'match_id' => $match->id,
            'match' => $match->fresh('buyer'),
        ], 201);

        $idempotency->remember(
            $request,
            $tenant->id,
            'disposition',
            'deal_buyer_match.upserted',
            'deal_buyer_match',
            $match->id,
            $response
        );

        return $response;
    }

    public function update(Request $request, int $dealId, int $matchId)
    {
        $tenant = $request->attributes->get('tenant');

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($dealId);

        $match = DealBuyerMatch::where('deal_id', $deal->id)
            ->where('id', $matchId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'match_score' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'outreach_status' => 'nullable|string|max:50',
            'buyer_notes' => 'nullable|string',
            'notified_at' => 'nullable|date',
            'responded_at' => 'nullable|date',
            'last_contacted_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed.',
                'details' => $validator->errors(),
            ], 422);
        }

        $match->update($validator->validated());

        return response()->json([
            'success' => true,
            'match' => $match->fresh('buyer'),
        ]);
    }
}
