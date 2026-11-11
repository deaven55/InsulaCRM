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
- GET/POST/PUT deals
- GET/POST/PUT buyers
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
- Add buyer criteria and distribution records if not already sufficient.
- Rank buyers by market, price, property type, strategy, tier and engagement.
- Track dispatch, response, follow-up and package delivery.

### Phase 5 — Unified dashboard
- Use InsulaCRM as the backend.
- Borrow useful BIDS dashboard UX patterns where they improve the existing CRM.
- Build acquisition, deal, disposition and analytics views directly into the tenant-aware application.

## Immediate Next Engineering Task

Implement the integration foundation first. Do not start by importing all n8n workflows blindly. Establish the data contract, idempotency, tenant boundaries, and external-ID mapping before connecting automation.
