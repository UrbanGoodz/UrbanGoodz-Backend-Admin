# STRANDED certification

Scope audited: customer request lifecycle, fee payment, offer selection, escrow, tracking, messages,
rating/report, ownership. Existing 75 Stranded tests pass; 5 added (`StrandedMoneyFlowTest`).

## Defects fixed
1. **Double charge / charged-but-unpaid on retry (P0, money).** The ledger idempotency key was fixed per request
   (`stranded_fee_<id>`, UNIQUE). A declined card followed by a good card charged Stripe, then crashed on the
   ledger insert: customer charged, request still unpaid, SQL error shown to the user. Now: one key per attempt,
   sent to Stripe as `Idempotency-Key`; ledger failure after a successful charge is logged and can no longer undo `paid`.
2. **Concurrent fee submission.** The `paid` check was a plain read. The fee is now claimed with an atomic
   `unpaid -> processing` update; a failed or errored attempt reopens it.
3. **Helper never paid.** The customer app closes a request with `completed`, which was terminal and never released
   escrow (only `confirmed` did, and the app never sends it). Closing now releases escrow (idempotent, ledgered).
4. **Double close.** `isTerminal()` read-then-write let a double tap count the job twice. Closing is now a
   compare-and-swap.

## Added (UX / conversion)
- `GET stranded/requests/active` returns the unfinished request and a `next_step`
  (`pay_fee`, `broadcast`, `choose_helper`, `track_helper`, `finish_and_rate`) so a customer whose app closed
  mid-rescue resumes, including paying the fee that sends the request out. `GET stranded/requests` lists recent ones.
  Customer app: `StrandedApiService.getActiveRequest()`; screen wiring still to do.

## Not verified / owner decisions
- **Reward escrow is bookkeeping only.** `selectOffer` marks escrow `held` and `releaseEscrow` writes a ledger row
  `internal_status=completed`, but no code authorizes, captures or transfers money at Stripe. Paid-helper payouts
  are therefore not actually moved. Needs a product/payments decision (Stripe Connect or manual settlement).
- Real Stripe was not called; the provider seam was scripted in tests.
- Admin fee/radius settings and live pages are covered by existing tests; no device run was possible here.
