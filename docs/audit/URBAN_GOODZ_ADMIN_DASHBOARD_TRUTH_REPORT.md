# Urban Goodz Admin Dashboard Truth Report

Evidence basis:

- Source at `6937e5d5c8d7bbf0c6405eda8a6c1d0e922033c9`.
- Three user-supplied production screenshots captured July 23, 2026.
- No production database or logs were accessed.

## Live visual evidence

The screenshots visibly show:

- “Legacy Marketplace Metrics”
- 1,116 catalog items
- 9 marketplace orders
- 130 “Vendors / Providers”
- 32 customers
- $82.00 gross sale for 2026
- user statistics of 32 customers, 0 stores, and 3 delivery men
- “Demo Product” sold 2
- “Carrot Imported” sold 1
- no popular stores
- a Top Selling Stores tile with an image placeholder and no visible store identity

These observations prove what the page displayed. They do not prove the underlying rows are legitimate production data.

## Confirmed source causes

### 130 versus 0 stores

The 130 card is labeled “Vendors / Providers” but renders `data.total_stores`. In settings/all-module context, `order_stats_calc()` counts all stores whose related vendor has `status=1`.

The donut’s store count always applies `where('module_id', $module_id)`. When the settings context has no numeric module, this becomes a null-module filter. Therefore the two widgets do not use the same population and can legitimately render 130 and 0 from inconsistent queries.

Verdict: **confirmed dashboard defect**. It is not proof that there are literally 130 valid production vendors/providers and zero stores.

### 1,116 catalog items with no active stores

The catalog count uses `Item::where('is_approved', 1)` in all-module mode and does not require an existing/active store relation. The donut’s zero is a differently filtered store count.

Verdict: **confirmed metric-integrity defect**; **orphan count unproven**.

### Demo Product and Carrot Imported

The all-module top-selling query selects items with `order_count > 0`. It requires a store only when a numeric module or zone filter is applied. It does not filter `is_approved`, production provenance, demo/seed/import status, active store, or active vendor in the all-context branch.

Verdict:

- Demo Product in a production ranking: **visually confirmed and unacceptable without an explicit production-demo policy**.
- Carrot Imported store ownership: **not proven**.
- Query allows an item without a valid store to rank: **confirmed source defect**.

### $82 gross sale

The chart sums `order_transactions.order_amount` for `NotRefunded()` rows. It does not reconcile that sum in the dashboard query to:

- payment-provider events;
- `urban_goodz_payment_ledgers`;
- the nine displayed orders;
- valid customer identity;
- active vendor/store ownership;
- assigned driver;
- payout/commission/settlement state.

Verdict: the screenshot proves the displayed sum, not earned, captured, settled, or reconciled revenue.

### Top and popular stores

Top Selling Stores queries stores with active vendors and `order_count > 0`. The screenshot shows a tile without visible identity. That may be a missing logo/name presentation or a bad row; the screenshot alone cannot distinguish them.

Most Popular Stores is wishlist-based, not sales-based. “No Stores Available” means no qualifying wishlist aggregation under the active filters, not necessarily that the store table is empty.

## Newer Urban Goodz feature visibility

The source does contain two sections above the legacy block:

- Urban Goodz Command Center
- Urban Goodz Revenue Command Center

They link to business clients, routes, load board, load sourcing, pricing, payments, payouts, Order Anywhere, AI Concierge, creator commerce, services, rentals, Fashion Fit, logistics, medical courier, events, and other surfaces.

However:

- they are long grids of similarly weighted cards, not a prioritized operations dashboard;
- most values are raw row counts rather than operational KPIs;
- revenue is only capture-ledger sum, not full gross-margin reconciliation;
- `driver_pricing_count`, `service_requests_count`, and `notifications_count` are referenced by the Blade but never returned by `urban_goodz_dashboard_data()`, so they silently display zero;
- no data-quality/provenance status is visible;
- the legacy marketplace block remains large and visually dominant.

Verdict: newer features are present in source, but the dashboard is not a trustworthy, modern Urban Goodz operational command surface.

## Competing dashboard systems

Urban Goodz currently exposes two competing Admin concepts:

1. The inherited `/admin` dashboard remains the primary landing experience and is dominated by legacy marketplace cards and queries.
2. New Urban Goodz operations are distributed across Command Center/Revenue partials, dedicated routes, JSON endpoints, settings pages, AI pages, pricing/payout screens, dispatch/load-sourcing pages, and Business Portal workspaces.

They do not share one declared metric registry, population/scope policy, money reconciliation service, data-provenance rule, or authoritative workflow state. As a result, a similarly named count or amount can be computed from different tables and filters.

There is no single canonical control center for pricing, payments, payouts, dispatch, AI recommendations/approvals, operational health, and financial reconciliation.

**Decision:** consolidate navigation into one role-aware Urban Goodz Operations Command Center. Move inherited marketplace reporting into a clearly identified Marketplace module when it still serves an active business purpose. Establish one owner, query, scope, freshness rule, reconciliation rule, and drill-through target for each metric before removing or replacing any existing card.

## Purpose decision

The legacy marketplace analytics can serve a purpose only if Urban Goodz still operates that store/item/order marketplace and a named role needs those KPIs. If so, it should move to a separate “Legacy Marketplace Analytics” route with consistent module/zone filters and reconciled data.

If that marketplace is no longer an active business line, the block should be retired after evidence-preserving export. It should not remain on the primary dashboard merely because legacy source exists.

## Recommended premium dashboard

The primary Admin landing page should be role-aware and exception-first:

1. **Executive strip:** captured revenue, pending settlement, gross margin, refunds, outstanding payouts, data-quality exceptions.
2. **Operations:** active routes/loads/orders, unassigned work, late-risk work, failed scans, returns, service-level breaches.
3. **Money control:** customer price, driver/carrier payout, dispatcher commission, external costs, fees, margin, reconciliation state.
4. **People and partners:** approved/active business clients, vendors, drivers, dispatchers, providers—not mixed labels.
5. **AI Chief of Staff:** read-only brief, recommendations, approvals awaiting a human, prohibited-action status.
6. **Feature health:** Order Anywhere, load sourcing, medical courier, services, rentals, creators, Fashion Fit, notifications, workers.
7. **Data trust:** production/demo/import provenance, orphan counts, stale records, unmatched payments, last reconciliation.
8. **Drill-through:** every KPI opens the exact filtered records that produced it.

The modern design is P0-29 in the recovery sprint. P0-25 establishes the authoritative metric/query registry; P1-13 separates or retires the legacy module; P1-14 replaces misleading cards. Implementation must follow metric reconciliation and data provenance work; visual polish cannot make inconsistent data trustworthy.
