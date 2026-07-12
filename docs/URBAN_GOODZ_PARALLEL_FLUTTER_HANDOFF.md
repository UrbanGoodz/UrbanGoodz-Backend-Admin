# Urban Goodz Parallel Flutter Handoff

This document details the backend contract, API schemas, and integration points for the parallel Flutter development track.

---

## 1. Environment & Auth

- **Branch:** `adminpanel-v39-backend-sprint`
- **Starting Commit SHA:** `e92164d4161972f98672e36cbc623c67481724a9`
- **Previous SHA:** `58b983617becc4a3cce8c232c918a59ea3e414c4`
- **Base URL:** `http://localhost/api/v1` or `https://admin.urbangoodzdelivery.com/api/v1`
- **Authentication Headers:**
  ```http
  Authorization: Bearer <auth_token>
  Accept: application/json
  ```

> [!IMPORTANT]
> **Middleware Change (v39 Payments/AI Sprint):** All driver API routes under `/api/v1/urban-goodz/driver/*` now use the `dm.api` middleware instead of `auth:delivery_man`. The `dm.api` middleware authenticates via `?token=` query parameter or `Authorization: Bearer` header. Flutter driver app must ensure authentication tokens are sent correctly.

---

## 2. API Endpoints

### 2.1 Load Board (Driver / Vendor / Customer Integration)
- **List Available Loads:**
  - `GET /api/v1/opportunity/loads`
  - **Filters:** `origin_state`, `destination_state`, `load_type`, `equipment_type`, `min_payout`
  - **Response (200 OK):**
    ```json
    {
      "loads": [
        {
          "id": 1,
          "load_number": "LN-98402",
          "status": "available",
          "origin_name": "North Warehouse",
          "origin_city": "Houston",
          "origin_state": "TX",
          "destination_name": "Dallas hub",
          "destination_city": "Dallas",
          "destination_state": "TX",
          "distance_miles": 240.0,
          "payout_amount": "250.00",
          "load_type": "Same-Day",
          "equipment_type": "Cargo Van"
        }
      ]
    }
    ```

- **Accept Load (Driver Assignment):**
  - `POST /api/v1/opportunity/loads/{id}/accept`
  - **Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Load accepted successfully",
      "load": { "id": 1, "status": "assigned", "assigned_driver_id": 12 }
    }
    ```

- **Update Load Status:**
  - `POST /api/v1/opportunity/loads/{id}/status`
  - **Body parameters:** `status` (must be `in_transit`, `picked_up`, `delivered`, `cancelled`)
  - **Response (200 OK):**
    ```json
    {
      "success": true,
      "load": { "id": 1, "status": "in_transit" }
    }
    ```

---

### 2.2 Independent Dispatcher APIs
- **Redirection Rule:** Logged-in client users belonging to a dispatch company and having a dispatcher role are redirected automatically to `/business/dispatcher/dashboard` on Web.
- **Roles:** `dispatch_owner`, `dispatch_manager`, `dispatcher`, `dispatch_readonly`, `dispatch_finance`.
- **Permissions:** 
  - `dispatch_loads_view`, `dispatch_loads_assign`, `dispatch_drivers_view`, `dispatch_drivers_assign`, `dispatch_status_update`, `dispatch_commissions_view`.

---

### 2.3 Fashion Fit APIs (Measurement Profiles & Stylist Requests)
- **Get Customer Profile:**
  - `GET /api/v1/urban-goodz/fashion/measurements/profile`
  - **Response (200 OK):**
    ```json
    {
      "success": true,
      "data": {
        "id": 5,
        "customer_id": 18,
        "height": "70.00",
        "chest_bust": "40.00",
        "waist": "34.00",
        "hips": "42.00",
        "preferred_fit": "Slim"
      }
    }
    ```

- **Create Stylist Request:**
  - `POST /api/v1/urban-goodz/fashion/measurements/request`
  - **Body parameters:**
    ```json
    {
      "item_wanted": "Custom Wedding Suit",
      "request_type": "Custom Garment",
      "budget": 500.00,
      "notes": "Prefer dark blue wool fabrics."
    }
    ```
  - **Response (201 Created):**
    ```json
    {
      "success": true,
      "message": "Stylist Request submitted successfully.",
      "data": { "id": 12, "status": "Pending Stylist Review" }
    }
    ```

- **Upload Photos:**
  - `POST /api/v1/urban-goodz/fashion/measurements/photos`
  - **Body parameters:** `measurement_request_id`, `front_photo`, `side_photo`, `back_photo`
  - **Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Tester photo placeholders attached. Production storage and face blur are not claimed."
    }
    ```

---

## 3. Payment Safety & Mode
- **Current Mode:** **Sandbox/Test** is active by default.
- **Gateway Configuration:** Enforced via `config/urban_goodz_payments.php`.
- **Driver Card Controls:** Card spending has a 10% safety buffer and a hard limit configured per driver. Webhook listener signatures are fully verified.

> [!IMPORTANT]
> **Payments/AI Sprint Changes (July 2026):**
>
> **Payout Balance Enforcement:** The `POST /api/v1/urban-goodz/driver/payout-request` endpoint now validates the **available** earning balance. It deducts all pending/approved/processing payouts from the driver's pending earnings before checking if the requested amount is allowed. The error response now includes `available_earnings` in addition to `pending_earnings`:
> ```json
> {
>   "error": "Requested amount exceeds pending earnings",
>   "pending_earnings": 10.00,
>   "available_earnings": 5.00
> }
> ```
>
> **Idempotent Settlement:** `settleSplits()` now runs automatically on `completed`, `cancelled`, or `failed` status transitions. Calling it multiple times is safe — splits already in `released` status are skipped, preventing double-credit to wallets.
>
> **Refund-Aware Splits:** If a refund occurs before settlement, the vendor's released amount is reduced by the refunded amount. If a refund occurs after settlement, the vendor wallet is debited.
>
> **Staged Test Gateway Hardened:** `StagedTestPaymentGateway` is now **disabled** in `production` environments and when payment mode is `live` or `live_controlled`. It only activates in `sandbox`/`test` modes in non-production environments.
>
> **Webhook Idempotency:** Duplicate webhook events (same event type + provider reference for the same request) are now silently skipped via ledger-based deduplication.

---

## 4. Notifications Expectations
- **Queue/Channel:** Firebase Cloud Messaging (FCM) is configured for push notifications.
- **Key Triggers:**
  - `load_assigned` -> Driver push notification.
  - `load_status_changed` -> Customer and Vendor updates.
  - `stylist_bid_received` -> Customer push notification.

---

## 5. Change Log

### Payments/AI Sprint Integration — July 12, 2026

| Area | Change | Flutter Impact |
|------|--------|----------------|
| **Driver Routes** | Middleware changed from `auth:delivery_man` to `dm.api` | Ensure `?token=` or `Authorization: Bearer` is sent |
| **Payout Request** | Now validates available balance (pending earnings minus pending payouts) | Handle new `available_earnings` field in 400 response |
| **Payment Settlement** | `settleSplits()` auto-triggered on status transitions | No direct Flutter impact — backend-only |
| **Refund Behavior** | Refunds adjust vendor wallet before or after settlement | No direct Flutter impact — backend-only |
| **Webhook Route** | Now accepts `staged_test` as a provider | No Flutter impact — webhook-only |
| **AI Copilot** | Uses `config('dm_maximum_orders')` instead of DB column | No Flutter impact — backend-only |
| **Load Board** | Null/empty `external_id` no longer causes false duplicate detection | No Flutter impact — backend-only |
