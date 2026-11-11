<?php

namespace App\Services;

class DealOfferDecisionService
{
    public function calculate(array $input): array
    {
        $arv = $this->num($input['arv'] ?? null);
        $repairs = $this->num($input['repairs'] ?? null);
        $asking = $this->num($input['asking_price'] ?? null);
        $assignment = $this->num($input['assignment_fee_target'] ?? 10000) ?? 10000;
        $closing = $this->num($input['closing_costs'] ?? null) ?? 0;
        $holding = $this->num($input['holding_costs'] ?? null) ?? 0;
        $selling = $this->num($input['selling_costs'] ?? null) ?? 0;

        if ($arv === null || $repairs === null) {
            return [
                'status'=>'insufficient_data','decision'=>'REVIEW','decision_score'=>null,
                'offer_low'=>null,'offer_target'=>null,'offer_high'=>null,
                'factors'=>['Missing ARV or repair estimate.'],
                'assumptions'=>['No offer was calculated from missing core valuation inputs.'],
            ];
        }

        $base = ($arv * 0.70) - $repairs - $assignment - $closing - $holding - $selling;
        $target = max(0, $base);
        $low = max(0, $target - max(2500, $arv * 0.02));
        $high = max($low, $target + max(2500, $arv * 0.02));

        $score = 50;
        $factors = [];

        $arvConfidence = $this->num($input['arv_confidence'] ?? null);
        if ($arvConfidence !== null) {
            $score += ($arvConfidence - 50) * 0.45;
            $factors[] = 'ARV confidence: '.round($arvConfidence,1);
        } else {
            $factors[] = 'ARV confidence unavailable.';
        }

        $compCount = (int) ($input['comp_count'] ?? 0);
        $score += min(15, $compCount * 2);
        $factors[] = 'Comparable sales used: '.$compCount;

        if ($asking !== null && $asking > 0) {
            $discount = (($asking - $target) / $asking) * 100;
            $factors[] = 'Target offer vs asking: '.round($discount,1).'% difference.';
            if ($target >= $asking) $score += 15;
            elseif ($target >= $asking * 0.85) $score += 5;
            else $score -= 10;
        }

        $repairRatio = $arv > 0 ? ($repairs / $arv) * 100 : 100;
        $factors[] = 'Repairs as % of ARV: '.round($repairRatio,1).'%';
        if ($repairRatio <= 10) $score += 5;
        elseif ($repairRatio >= 30) $score -= 10;

        $score = round(max(0, min(100, $score)), 2);
        $decision = $score >= 75 ? 'BUY' : ($score >= 55 ? 'NEGOTIATE' : 'PASS');

        return [
            'status'=>'calculated','decision'=>$decision,'decision_score'=>$score,
            'offer_low'=>round($low,2),'offer_target'=>round($target,2),'offer_high'=>round($high,2),
            'factors'=>$factors,
            'assumptions'=>[
                'Base offer uses a 70% ARV rule less repairs, target assignment fee, and supplied transaction costs.',
                'The 70% factor is an internal underwriting heuristic, not an appraisal standard or guaranteed market outcome.',
                'Human review remains required before presenting an offer to a seller.',
            ],
        ];
    }

    private function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float)$v : null;
    }
}
