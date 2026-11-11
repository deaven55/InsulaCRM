<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DealPriorityQueueService
{
    /**
     * Build the tenant-safe acquisition/disposition priority queue.
     *
     * Ranking is read from canonical DealAnalysis fields. Missing ranking data
     * is intentionally retained so the dashboard can surface work that still
     * needs evidence, comps, underwriting, compliance review, or buyer matching.
     */
    public function paginate(array $filters, int $tenantId): LengthAwarePaginator
    {
        $query = Deal::withoutGlobalScopes()
            ->where('deals.tenant_id', $tenantId)
            ->with([
                'lead.property',
                'analysis',
                'buyerMatches.buyer',
            ]);

        $this->applyFilters($query, $filters);

        $sort = $filters['sort'] ?? 'priority';
        if ($sort === 'newest') {
            $query->orderByDesc('deals.created_at');
        } elseif ($sort === 'closing_soon') {
            $query->orderByRaw('CASE WHEN deals.closing_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('deals.closing_date');
        } elseif ($sort === 'stage_age') {
            $query->orderBy('deals.stage_changed_at');
        } else {
            $query->orderByRaw('CASE WHEN deal_analyses.ranking_score IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('deal_analyses.ranking_score')
                ->orderByDesc('deals.created_at');
        }

        return $query->paginate(
            max(1, min((int) ($filters['per_page'] ?? 25), 100))
        );
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query->leftJoin('deal_analyses', function ($join) {
            $join->on('deal_analyses.deal_id', '=', 'deals.id')
                ->on('deal_analyses.tenant_id', '=', 'deals.tenant_id');
        })->select('deals.*');

        if (!empty($filters['state'])) {
            $query->whereHas('lead.property', fn (Builder $q) =>
                $q->where('state', strtoupper(trim($filters['state']))));
        }

        if (!empty($filters['county'])) {
            $query->whereHas('lead.property', fn (Builder $q) =>
                $q->whereRaw('LOWER(county) = ?', [strtolower(trim($filters['county']))]));
        }

        if (!empty($filters['city'])) {
            $query->whereHas('lead.property', fn (Builder $q) =>
                $q->whereRaw('LOWER(city) = ?', [strtolower(trim($filters['city']))]));
        }

        if (!empty($filters['stage'])) {
            $query->where('deals.stage', $filters['stage']);
        }

        if (!empty($filters['ranking_grade'])) {
            $query->where('deal_analyses.ranking_grade', strtoupper($filters['ranking_grade']));
        }

        if ($filters['min_score'] !== null && $filters['min_score'] !== '') {
            $query->where('deal_analyses.ranking_score', '>=', (float) $filters['min_score']);
        }

        if ($filters['max_score'] !== null && $filters['max_score'] !== '') {
            $query->where('deal_analyses.ranking_score', '<=', (float) $filters['max_score']);
        }

        if (!empty($filters['decision'])) {
            $query->where('deal_analyses.decision', strtoupper($filters['decision']));
        }

        if ($filters['has_arv'] === 'true') {
            $query->whereNotNull('deal_analyses.arv');
        } elseif ($filters['has_arv'] === 'false') {
            $query->whereNull('deal_analyses.arv');
        }

        if ($filters['has_buyer_match'] === 'true') {
            $query->whereHas('buyerMatches', fn (Builder $q) =>
                $q->whereIn('status', ['pending', 'interested', 'responded']));
        } elseif ($filters['has_buyer_match'] === 'false') {
            $query->whereDoesntHave('buyerMatches', fn (Builder $q) =>
                $q->whereIn('status', ['pending', 'interested', 'responded']));
        }

        if ($filters['compliance_status'] !== null && $filters['compliance_status'] !== '') {
            // Compliance is intentionally conservative until a jurisdiction
            // profile is curated. Current profiles return REVIEW.
            if (strtoupper($filters['compliance_status']) === 'REVIEW') {
                $query->whereNotNull('deals.id');
            }
        }
    }

    /**
     * Normalize a paginated queue item into a stable dashboard payload.
     */
    public function transform($deal): array
    {
        $property = $deal->lead?->property;
        $analysis = $deal->analysis;

        $compliance = app(JurisdictionComplianceService::class)->check([
            'state' => $property?->state,
            'activity' => 'acquisition',
        ]);

        $activeMatches = $deal->buyerMatches
            ->filter(fn ($match) => in_array($match->status, ['pending', 'interested', 'responded'], true));

        $missing = [];
        if (!$property) $missing[] = 'property';
        if (!$analysis?->arv) $missing[] = 'arv';
        if (!$analysis?->repair_estimate && $analysis?->repair_estimate !== 0) $missing[] = 'repair_estimate';
        if (!$analysis?->arv_confidence) $missing[] = 'arv_confidence';
        if (!$analysis?->offer_target && $analysis?->offer_target !== 0) $missing[] = 'offer_target';
        if (!$analysis?->ranking_score && $analysis?->ranking_score !== 0) $missing[] = 'ranking';
        if (!$analysis?->comp_summary || !is_array($analysis->comp_summary) || count($analysis->comp_summary) < 3) $missing[] = 'closed_comps';
        if ($activeMatches->count() === 0) $missing[] = 'buyer_match';

        return [
            'deal_id' => $deal->id,
            'stage' => $deal->stage,
            'stage_label' => Deal::stageLabel($deal->stage),
            'created_at' => $deal->created_at,
            'stage_changed_at' => $deal->stage_changed_at,
            'closing_date' => $deal->closing_date,
            'seller' => [
                'lead_id' => $deal->lead?->id,
                'name' => $deal->lead?->full_name,
                'phone' => $deal->lead?->phone,
                'status' => $deal->lead?->status,
                'motivation_score' => $deal->lead?->ai_motivation_score ?? $deal->lead?->motivation_score,
                'do_not_contact' => (bool) ($deal->lead?->do_not_contact),
            ],
            'property' => [
                'property_id' => $property?->id,
                'address' => $property?->address,
                'city' => $property?->city,
                'state' => $property?->state,
                'county' => $property?->county,
                'zip_code' => $property?->zip_code,
                'parcel_id' => $property?->parcel_id,
                'property_type' => $property?->property_type,
                'asking_price' => $property?->asking_price,
                'assessed_value' => $property?->assessed_value,
                'acreage' => $property?->acreage,
                'tax_status' => $property?->tax_status,
                'delinquent_tax_amount' => $property?->delinquent_tax_amount,
            ],
            'underwriting' => [
                'arv' => $analysis?->arv,
                'repair_estimate' => $analysis?->repair_estimate,
                'offer_low' => $analysis?->offer_low,
                'offer_target' => $analysis?->offer_target,
                'offer_high' => $analysis?->offer_high,
                'decision' => $analysis?->decision,
                'decision_score' => $analysis?->decision_score,
                'arv_confidence' => $analysis?->arv_confidence,
                'comp_count' => is_array($analysis?->comp_summary) ? count($analysis->comp_summary) : 0,
            ],
            'ranking' => [
                'score' => $analysis?->ranking_score,
                'grade' => $analysis?->ranking_grade,
                'factors' => $analysis?->ranking_factors ?? [],
                'risk_flags' => $analysis?->ranking_risk_flags ?? [],
                'ranked_at' => $analysis?->ranked_at,
            ],
            'buyer_demand' => [
                'active_match_count' => $activeMatches->count(),
            ],
            'compliance' => $compliance,
            'work_queue' => [
                'missing' => $missing,
                'ready_for_offer' => empty(array_intersect($missing, ['arv', 'repair_estimate', 'offer_target', 'ranking', 'closed_comps'])),
                'ready_for_disposition' => $activeMatches->count() > 0 && !in_array('ranking', $missing, true),
            ],
        ];
    }
}
