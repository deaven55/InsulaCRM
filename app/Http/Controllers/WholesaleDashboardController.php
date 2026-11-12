<?php

namespace App\Http\Controllers;

use App\Services\DealPriorityQueueService;
use Illuminate\Http\Request;

class WholesaleDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $filters = [
            'state' => $request->string('state')->trim()->upper()->toString() ?: null,
            'county' => $request->string('county')->trim()->toString() ?: null,
            'city' => $request->string('city')->trim()->toString() ?: null,
            'stage' => $request->string('stage')->trim()->toString() ?: null,
            'ranking_grade' => $request->string('ranking_grade')->trim()->upper()->toString() ?: null,
            'decision' => $request->string('decision')->trim()->upper()->toString() ?: null,
            'has_arv' => $request->string('has_arv')->toString() ?: null,
            'has_buyer_match' => $request->string('has_buyer_match')->toString() ?: null,
            'compliance_status' => $request->string('compliance_status')->trim()->upper()->toString() ?: null,
            'sort' => $request->string('sort')->trim()->toString() ?: 'priority',
            'per_page' => min(100, max(10, (int) $request->input('per_page', 25))),
            'min_score' => $request->input('min_score'),
            'max_score' => $request->input('max_score'),
        ];

        if ($user->isAgent() || $user->isDispositionAgent()) {
            // Keep the dashboard tenant-safe and role-safe. Agent ownership
            // filtering can be added without changing the queue contract.
            $filters['agent_id'] = $user->id;
        }

        $paginator = app(DealPriorityQueueService::class)->paginate($filters, $user->tenant_id);
        $items = collect($paginator->items())
            ->map(fn ($deal) => app(DealPriorityQueueService::class)->transform($deal));

        $summary = [
            'total' => $paginator->total(),
            'ranked' => $items->filter(fn ($item) => $item['ranking']['score'] !== null)->count(),
            'ready_for_offer' => $items->where('work_queue.ready_for_offer', true)->count(),
            'ready_for_disposition' => $items->where('work_queue.ready_for_disposition', true)->count(),
            'review' => $items->filter(fn ($item) => ($item['compliance']['status'] ?? null) === 'REVIEW')->count(),
        ];

        return view('wholesale.dashboard', [
            'items' => $items,
            'paginator' => $paginator,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }
}
