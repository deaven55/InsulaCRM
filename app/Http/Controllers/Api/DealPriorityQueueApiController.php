<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DealPriorityQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealPriorityQueueApiController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'state' => 'nullable|string|size:2',
            'county' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:120',
            'stage' => 'nullable|string|max:60',
            'ranking_grade' => 'nullable|in:A,B,C,D,a,b,c,d',
            'min_score' => 'nullable|numeric|min:0|max:100',
            'max_score' => 'nullable|numeric|min:0|max:100',
            'decision' => 'nullable|in:BUY,NEGOTIATE,PASS,REVIEW,buy,negotiate,pass,review',
            'has_arv' => 'nullable|in:true,false',
            'has_buyer_match' => 'nullable|in:true,false',
            'compliance_status' => 'nullable|in:REVIEW,review',
            'sort' => 'nullable|in:priority,newest,closing_soon,stage_age',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed.',
                'details' => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();
        $filters['min_score'] ??= null;
        $filters['max_score'] ??= null;
        $filters['has_arv'] ??= null;
        $filters['has_buyer_match'] ??= null;

        $paginator = app(DealPriorityQueueService::class)->paginate($filters, $tenant->id);

        $items = collect($paginator->items())
            ->map(fn ($deal) => app(DealPriorityQueueService::class)->transform($deal))
            ->values();

        return response()->json([
            'success' => true,
            'filters' => $filters,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
