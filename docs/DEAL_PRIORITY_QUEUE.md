# Deal Priority Queue

The priority queue is the canonical read model for the wholesaling operating system dashboard.

## Endpoint

GET `/api/v1/deal-queue`

The endpoint is tenant-scoped and returns paginated deals enriched with:

- seller/contact status and motivation
- canonical property intelligence
- ARV/repairs/offer underwriting
- acquisition ranking
- buyer-match demand
- jurisdiction compliance review
- missing-data/work-queue actions

## Filters

- `state`: two-letter postal abbreviation
- `county`: canonical property county
- `city`
- `stage`
- `ranking_grade`: A/B/C/D
- `min_score`, `max_score`
- `decision`: BUY/NEGOTIATE/PASS/REVIEW
- `has_arv`: true/false
- `has_buyer_match`: true/false
- `compliance_status`: REVIEW
- `sort`: priority/newest/closing_soon/stage_age
- `per_page`: 1-100

Default sort is highest ranking score first, followed by newest deals. Deals without a ranking remain visible after ranked deals so incomplete work is not hidden.

## Work-queue semantics

The response exposes:

- `ready_for_offer`: ARV, repairs, offer target, ranking, and closed comps are present.
- `ready_for_disposition`: at least one active buyer match exists and the deal has been ranked.
- `missing`: explicit next data/work items.

## Compliance

The queue calls the jurisdiction compliance service using the property's canonical state. Current North Carolina guidance remains conservative: NCREC states a bona fide buyer may assign contractual rights/interests, while certain seller solicitation, misrepresentation, negotiation, and general buyer marketing conduct can constitute unlicensed brokerage. The queue therefore surfaces REVIEW rather than automatically clearing seller/disposition activity. citeturn165097search0

Unknown states remain REVIEW until a jurisdiction profile is curated.

## Design boundary

This endpoint is a read model. It does not replace the canonical Deal, Property, Lead, DealAnalysis, Evidence, or BuyerMatch records and does not fabricate missing intelligence.
