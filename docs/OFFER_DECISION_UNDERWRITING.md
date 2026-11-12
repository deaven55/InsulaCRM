# Offer Decision Underwriting

The canonical CRM now exposes `POST /api/v1/deals/{dealId}/offer-decision/calculate`.

The engine combines the canonical ARV, repair estimate, asking price, transaction-cost inputs, assignment-fee target, ARV confidence, and comparable count.

It returns:
- BUY / NEGOTIATE / PASS
- decision score
- offer low / target / high
- underwriting factors
- explicit assumptions

Core offer math uses the existing 70% ARV wholesale heuristic less repairs, assignment target, and supplied transaction costs. The 70% factor is explicitly an internal underwriting heuristic and is not presented as an appraisal standard.

No decision is produced from missing ARV or repair inputs. Human review remains required before a seller-facing offer is made.

North Carolina appraisal rules recognize the sales-comparison approach as one valuation approach, but this system is an internal wholesale underwriting tool rather than a licensed appraisal.