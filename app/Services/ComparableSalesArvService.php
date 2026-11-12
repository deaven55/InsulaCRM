<?php

namespace App\Services;

use App\Models\ComparableSale;

class ComparableSalesArvService
{
    /**
     * Rank supplied comparable sales and calculate a defensible ARV range.
     *
     * The service never invents a comp or a missing sale price. It uses only
     * persisted/supplied comparable facts and makes the weighting transparent.
     */
    public function calculate(array $subject, array $comps): array
    {
        $eligible = [];

        foreach ($comps as $comp) {
            $price = $this->number($comp['adjusted_price'] ?? $comp['sale_price'] ?? null);
            $saleDate = $comp['sale_date'] ?? null;

            if ($price === null || $price <= 0 || empty($saleDate)) {
                continue;
            }

            $score = $this->similarityScore($subject, $comp);
            if ($score < 40) {
                continue;
            }

            $eligible[] = [
                'comp' => $comp,
                'price' => $price,
                'score' => $score,
                'weight' => max(0.01, $score / 100),
            ];
        }

        usort($eligible, fn ($a, $b) => $b['score'] <=> $a['score']);

        $selected = array_slice($eligible, 0, 8);

        if (count($selected) < 3) {
            return [
                'status' => 'insufficient_comps',
                'arv' => null,
                'arv_low' => null,
                'arv_high' => null,
                'confidence' => null,
                'selected_comps' => $this->summaries($selected),
                'methodology' => 'weighted_comp_analysis',
                'assumptions' => ['At least 3 sufficiently comparable closed sales are required for an ARV estimate.'],
            ];
        }

        $weightedMean = $this->weightedMean($selected);
        $weightedMedian = $this->weightedMedian($selected);

        $prices = array_map(fn ($x) => $x['price'], $selected);
        sort($prices);
        $trimmed = count($prices) >= 5
            ? array_slice($prices, 1, count($prices) - 2)
            : $prices;
        $trimmedMean = array_sum($trimmed) / max(1, count($trimmed));

        $arv = round(($weightedMean * 0.35) + ($weightedMedian * 0.45) + ($trimmedMean * 0.20), 2);
        $spread = $this->weightedSpread($selected, $arv);
        $rangeHalfWidth = max($arv * 0.05, min($arv * 0.12, $spread * 1.35));

        $avgScore = array_sum(array_map(fn ($x) => $x['score'], $selected)) / count($selected);
        $confidence = round(max(0, min(100, 45 + ($avgScore * 0.45) + min(15, (count($selected) - 3) * 3))), 2);

        return [
            'status' => 'calculated',
            'arv' => $arv,
            'arv_low' => round(max(0, $arv - $rangeHalfWidth), 2),
            'arv_high' => round($arv + $rangeHalfWidth, 2),
            'confidence' => $confidence,
            'selected_comps' => $this->summaries($selected),
            'methodology' => 'weighted_comp_analysis',
            'calculation' => [
                'weighted_mean' => round($weightedMean, 2),
                'weighted_median' => round($weightedMedian, 2),
                'trimmed_mean' => round($trimmedMean, 2),
                'weighted_spread' => round($spread, 2),
            ],
            'assumptions' => [
                'Only supplied comparable sales with explicit sale price and sale date were eligible.',
                'Comparables with similarity score below 40 were excluded.',
                'ARV combines weighted mean, weighted median, and trimmed mean to reduce outlier influence.',
            ],
        ];
    }

    private function similarityScore(array $subject, array $comp): float
    {
        $score = 50.0;

        $distance = $this->number($comp['distance_miles'] ?? null);
        if ($distance !== null) {
            $score += max(-20, 15 - ($distance * 5));
        }

        $subjectSqft = $this->number($subject['square_footage'] ?? null);
        $compSqft = $this->number($comp['sqft'] ?? null);
        if ($subjectSqft && $compSqft) {
            $ratio = abs($compSqft - $subjectSqft) / $subjectSqft;
            $score += max(-20, 15 - ($ratio * 60));
        }

        $subjectBeds = $this->number($subject['bedrooms'] ?? null);
        $compBeds = $this->number($comp['beds'] ?? null);
        if ($subjectBeds !== null && $compBeds !== null) {
            $score += max(-8, 6 - (abs($subjectBeds - $compBeds) * 4));
        }

        $subjectBaths = $this->number($subject['bathrooms'] ?? null);
        $compBaths = $this->number($comp['baths'] ?? null);
        if ($subjectBaths !== null && $compBaths !== null) {
            $score += max(-8, 6 - (abs($subjectBaths - $compBaths) * 4));
        }

        $subjectYear = $this->number($subject['year_built'] ?? null);
        $compYear = $this->number($comp['year_built'] ?? null);
        if ($subjectYear && $compYear) {
            $score += max(-6, 5 - (abs($subjectYear - $compYear) / 10));
        }

        if (!empty($subject['property_type']) && !empty($comp['property_type'])) {
            $score += strtolower($subject['property_type']) === strtolower($comp['property_type']) ? 10 : -15;
        }

        $saleDate = strtotime((string) ($comp['sale_date'] ?? ''));
        if ($saleDate) {
            $monthsOld = max(0, (time() - $saleDate) / 2592000);
            $score += max(-10, 8 - ($monthsOld * 0.5));
        }

        if (isset($comp['confidence']) && is_numeric($comp['confidence'])) {
            $score += ((float) $comp['confidence'] - 50) * 0.10;
        }

        return round(max(0, min(100, $score)), 2);
    }

    private function weightedMean(array $items): float
    {
        $numerator = 0;
        $denominator = 0;

        foreach ($items as $item) {
            $numerator += $item['price'] * $item['weight'];
            $denominator += $item['weight'];
        }

        return $denominator ? $numerator / $denominator : 0;
    }

    private function weightedMedian(array $items): float
    {
        usort($items, fn ($a, $b) => $a['price'] <=> $b['price']);
        $total = array_sum(array_map(fn ($x) => $x['weight'], $items));
        $running = 0;

        foreach ($items as $item) {
            $running += $item['weight'];
            if ($running >= ($total / 2)) {
                return $item['price'];
            }
        }

        return $items[count($items) - 1]['price'];
    }

    private function weightedSpread(array $items, float $center): float
    {
        $total = array_sum(array_map(fn ($x) => $x['weight'], $items));
        if ($total <= 0) {
            return 0;
        }

        $variance = 0;
        foreach ($items as $item) {
            $variance += $item['weight'] * (($item['price'] - $center) ** 2);
        }

        return sqrt($variance / $total);
    }

    private function summaries(array $items): array
    {
        return array_map(fn ($x) => [
            'id' => $x['comp']['id'] ?? null,
            'address' => $x['comp']['address'] ?? null,
            'sale_price' => $this->number($x['comp']['sale_price'] ?? null),
            'adjusted_price' => $this->number($x['comp']['adjusted_price'] ?? null),
            'sale_date' => $x['comp']['sale_date'] ?? null,
            'distance_miles' => $this->number($x['comp']['distance_miles'] ?? null),
            'similarity_score' => round($x['score'], 2),
            'source' => $x['comp']['external_source'] ?? null,
            'source_url' => $x['comp']['source_url'] ?? null,
        ], $items);
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
