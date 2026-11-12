# Comparable Sales + ARV Intelligence

The canonical CRM now supports provenance-backed comparable sales and deterministic ARV calculation.

## API

- GET `/api/v1/deals/{dealId}/comps`
- POST `/api/v1/deals/{dealId}/comps/recalculate`

The POST endpoint accepts closed-sale comps with explicit sale price/date and source provenance. It persists tenant-scoped comps and recalculates the deal analysis when at least three sufficiently comparable sales are available.

## ARV methodology

The calculation:
1. excludes comps without explicit sale price or sale date;
2. ranks by proximity, size, beds/baths, year built, property type, recency, and source confidence;
3. excludes similarity scores below 40;
4. selects up to eight strongest comps;
5. combines weighted mean, weighted median, and trimmed mean;
6. produces ARV low/base/high and confidence;
7. writes the ARV and comp summary into the canonical deal analysis.

The service does not fabricate comparable sales or missing property facts.

## Provenance

Comparable sales support:
- external identity;
- source record ID;
- source URL;
- observed timestamp;
- source confidence;
- normalized property attributes;
- similarity score.

The endpoint supports the existing tenant-scoped Idempotency-Key mechanism.

## Important limitation

The ARV is an evidence-backed wholesale analysis estimate, not an appraisal or guaranteed market value. Source quality and availability vary by jurisdiction.
