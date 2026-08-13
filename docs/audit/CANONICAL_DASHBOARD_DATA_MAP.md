# URBAN GOODZ OPERATIONS COMMAND CENTER

> **STATUS: PRELIMINARY — NOT APPROVED**
>
> This map was derived from the CANDIDATE SCHEMA BASELINE
> (`database/baseline/urbangoodz_candidate_schema.sql`), whose
> **production provenance is UNVERIFIED**. Table and column references below
> have not been confirmed against a fresh read-only production schema export.
>
> Metrics quoted in this document (vendor counts, sales figures) were read from a
> local database copy and are **not** verified production values.
>
> Do not use this document to justify a schema change, a migration, or a
> production dashboard fix until provenance verification is complete.

## Master Data Map

This document defines the proposed canonical source of truth for the Urban Goodz operational command center. Currently, the dashboard mixes legacy 6amMart marketplace logic with fragmented Urban Goodz components.

### 1. Marketplace
- **Canonical Table:** orders
- **Exclusions:** is_guest = 1, order_status = canceled, mock/test records.
- **Defects:** Store::whereHas(vendor) applies a strict module_id filter which causes the dashboard to report 0 stores when the active module in session does not match the stores module ID.

### 2. Business Operations
- **Vendors and Providers:** Must query the vendors table directly without implicitly filtering by module_id.
- **Defects:** 130 vendors exist, but because they lack a linked active store in the currently selected module, they vanish from the store count.

### 3. Dispatch and Logistics
- **Canonical Table:** delivery_histories, orders (where order_type = delivery), delivery_men.
- **Defect:** Available driver logic checks active WebSocket presence or legacy application_status = approved coupled with active = 1, which fails if the new Urban Goodz driver app doesnt sync with legacy heartbeats.

### 4. Courier and Medical
- **Canonical Table:** parcel_categories, orders (where module_type = parcel).
- **Defect:** Missing unified view.

### 5. Order Anywhere
- **Canonical Table:** orders (where order_anywhere = 1).
- **Defect:** Lacks a dedicated dashboard view. Settings route /admin/order-anywhere/settings is missing.

### 6. Payments and Payouts
- **Canonical Table:** account_transactions, order_transactions.
- **Current Metric:** $82 sales. 
- **Defect:** Gross sale logic does not reconcile against provider events or Urban Goodz ledgers. Dynamic AI payouts are mutated directly without a proper commission ledger chain.

### 7. Creators, Reels and Events
- **Canonical Table:** reels, events (if present).
- **Defect:** Upload sources exist but are not integrated into the dashboard.

### 8. AI Chief of Staff & Operations
- **Canonical Table:** ai_operations or similar.
- **Defect:** AI Chief of Staff writes during GET requests, lacks provider logging, and has no dashboard widget.

### 9. Load Sourcing
- **Canonical Table:** Internal jobs / emails.
- **Defect:** Adapters fail closed and lack a centralized command view.

### 10. System Health
- **Defect:** Storage relies on local paths without S3 failover reporting.

