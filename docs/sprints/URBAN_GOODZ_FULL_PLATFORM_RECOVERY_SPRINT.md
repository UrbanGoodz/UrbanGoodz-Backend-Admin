# Urban Goodz Full Platform Recovery Sprint

The executable backlog is [URBAN_GOODZ_FULL_PLATFORM_RECOVERY_SPRINT.csv](URBAN_GOODZ_FULL_PLATFORM_RECOVERY_SPRINT.csv). It contains 73 bounded tasks with evidence and acceptance criteria.

## Release gate

No controlled deployment is eligible until all P0 tasks that touch the intended release surface are complete, independently reviewed, and supported by reproducible artifacts. “Source exists,” “tests have the same failure count,” and “page object exists” are not release evidence.

## Lanes

| Lane | Objective |
|---|---|
| Security | fail-closed webhooks, no credential retention, safe model mutation |
| Authorization | route/menu/controller permission parity |
| AI Chief of Staff | visible, read-only insight first, classified actions, approvals and audit |
| Financial Control | deterministic money rules, non-mutation by AI, settlement/reversal |
| Database | reproducible installation schema and clean migration chain |
| Testing | meaningful assertions, clean baseline, staging certification |
| Pricing/Payouts | central visibility and quote-to-ledger reconciliation |
| Dispatcher | commission wallet, configuration, Admin-controlled adjustments |
| Mobile | remove fabricated operational data and certify authenticated APIs |
| Load sourcing | attributable ingestion and partner-contract readiness |
| Platform services | notifications, realtime, storage, maps, analytics |
| Release | evidence retention, runbook, backup, exact-SHA rollback |

## P0 execution order

1. Close immediate security and authorization holes: P0-01 through P0-08.
2. Rebuild Chief of Staff as a permissioned read-only command surface: P0-09 through P0-13.
3. Restore reproducible schema/test foundations: P0-14 through P0-18.
4. Complete pricing/payout discovery and central design: P0-19 through P0-21.
5. Implement dispatcher and cross-feature money reconciliation: P0-22 through P0-24.
6. Reconcile the dashboard and production-data boundaries: P0-25 through P0-30.
7. Close mobile credential/identity/realtime and runtime branding risks: P0-31 through P0-34.
8. Prove the exact production deployment, branding, duplicate-copy, and cache identity read-only: P0-35.
9. Certify SMS/OTP and CAPTCHA fail-closed behavior: P0-36 through P0-39.
10. Repair storage selection, private-media authorization, and disaster recovery: P0-40 through P0-42.

P1-18 and P1-19 then migrate media to protected object storage and establish a production-grade Reels processing/delivery pipeline.

## Legacy-foundation rule

Do not globally delete or rename 6amMart identifiers. Core marketplace routes, models, middleware, the module-permission helper, the Tax module project discriminator, and the Shopper `sixam_mart` Dart namespace are compatibility foundations. The dedicated contamination audit distinguishes those from runtime branding, unsafe seeders, fixed credentials, mobile identity, and external-dependency risks.

Parallel work is permitted only where the CSV dependency column allows it. Claude-owned Admin-auth files remain out of scope for competing edits; P0-02 requires coordination with that owner.

## Non-negotiable financial boundary

AI may calculate or explain a proposed price, payout, or margin, but may not directly:

- increment/decrement a wallet;
- mark a payout settled;
- create a final ledger adjustment;
- alter a commission rule;
- capture/refund a payment;
- approve its own proposal.

The authoritative amount must be deterministic from a versioned policy snapshot, explicitly approved where required, idempotently settled, and reversibly audited.

## Required platform equation

Every supported service must expose a reconciled breakdown:

`customer/load price`
`- driver or carrier payout`
`- dispatcher commission`
`- external costs`
`- processing fees`
`= Urban Goodz gross margin`

The central control center must identify the rule version, source inputs, approval state, quote/invoice/ledger identifiers, settlement state, and any adjustment actor.

## Definition of done

- Focused tests include negative authorization and non-mutation cases.
- The full suite runs on a database created solely from committed, reviewed schema.
- Browser tests use explicit non-production base URL and meaningful protected selectors.
- Money workflows reconcile quote, checkout, invoice, provider event, wallet, payout, and ledger.
- No critical test is silently skipped.
- Evidence is redacted, checksummed, and retained in restricted short-lived paths.
- Independent review approves one exact SHA and file list.
- OTP provider acceptance is parsed and actor-specific delivery paths are behaviorally proven.
- CAPTCHA fallback is server-controlled, one-time, rate-limited, and cannot be selected by the client.
- Private uploads are never directly web-addressable; record ownership and download authorization are tested negatively.
- Media and database backups have an isolated restore-drill artifact with declared RPO/RTO.
