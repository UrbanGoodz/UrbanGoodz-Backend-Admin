# Dispatch, commission and financial reconciliation certification

Existing coverage re-run and passing: commission resolver, driver compensation, order-commission parity, settlement
snapshots (immutable), split control, financial control. New: `DriverJobLifecycleMoneyTest` (6),
`OrderAnywhereMoneyReconciliationTest` (3).

## Defects fixed
1. **Double payout by reopening a route.** `routes/{id}/started` and `active-jobs/{id}/start` accepted a *completed* route; the
   next completion got a new `route_completed_at`, hence a new settlement key, a new snapshot and a second driver payout plus a
   second completion bonus. Start is now refused (409) for finished routes.
2. **Route completion was not atomic.** Two simultaneous completions both passed the status check. It is now a compare-and-swap.
3. **`active-jobs/{id}/complete` skipped everything for a dedicated route:** no check that packages were delivered, no bonus, no
   settlement. It now runs the same checked, settled completion as the route endpoint.
4. **Drivers could force an Order Anywhere order to `completed`** from any state (never bought, never delivered), and could
   hand back a paid, half-done order into the review queue. Both now go through the state machine (`OrderAnywhereDriverFlow`);
   handing back is only allowed before goods are bought and returns the job to automatic dispatch.
5. **`OrderAnywhereRequest::transitionTo` never saved a `completed` transition when payment was captured** (empty branch), so
   captured requests silently stayed at `delivered`. Now saved.
6. **The driver's delivery pay was never allocated in the automatic flow.** Accepting a dispatch did not record the driver on the
   request, and the driver's split row is only created when a driver is attached; splits are first calculated at quote time.
   Reconciliation checks summary columns, not split rows, so it passed with the driver unpaid. The driver is now attached on
   acceptance (and moved to `shopper_accepted`), the missing split is added before capture, and settlement creates the
   withdrawable earning exactly once.

## Verified
- Money-in = merchant purchase funds + driver pay + dispatcher + Urban Goodz revenue (reconciled to the cent in the new test).
- Settlement snapshots are idempotent per completion and cannot be edited or deleted (existing tests).

## Open
- `findDriverJob` resolves a bare id across four tables in a fixed order (Order Anywhere, courier job, route, load); a driver
  holding an Order Anywhere request and a route with the same numeric id can only reach the first. Needs a source discriminator in the API.
- `reconcileSplits` compares request columns to the captured amount; it should also sum the actual split rows.
- Dispatcher commission on Order Anywhere still requires a dispatcher id and is not attached by the automatic flow.
- Driver pay for Order Anywhere comes from the pricing policy at quote time (before the driver's vehicle is known).
