# Deal Ranking Engine

## Purpose

The Deal Ranking Engine converts canonical deal intelligence into a consistent acquisition-priority score.

It is a prioritization heuristic for deciding which wholesale opportunities deserve attention first. It is not an appraisal, CMA, BPO, lending decision, or guarantee of profitability.

## Inputs

The endpoint can use:

- ARV and ARV confidence
- repair estimate
- seller asking price
- calculated offer target
- seller motivation score
- closed comparable-sale count
- active buyer-match count
- property intelligence completeness
- documented distress-signal count
- analysis risk-flag count

Missing values are not fabricated. Missing core evidence lowers confidence through explicit risk flags rather than invented facts.

## Scoring

The service starts at 50/100 and applies bounded adjustments for:

1. seller motivation
2. documented distress
3. repair burden relative to ARV
4. ARV confidence
5. comparable-sales strength
6. offer-target versus asking-price spread
7. property-data completeness
8. buyer demand
9. analysis risk flags

Grades:

- A: 85-100
- B: 70-84.99
- C: 55-69.99
- D: 0-54.99

## API

POST /api/v1/deals/{dealId}/rank

The endpoint is tenant-safe and resolves the deal, property, lead, analysis, and buyer-match context within the authenticated tenant.

## Persistence

The result is persisted on deal_analyses:

- ranking_score
- ranking_grade
- ranking_factors
- ranking_risk_flags
- ranked_at

The factors and risk flags preserve the reasons behind the current ranking.

## Important limitation

The ranking engine intentionally does not infer a distressed condition from a vague property description. Distress signals should come from documented evidence or an upstream acquisition/enrichment workflow.

Comparable-sale data should be source-specific and verified before being treated as strong valuation evidence. The North Carolina Appraisal Board emphasizes identifying the source of comparable-sale data and verifying/analyzing the information used.
