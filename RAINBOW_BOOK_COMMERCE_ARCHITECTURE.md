# Rainbow AI Book Commerce — Production Architecture

Status: DESIGN / CODED-DOCUMENTATION ONLY. This document does not enable publishing, payments, database changes, DNS changes, ad spend, or outbound messaging.

## Goal

One command should be able to move a digital book from idea to a verified publication candidate while preserving strong approval and rollback gates:

`Prompt → Plan → Manuscript → QA → Cover/Layout → PDF → Product Draft → Payment Wiring Check → Delivery Wiring Check → Publish Approval → Public URL → Verification Report`

The default policy is **draft-first**. Production publishing, payment mutations, credentials, DNS, destructive changes, bulk messaging, and ad spend remain approval-gated.

## Core state machine

Every book receives a stable `book_id` and advances through explicit states. State changes are append-only audit events.

1. `requested`
2. `planned`
3. `manuscript_ready`
4. `qa_passed`
5. `assets_ready`
6. `pdf_ready`
7. `product_draft_ready`
8. `commerce_preflight_passed`
9. `awaiting_publish_approval`
10. `published`
11. `production_verified`

Failure states:

- `qa_failed`
- `preflight_failed`
- `blocked_for_approval`
- `publish_failed`
- `delivery_failed`
- `rolled_back`

A failed state never automatically skips forward. Retry creates a new execution record and preserves the original evidence.

## Agent boundaries

### Book Orchestrator
Creates the work graph, assigns immutable IDs, tracks state, stores evidence, and enforces policy. It never handles raw payment secrets.

### Content Writer
Produces the manuscript from the approved specification. Output must be original and versioned.

### Editor / Proofreader
Checks grammar, clarity, answer keys, age/level fit, factual consistency, cultural fit, duplication signals, and prohibited claims.

### Curriculum Designer
Validates learning objectives, exercises, sequencing, answer keys, assessments, and instructional completeness for education products.

### Cover / Creative Planner
Produces cover specification, title hierarchy, visual direction, pricing placement, legal/copyright placeholders, and thumbnail requirements.

### Layout / Publishing Specialist
Produces deterministic page map, PDF preflight checklist, metadata package, preview pages, and product-page payload.

### Commerce Preflight Agent
Validates that product price, currency, SKU/book ID, checkout configuration, payment webhook endpoint, delivery policy, refund link, and protected asset references are internally consistent. It does not create or rotate secrets.

### Release Verifier
After an approved publication, verifies canonical URL, HTTP status, title/meta/schema, mobile rendering, checkout visibility, asset protection, and audit trail. Payment end-to-end verification must use an approved safe test mechanism before marking VERIFIED.

## Data model

### Book record

- `book_id`
- `project_id`
- `title`
- `slug`
- `category`
- `language`
- `audience`
- `price_minor`
- `currency`
- `status`
- `source_version`
- `pdf_sha256`
- `cover_sha256`
- `product_url`
- `created_at`
- `updated_at`

### Execution event

- `execution_id`
- `book_id`
- `agent`
- `action`
- `input_version`
- `output_version`
- `status`
- `evidence`
- `started_at`
- `finished_at`
- `error_code`

### Publication event

- `publication_id`
- `book_id`
- `target`
- `draft_id`
- `canonical_url`
- `previous_version`
- `published_version`
- `approval_reference`
- `published_at`
- `rollback_reference`

## Artifact integrity

Source manuscript, cover asset, and final PDF are separate immutable artifacts. Final artifacts receive SHA-256 checksums. Product records store only references and checksums, not duplicated binary data.

Paid PDF storage remains private. Public product pages and preview assets must never expose the protected paid-file storage path.

## Product-page contract

Rainbow prepares a draft payload containing:

- canonical slug
- title/subtitle
- short and long description
- category/tags
- cover image reference
- preview reference
- price and currency
- SKU/book ID
- SEO title/description
- structured-data fields
- purchase CTA
- refund/terms references

Draft creation can be automated. Production publication remains policy-gated until the release gate is explicitly enabled for that target.

## Payment architecture

Razorpay integration must be server-verified. Browser success state alone never grants access.

Required flow:

1. Backend creates or references an order.
2. Customer completes checkout.
3. Backend verifies provider signature and/or authoritative webhook event.
4. Handler is idempotent by provider event/payment/order ID.
5. Verified order is mapped to `book_id` and buyer identity.
6. Entitlement is created only after successful verification.
7. Delivery endpoint checks entitlement before returning a short-lived asset link or streaming response.

No payment secret, webhook secret, or API key is stored in repository files, logs, Sheets, or client JavaScript.

## Protected delivery

Delivery must use an entitlement check plus one of these server-side patterns:

- short-lived signed URL to private storage, or
- authenticated controlled streaming endpoint.

Each download event records order ID, entitlement ID, book ID, timestamp, result, and non-sensitive request metadata. Public pages never reveal private filesystem paths.

If the business rule is single-use download, enforce it as an entitlement policy with a recovery/admin path rather than deleting the asset.

## Idempotency

These actions require deterministic idempotency keys:

- book creation request
- product draft creation
- payment webhook processing
- entitlement creation
- publish action
- delivery grant

Retries must not create duplicate products, duplicate entitlements, or multiple publication records for the same intended version.

## Approval gates

Automatic internal generation is allowed. The following remain explicit gates unless a future policy narrowly pre-authorizes a specific reversible action:

- production publish/unpublish
- payment credential/config changes
- refunds/transfers
- DNS changes
- database destructive mutations
- bulk WhatsApp/outbound messaging
- ad spend or budget changes
- secret rotation

## QA gate

Minimum book QA evidence before `qa_passed`:

- manuscript completeness
- title/content consistency
- chapter numbering/TOC consistency
- duplicate-section scan
- exercise/answer-key consistency
- spelling/grammar pass
- factual-risk review where applicable
- age/level suitability
- prohibited/unsupported claim check
- price/edition metadata check

A QA score may be used for prioritization, but publication eligibility is rule-based: any blocking defect fails the gate regardless of score.

## Commerce preflight gate

Before publication candidate status:

- product slug unique
- price/currency valid
- protected asset checksum present
- no protected asset public URL
- checkout target configured
- payment callback/webhook route configured
- refund/terms URLs available
- product page has mobile/desktop acceptance checklist
- rollback target identified
- canonical URL reserved or deterministically derivable

## Rollback

Every publish operation stores the previous product/page version before mutation. Rollback must be possible without deleting orders, entitlements, audit records, or source artifacts.

Rollback actions can restore page/product content and disable new sales while preserving historical commerce evidence.

## Monitoring

Track at minimum:

- queue depth
- job age
- failed/blocked jobs
- QA failure reason distribution
- publish failures
- webhook verification failures
- entitlement creation failures
- delivery failures
- checkout-to-payment conversion
- payment-to-delivery success
- product URL health

Sensitive payloads and secrets must be redacted from logs.

## One-prompt orchestration contract

Example command:

`Create a 90-page original beginner English speaking practice book, price ₹299, run QA, create cover/PDF/product draft, run commerce preflight, and prepare it for publication.`

Expected safe result before production approval:

- stable `book_id`
- manuscript version
- QA report
- cover/layout specification
- PDF artifact reference + checksum
- product draft payload
- commerce preflight report
- list of any blockers
- explicit `awaiting_publish_approval` state when publish would change production

After separately authorized publication, Rainbow may return the canonical public URL only after the URL and product page have been verified.

## Definition of done

### CODED
Implementation exists on a non-production branch.

### TESTED
Automated and controlled smoke/regression tests pass, including duplicate/retry and approval-blocking paths.

### MERGED
Reviewed changes are in the production branch.

### DEPLOYED
The production environment is running that merged version.

### VERIFIED
A real production-safe user journey has been exercised successfully, with evidence for product page, payment verification path where applicable, protected delivery, logs, and rollback readiness.
