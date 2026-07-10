# P5 FUTURE PROVISIONING CHECKLIST
## Urban Goodz — Session 1 / Data Import
**Applies to batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`

A staged sourced business row may be provisioned into a real store/vendor
listing ONLY after EVERY item below is satisfied. This checklist is for the
future provisioning phase (not executed in P5).

### Per-row required gates
- [ ] `admin_review_status = approved`
- [ ] `category_ids` is NOT empty
- [ ] `category_ids` does NOT include `1` (no Demo/fallback)
- [ ] `category_ids` all belong to the row's `module_id`
- [ ] `source_url` is valid (http/https, well-formed)
- [ ] Not flagged `duplicate` / `merge_required`
- [ ] `visibility` is `private` before provisioning
- [ ] `partnered = false` before provisioning (unless separately approved)
- [ ] No live-store conflict (excludes The Breakfast Klub, ChopnBlok, Sweet Georgia Brown, Distant Relatives)
- [ ] Age-restricted rows have **separate compliance approval**
- [ ] No dangerous fulfillment modes (delivery/courier/active/public)
- [ ] Module is **active** OR explicitly PM-approved for activation/provisioning
- [ ] No customer-facing publication until PM approves
- [ ] No store/vendor/product creation until PM approves

### Phase-level required gates (PM + engineering)
- [ ] PM explicitly approves the provisioning phase
- [ ] Provisioning command exists with `--dry-run` (default) and required `--batch-marker`
- [ ] Provisioning command refuses to run if:
  - [ ] batch marker missing or unrecognized
  - [ ] any target row fails a per-row gate above
  - [ ] row count differs from the expected eligible set
- [ ] Rollback / disable plan exists and is documented
- [ ] Any provisioning through `UrbanGoodzIngestionService::publishApprovedListings`
      is gated behind these same eligibility rules (the API
      `UrbanGoodzDiscoveryController` path must enforce them)
- [ ] `view:clear` / `route:clear` run as needed; `route:cache` NEVER run (cPanel conflict)

### Post-provision verification
- [ ] Exact number of stores created matches eligible row count
- [ ] No staged row double-provisioned
- [ ] `partnered` still false unless approved
- [ ] Age-restricted rows remain `review_only` (no delivery/courier/pickup)
- [ ] Rollback SQL/plan verified before go-live
