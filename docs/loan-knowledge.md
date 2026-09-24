# Loan policy evidence

The existing loan executor receives a curated, product-scoped evidence packet and appends source provenance to its output. There is no live website fetch, background refresh, new service, or API charge beyond the existing executor.

Initial coverage: SBI Personal Loan, PMMY, and the PMEGP new-enterprise route. These are partial public-policy summaries, not complete bank policies. Other products, individual pricing, internal credit rules and application availability are unknown. The reviewed sources were accessed on 2026-09-24. Source update dates are not policy effective dates.

## Updating evidence

1. Read the precise official product page and relevant current circulars. Check scope, exceptions, effective dates and conflicts. Do not treat a search snippet or prior model output as a review.
2. Edit data/loan-policies.json through code review. Keep summaries concise, preserve qualifying conditions and explicitly list unsupported topics. Use null for an unknown effective date. Never add applicant data or secrets.
3. Only change checked_on after actually re-reading the source. The seven-day review interval is an internal freshness limit, not proof a policy stayed unchanged. Expired records keep their source metadata but their facts are excluded from the model evidence packet.
4. New source IDs/URLs require a corresponding exact allowlist entry in rainbow_loan_snapshot_status and regression coverage. Unknown, future-dated or malformed records are rejected.
5. Run both loan test suites plus existing Rainbow and WhatsApp tests. Evaluate actual AI answers for invented terms and correct scope before deployment. Prompt grounding is not a mathematical guarantee of answer correctness.

## Known release gaps

No complete bank catalog, private underwriting policies, scheduled refresh, authenticated end-to-end AI evaluation or production verification. No government-application submission or loan approval capability. A reviewed snapshot must never be labelled live/current verification. Main remains unchanged while this work is in draft PR 13.
