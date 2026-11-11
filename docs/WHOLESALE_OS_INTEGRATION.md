# Wholesale OS Integration Plan

## Objective

Unify the seller-side acquisition workflows, InsulaCRM, AI wholesaler skills, and buyer-side disposition workflows around InsulaCRM as the operational source of truth.

## Components

- `real-estate-acquisition-workflows`: seller lead ingestion, skip-trace routing, CRM import, TCPA-aware outreach, follow-up, inbound intent classification, DNC hygiene.
- `InsulaCRM`: tenant-aware CRM, leads, properties, deals, buyers, activities, API, disposition room, reporting and user permissions.
- `wholesaler-claude-skills`: research/analysis skills for property recon, comps/ARV, rehab estimates, creative finance, seller scripts and deal ranking.
- `real-estate-disposition-workflows`: deal intake/enrichment, buyer matching, distribution, response handling, follow-up, buyer scoring, deal package delivery, lifecycle and seller updates.
- `rafiulislam4246/bids-dashboard`: frontend reference for a future consolidated dashboard; it is not treated as a backend dependency.

## System of Record

InsulaCRM is the canonical application data layer.

Canonical entities:
- Tenant
- User / role
- Lead / Seller
- Property
- Deal
- Buyer
- Buyer criteria
- Distribution
- Activity
- Task
- Communication / message
- Offer
- Contract / document
- DNC / consent state

External automation may stage and enrich data, but it should write through the InsulaCRM API or an explicit integration adapter rather than maintain a second permanent CRM database.

## Integration Flow

1. Acquisition automation discovers and deduplicates a property/owner.
2. Skip-trace results are normalized.
3. Lead/property records are created or updated through `/api/v1`.
4. InsulaCRM owns lead status, DNC state, activities and seller pipeline.
5. AI skills enrich the property and deal analysis without becoming the source of truth.
6. A qualified deal enters the deal pipeline and disposition room.
7. Disposition automation reads eligible deals and buyer criteria.
8. Matching produces ranked buyers and distribution records.
9. Buyer responses update distribution/activity state in InsulaCRM.
10. Closed/expired outcomes update the deal lifecycle and analytics.

## API Contract

Existing API v1 already exposes:
- GET/POST/PUT leads
- GET/POST/PUT deals, including external identity lookup and disposition-safe idempotent creation
- GET/POST/PUT buyers, including external identity, proof-of-funds and buyer scoring fields
- GET/POST/PUT deal-buyer matches plus match recalculation
- GET/POST/PUT properties
- GET/POST activities
- GET stats

Integration work should extend this contract before adding direct database coupling from n8n.

## Non-Negotiable Rules

- Preserve tenant isolation on every integration path.
- Preserve DNC/consent enforcement before outbound communication.
- Normalize addresses and phone numbers before deduplication.
- Make webhook handlers idempotent.
- Store external IDs for n8n/Convex/Close/skip-trace records where needed.
- Do not copy secrets into workflow JSON or source control.
- Keep financial calculations auditable: ARV, repairs, MAO, assignment fee and deal score should retain inputs and methodology.
- Treat buyer matching as a recommendation/ranking layer; the operator remains able to override it.
- Do not make the public BIDS frontend repository a dependency for backend correctness.

## First Implementation Slice

### Phase 1 — Integration foundation
- Add integration metadata/external IDs to the relevant CRM entities.
- Add webhook/API endpoints needed by acquisition and disposition workflows.
- Add idempotency keys for inbound lead/deal/buyer events.
- Add structured integration logs and failure visibility.
- Document field mappings.

### Phase 2 — Acquisition
- Connect the seven acquisition workflows to InsulaCRM API.
- Map lead/property/contact fields.
- Preserve DNC and TCPA send-window checks.
- Route hot-lead alerts into CRM activities/tasks.

### Phase 3 — Deal analysis
- Add an auditable AI analysis record for property recon, comps, ARV, repairs and MAO.
- Store source timestamps and confidence/notes.
- Allow human override without deleting the AI result.

### Phase 4 — Disposition
- Connect the ten disposition workflows to InsulaCRM.
- Use the deal/buyer/match API as the stable integration boundary.
- Add first-class distribution/outreach persistence only where the existing match model cannot represent the workflow state.
- Add buyer criteria and distribution records if not already sufficient.
- Rank buyers by market, price, property type, strategy, tier and engagement.
- Track dispatch, response, follow-up and package delivery.

### Phase 5 — Unified dashboard
- Use InsulaCRM as the backend.
- Borrow useful BIDS dashboard UX patterns where they improve the existing CRM.
- Build acquisition, deal, disposition and analytics views directly into the tenant-aware application.

## Disposition API Contract

### Deals
- GET /api/v1/deals?external_id=...&external_source=...
- POST /api/v1/deals with external_id, external_source, seller/lead linkage and deal economics.
- PUT /api/v1/deals/{id} for stage, pricing and disposition lifecycle updates.

### Buyers
- GET /api/v1/buyers?external_id=...&external_source=...
- POST /api/v1/buyers with external identity, buy-box criteria, POF metadata and buyer score.
- PUT /api/v1/buyers/{id} for criteria, POF and score updates.

### Buyer Matching
- GET /api/v1/deals/{dealId}/matches
- POST /api/v1/deals/{dealId}/matches to upsert a ranked buyer match.
- POST /api/v1/deals/{dealId}/matches/recalculate to run the existing tenant-aware buyer matching service.
- PUT /api/v1/deals/{dealId}/matches/{matchId} for outreach/response state.

All disposition writes use tenant-scoped API keys and the disposition idempotency namespace where a request can be replayed safely.

## Immediate Next Engineering Task

The CRM-side disposition contract is now in place. Next, import or expose the actual n8n workflow JSON from the disposition repository and wire Deal Intake, Intelligent Distribution, Buyer Onboarding, Buyer Scoring, and Deal Package Delivery to these endpoints. Do not invent workflow file paths when the sanitized reference repository does not expose them.


## AI deal-analysis foundation

InsulaCRM now stores one tenant-scoped analysis record per deal. The analysis layer preserves ARV, repair estimate, purchase price, MAO, projected assignment fee, equity estimate, confidence, score, exit strategy, source/methodology, comps, rehab scope, assumptions, risk flags, and human override state.

The calculation service uses an auditable default formula: MAO = ARV × 70% − repairs − assignment fee target. Missing inputs produce partial analysis rather than fabricated values. Human overrides are explicit and require a reason when used.

### API
- GET /api/v1/deals/{dealId}/analysis
- POST /api/v1/deals/{dealId}/analysis
- PUT /api/v1/deals/{dealId}/analysis

### Next integration step
The acquisition/disposition deal-intake workflow should POST analysis inputs after canonical deal creation. External AI should supply evidence and confidence; InsulaCRM remains responsible for tenant-safe persistence and auditable calculations.
