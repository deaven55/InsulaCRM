<?php

namespace App\Services;

class DealRankingService
{
    /**
     * Rank a wholesale deal from 0-100 using only supplied/canonical facts.
     *
     * This is an acquisition prioritization score, not an appraisal, valuation,
     * lending decision, or guarantee of profitability.
     */
    public function calculate(array $input): array
    {
        $score = 50.0;
        $factors = [];
        $risks = [];

        $arv = $this->num($input['arv'] ?? null);
        $repairs = $this->num($input['repairs'] ?? null);
        $asking = $this->num($input['asking_price'] ?? null);
        $offerTarget = $this->num($input['offer_target'] ?? null);
        $arvConfidence = $this->num($input['arv_confidence'] ?? null);
        $compCount = max(0, (int) ($input['comp_count'] ?? 0));
        $motivation = $this->num($input['motivation_score'] ?? null);
        $buyerMatches = max(0, (int) ($input['buyer_match_count'] ?? 0));
        $propertyCompleteness = $this->num($input['property_completeness'] ?? null);
        $distressSignals = max(0, (int) ($input['distress_signal_count'] ?? 0));
        $riskFlagCount = max(0, (int) ($input['risk_flag_count'] ?? 0));

        if ($motivation !== null) {
            $score += ($motivation - 50) * 0.12;
            $factors[] = 'Seller motivation: '.round($motivation, 1).'/100.';
        } else {
            $risks[] = 'Seller motivation is unavailable.';
        }

        if ($distressSignals > 0) {
            $score += min(12, $distressSignals * 3);
            $factors[] = 'Distress signals detected: '.$distressSignals.'.';
        } else {
            $factors[] = 'No documented distress signal supplied.';
        }

        if ($arv !== null && $repairs !== null && $arv > 0) {
            $repairRatio = ($repairs / $arv) * 100;
            if ($repairRatio <= 10) $score += 7;
            elseif ($repairRatio <= 20) $score += 3;
            elseif ($repairRatio >= 35) $score -= 10;
            elseif ($repairRatio >= 25) $score -= 5;
            $factors[] = 'Repairs/ARV: '.round($repairRatio, 1).'%.';
        } else {
            $risks[] = 'ARV or repairs are missing.';
        }

        if ($arvConfidence !== null) {
            $score += ($arvConfidence - 50) * 0.16;
            $factors[] = 'ARV confidence: '.round($arvConfidence, 1).'/100.';
            if ($arvConfidence < 50) $risks[] = 'Low ARV confidence.';
        } else {
            $risks[] = 'ARV confidence is unavailable.';
        }

        $score += min(10, $compCount * 2);
        if ($compCount >= 3) {
            $factors[] = 'Closed comparable sales available: '.$compCount.'.';
        } else {
            $risks[] = 'Fewer than 3 comparable sales are available.';
        }

        if ($asking !== null && $asking > 0 && $offerTarget !== null) {
            $spread = (($asking - $offerTarget) / $asking) * 100;
            $factors[] = 'Offer target vs asking spread: '.round($spread, 1).'%.';
            if ($offerTarget >= $asking) $score += 10;
            elseif ($spread <= 10) $score += 5;
            elseif ($spread <= 20) $score += 1;
            else $score -= 7;
        } else {
            $risks[] = 'Asking price or offer target is unavailable.';
        }

        if ($propertyCompleteness !== null) {
            $score += ($propertyCompleteness - 50) * 0.10;
            $factors[] = 'Property data completeness: '.round($propertyCompleteness, 1).'/100.';
            if ($propertyCompleteness < 60) $risks[] = 'Property intelligence is incomplete.';
        }

        if ($buyerMatches > 0) {
            $score += min(8, $buyerMatches * 1.5);
            $factors[] = 'Potential buyer matches: '.$buyerMatches.'.';
        } else {
            $risks[] = 'No buyer matches are currently documented.';
        }

        if ($riskFlagCount > 0) {
            $score -= min(15, $riskFlagCount * 3);
            $risks[] = 'Analysis risk flags present: '.$riskFlagCount.'.';
        }

        $score = round(max(0, min(100, $score)), 2);
        $grade = $score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 55 ? 'C' : 'D'));

        return [
            'status' => 'calculated',
            'ranking_score' => $score,
            'ranking_grade' => $grade,
            'factors' => $factors,
            'risk_flags' => array_values(array_unique($risks)),
            'assumptions' => [
                'Ranking is an acquisition prioritization heuristic, not an appraisal, CMA, BPO, lending decision, or guaranteed profit outcome.',
                'Scores improve when source-verified property facts, closed comparable sales, repair estimates, seller motivation, and buyer demand are available.',
                'Human review is required before contract, offer, or disposition decisions.',
            ],
        ];
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
