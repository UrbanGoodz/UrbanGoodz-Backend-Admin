# OTR (load board) and medical courier certification — status: PARTIAL

Covered: the driver and "opportunity" APIs for loads and medical courier jobs, load and job lifecycle, driver
eligibility, atomicity of assignment and payout, and the custody trail on medical routes and jobs. **Not covered:** any
device run, the admin screens for loads/jobs beyond their service layer, external load-source ingestion (TrukTek etc.),
freight document capture, real payout settlement, road distances, and anything needing a staging database with real data.

## Defects found and fixed
| ID | Sev | Defect | Fix | Evidence |
|---|---|---|---|---|
| O1 | **P0** | **Medical courier job API was open to any logged-in customer account.** A customer could accept a specimen job as *any* driver (`driver_id` came from the request body), move any job to `delivered` (which credits the assigned driver's wallet), write chain-of-custody entries with any handler name, and read every job including facility contacts and admin notes | routes now require the driver token; the acting driver is always the token's driver; only approved, active, medical-trained drivers; only the assigned driver can change status or write custody; a driver cannot cancel; admin notes never returned; pending jobs show a limited projection | `OtrAndMedicalCourierTest` (19 of 20 fail on the old code) |
| O2 | **P0** | **OTR load-board API was open to any customer account, and used the customer's user id as the driver id**: customer #7 accepting a load assigned it to whichever driver had id 7. Any customer could set any load to delivered/cancelled. The detail/list returned raw models: customer price, platform margin, source cost, fees and the eager-loaded driver, business client and dispatcher | driver-only; identity from the token; status endpoint limited to the assigned driver and to in_transit/picked_up/delivered; driver-safe projection | same |
| O3 | P1 | The driver app's `GET load-board` returned raw load models to every driver: **platform margin, customer price, source cost, shipper/consignee names and phone numbers** | whitelist projection (`driverFacing`); contacts only once the load is assigned to that driver | same |
| O4 | **P1** | **Driver bidding was a false success.** `POST load-board/{id}/bid` wrote a `bids` attribute the table does not have and answered "Bid submitted successfully" while saving nothing; the real bid table, duplicate check and eligibility were bypassed | uses the service's `placeBid`; amount must be > 0; duplicate -> 409 | `a_bid_is_actually_saved...` |
| O5 | **P1** | **Hazmat loads could be taken by drivers with no hazmat endorsement** (accept had no eligibility check). The service's own eligibility query also crashed on every hazmat load: it filtered on `has_hazmat_endorsement`, a column that does not exist (`has_hazmat`) | hazmat enforced on every assignment path, including dispatcher assignment; load-board vetting and declared-equipment match enforced for self-service; column corrected | same |
| O6 | **P1** | A driver could **reopen a delivered load**: cancel set any load back to `available` from any state. Start/complete had no state rules. Accept wrote status `accepted`, which is not in the load state machine, so dispatch could neither progress nor cancel that load | driver release only from `assigned`; start only from assigned/exception; deliver only from in_transit/picked_up; accept uses the service (`assigned`, audit event, `current_orders`) | same |
| O7 | **P1** | **Race conditions: when 8 drivers hit the same load or specimen job at the same instant, all 8 won**, and 8 concurrent "delivered"/"completed" calls each paid the driver. (`read, check, then write` with no atomic step) | every claim and transition is a single compare-and-swap `UPDATE ... WHERE status = <expected>`; payout happens only after the claim succeeds | `ConcurrentAssignmentTest` runs 8 real OS processes: old code 4 of 4 tests fail with `WIN x8`; fixed code exactly one winner and one payout |
| O8 | **P1** | **Chain-of-custody records were partly fabricated.** Route-package pickup and drop-off always wrote `seal_intact = true`, whatever happened; job status changes were logged under "System/admin" rather than the driver; custody handler name came from the request | seal/temperature are accepted from the driver (new optional fields) and recorded as given or as NULL ("not attested"), never assumed; job custody is attributed to the authenticated driver | `MedicalRouteCustodyTest`, `OtrAndMedicalCourierTest` |

## Not implemented or not decided (important)
1. **Recurring routes do not exist.** `recurring_rule` is a free-text field that is saved and never read. There is no scheduler, job or
   generator, and the scheduler runs only four commands. The business portal says "Routes can be one-time or recurring", yet a "weekly" medical
   route runs **once**. This is the core of the dedicated recurring medical courier product. Needs a product decision (rule format,
   how packages/drivers are assigned per run, medical eligibility) and then an implementation.
2. **Medical delivery needs no proof.** A specimen job can move to `delivered` (and pay the driver) with no recipient signature, receiver name or
   temperature log. On route packages a signature is required only if `requires_signature` is set separately from `requires_custody`.
   I did not force it because it could stop in-flight medical deliveries; recommend `requires_custody` implies a signature.
3. **Customer app screens for Medical Courier and Logistics now get 401** (they used customer tokens). They were not working correctly before
   (accepting a medical job always answered "driver_id is required"). These tasks belong in the driver app, which currently has **no medical
   job or load-acceptance UI** (only capability flags); today jobs can only be assigned by an admin. Remove those screens from the customer app or
   build them in the driver app.
4. Load completion pays only when an admin moves a load `delivered -> completed`. A driver finishing a load does not trigger payment, and no
   delivery proof (the `delivery_proof` column) is required.
5. Two parallel custody tables exist (`urban_goodz_medical_courier_custody_logs` for jobs, `urban_goodz_medical_custody_logs` for route packages);
   an auditor has to read both.
6. 2-opt/Or-opt ignore time windows (see the logistics doc); distances are straight-line unless the routing provider is configured.

## Verified to work
Driver-accepted flows through assigned -> in_transit -> delivered with audit events (the admin assignment screens were not exercised); only the assigned driver can act; the
state machines reject skipped and repeated steps; medical payout is created exactly once; replayed package scans do not duplicate custody entries.
