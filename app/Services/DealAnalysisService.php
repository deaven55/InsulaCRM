<?php

namespace App\Services;

use App\Models\Deal;

class DealAnalysisService
{
    public function calculate(array $input, $existing = null): array
    {
        $arv = $this->number($input['arv'] ?? $existing?->arv);
        $repairs = $this->number($input['repair_estimate'] ?? $existing?->repair_estimate);
        $purchase = $this->number($input['purchase_price'] ?? $existing?->purchase_price);
        $assignmentTarget = $this->number($input['assignment_fee_target'] ?? $existing?->assignment_fee_target);

        $maoOverride = (bool) ($input['mao_human_override'] ?? $existing?->mao_human_override ?? false);
        $mao = $this->number($input['mao'] ?? null);

        if (!$maoOverride && $arv !== null && $repairs !== null) {
            $fee = $assignmentTarget ?? 10000;
            $mao = max(0, round(($arv * 0.70) - $repairs - $fee, 2));
        }

        $projectedAssignment = null;
        if ($arv !== null && $repairs !== null && $purchase !== null) {
            $projectedAssignment = max(0, round(($arv * 0.70) - $repairs - $purchase, 2));
        }

        $equity = null;
        if ($arv !== null && $purchase !== null) {
            $equity = max(0, round($arv - $purchase, 2));
        }

        $arvConfidence = $this->confidence($input['arv_confidence'] ?? $existing?->arv_confidence);
        $repairConfidence = $this->confidence($input['repair_confidence'] ?? $existing?->repair_confidence);
        $confidenceValues = array_filter([$arvConfidence, $repairConfidence], fn ($v) => $v !== null);
        $overall = $confidenceValues ? round(array_sum($confidenceValues) / count($confidenceValues), 2) : null;
        $score = $this->score($arv, $repairs, $purchase, $mao, $overall);

        return [
            'arv' => $arv,
            'repair_estimate' => $repairs,
            'purchase_price' => $purchase,
            'assignment_fee_target' => $assignmentTarget,
            'mao' => $mao,
            'projected_assignment_fee' => $projectedAssignment,
            'estimated_equity' => $equity,
            'arv_confidence' => $arvConfidence,
            'repair_confidence' => $repairConfidence,
            'overall_confidence' => $overall,
            'deal_score' => $score,
            'exit_strategy' => $input['exit_strategy'] ?? $existing?->exit_strategy,
            'methodology' => $input['methodology'] ?? $existing?->methodology ?? '70_percent_rule',
            'source' => $input['source'] ?? $existing?->source,
            'comp_summary' => $input['comp_summary'] ?? $existing?->comp_summary,
            'repair_scope' => $input['repair_scope'] ?? $existing?->repair_scope,
            'assumptions' => $input['assumptions'] ?? $existing?->assumptions,
            'risk_flags' => $input['risk_flags'] ?? $existing?->risk_flags,
            'calculation_snapshot' => [
                'formula' => 'MAO = ARV × 70% − repairs − assignment fee target',
                'arv' => $arv,
                'repair_estimate' => $repairs,
                'purchase_price' => $purchase,
                'assignment_fee_target' => $assignmentTarget ?? 10000,
                'mao' => $mao,
                'projected_assignment_fee' => $projectedAssignment,
                'calculated_at' => now()->toIso8601String(),
            ],
            'arv_human_override' => (bool) ($input['arv_human_override'] ?? $existing?->arv_human_override ?? false),
            'repair_human_override' => (bool) ($input['repair_human_override'] ?? $existing?->repair_human_override ?? false),
            'mao_human_override' => $maoOverride,
            'human_override_reason' => $input['human_override_reason'] ?? $existing?->human_override_reason,
            'analyzed_at' => now(),
            'analyzed_by' => $input['analyzed_by'] ?? 'integration_ai',
        ];
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function confidence(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return max(0, min(100, (float) $value));
    }

    private function score(?float $arv, ?float $repairs, ?float $purchase, ?float $mao, ?float $confidence): ?int
    {
        if ($arv === null || $repairs === null || $purchase === null || $mao === null) {
            return null;
        }

        $margin = $arv > 0 ? (($arv - $repairs - $purchase) / $arv) * 100 : 0;
        $score = 50 + ($margin * 1.5) + (($confidence ?? 50) - 50) * 0.35;

        return max(0, min(100, (int) round($score)));
    }
}