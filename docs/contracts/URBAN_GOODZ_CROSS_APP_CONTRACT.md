# URBAN GOODZ — CROSS-APP MASTER CONTRACT

**Version:** 3.9  
**Status:** Production  
**Last Updated:** 2026-07-16  
**Scope:** All shared workflows across Customer, Vendor, Driver, and Admin/Dispatcher apps  

---

## 1. DOCUMENT PURPOSE

This is the **single source of truth** for every shared entity, workflow, status transition, notification, payment effect, audit-log entry, UI state, error behavior, retry policy, and ownership rule that crosses app boundaries in the Urban Goodz platform.

Every Flutter app (Customer, Vendor, Driver) and the Laravel Admin Panel must conform to this contract. Any deviation must be documented as a blocker in `CROSS_APP_ENDPOINT_MATRIX.md`.

---

## 2. CANONICAL STATUS ENUMS

### 2.1 MARKETPLACE ORDER STATUS

| Value | Description |
|---|---|
| `pending` | Order placed, awaiting vendor acknowledgment |
| `confirmed` | Vendor has confirmed order details |
| `vendor_accepted` | Vendor accepted the order for preparation |
| `vendor_rejected` | Vendor rejected the order |
| `preparing` | Vendor is actively preparing the order |
| `ready_for_pickup` | Order is packed and ready for driver pickup |
| `driver_assigned` | A driver has been assigned to the order |
| `driver_accepted` | Driver accepted the assignment |
| `driver_arrived_pickup` | Driver arrived at vendor location |
| `picked_up` | Driver picked up the order |
| `in_transit` | Order is en route to customer |
| `driver_arrived_dropoff` | Driver arrived at customer location |
| `delivered` | Order delivered to customer |
| `completed` | Customer confirmed or auto-confirmed delivery |
| `cancelled` | Order cancelled by any allowed actor |
| `failed` | Delivery failed (wrong address, customer unavailable) |
| `returned` | Customer returned the order |
| `refunded` | Refund processed after return/cancellation |

### 2.2 COURIER / PARCEL STATUS

| Value | Description |
|---|---|
| `draft` | Parcel created, not yet submitted |
| `quoted` | Price quote generated for parcel |
| `awaiting_payment` | Customer must pay the quote |
| `paid` | Payment received, parcel is active |
| `available` | Parcel available for driver assignment |
| `assigned` | Driver assigned to parcel |
| `accepted` | Driver accepted parcel job |
| `arrived_pickup` | Driver arrived at parcel pickup |
| `picked_up` | Parcel picked up by driver |
| `in_transit` | Parcel in transit |
| `arrived_dropoff` | Driver arrived at dropoff |
| `delivered` | Parcel delivered |
| `completed` | Delivery confirmed, closed |
| `failed` | Delivery failed |
| `return_required` | Return to sender required |
| `returned` | Parcel returned to sender |
| `cancelled` | Parcel cancelled |

### 2.3 LOGISTICS LOAD STATUS

| Value | Description |
|---|---|
| `draft` | Load created by shipper/dispatcher |
| `sourced` | Load sourced from external board or manual entry |
| `recommended` | AI recommended this load to a driver |
| `dispatcher_review` | Under dispatcher review before approval |
| `approved` | Dispatcher approved the load |
| `available` | Load available for driver bidding/assignment |
| `assigned` | Driver assigned by dispatcher or system |
| `accepted` | Driver accepted the load |
| `en_route_pickup` | Driver en route to pickup location |
| `arrived_pickup` | Driver arrived at pickup |
| `loaded` | Freight loaded onto vehicle |
| `in_transit` | Load in transit |
| `arrived_delivery` | Driver arrived at delivery location |
| `unloaded` | Freight unloaded |
| `pod_submitted` | Proof of delivery submitted |
| `completed` | Load fully completed and closed |
| `exception` | Exception encountered (damage, delay, refusal) |
| `cancelled` | Load cancelled |
| `rejected` | Load rejected by driver or dispatcher |

### 2.4 PAYMENT STATUS

| Value | Description |
|---|---|
| `pending` | Payment initiated, not yet processed |
| `requires_action` | Additional customer action needed (3DS, redirect) |
| `authorized` | Payment authorized by gateway, funds reserved |
| `captured` | Payment captured, funds transferred |
| `partially_refunded` | Partial refund issued |
| `refunded` | Full refund issued |
| `failed` | Payment failed at gateway |
| `cancelled` | Payment cancelled before capture |
| `disputed` | Customer filed a chargeback/dispute |

### 2.5 PAYOUT STATUS

| Value | Description |
|---|---|
| `pending` | Payout requested, awaiting admin approval |
| `approved` | Admin approved the payout |
| `processing` | Payout being processed by payment provider |
| `paid` | Funds sent to recipient |
| `failed` | Payout failed (wrong bank details, etc.) |
| `reversed` | Payout reversed after disbursement |
| `rejected` | Admin rejected the payout request |

### 2.6 ORDER ANYWHERE STATUS

| Value | Description |
|---|---|
| `submitted` | Customer submitted an "order anything" request |
| `under_review` | Admin reviewing the request |
| `sourcing` | Admin/vendor sourcing the item |
| `quoted` | Price quote provided to customer |
| `awaiting_customer_approval` | Customer must approve the quote |
| `approved` | Customer approved the quote |
| `awaiting_payment` | Customer must pay |
| `paid` | Payment received |
| `driver_assigned` | Driver assigned for pickup/delivery |
| `purchase_authorized` | Driver authorized to purchase on behalf |
| `purchased` | Driver completed the purchase |
| `receipt_submitted` | Driver submitted purchase receipt |
| `reconciled` | Admin reconciled receipt vs. quote |
| `in_delivery` | Item in transit to customer |
| `delivered` | Item delivered to customer |
| `completed` | Order fully completed |
| `cancelled` | Request cancelled |
| `refunded` | Refund issued |

### 2.7 FASHION FIT STATUS

| Value | Description |
|---|---|
| `draft` | Profile started but not saved |
| `profile_created` | Fit profile saved with basic info |
| `photos_pending` | Awaiting customer photo upload |
| `photos_uploaded` | Photos received |
| `measurements_saved` | Measurements extracted/saved |
| `stylist_request` | Customer sent request to stylists/vendors |
| `bids_open` | Stylists can submit bids/estimates |
| `estimate_received` | Customer received estimate(s) |
| `accepted` | Customer accepted an estimate |
| `completed` | Measurement/garment delivered |
| `cancelled` | Request cancelled |

---

## 3. STANDARD RESPONSE CONTRACT

Every API response across all apps **must** follow this shape:

### 3.1 Success Response

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { },
  "errors": null,
  "request_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

### 3.2 Error Response

```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "errors": {
    "field_name": ["The field name is required."]
  },
  "request_id": "550e8400-e29b-41d4-a716-446655440001"
}
```

### 3.3 List Response

```json
{
  "success": true,
  "message": "Records retrieved",
  "data": {
    "items": [],
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 0
    }
  },
  "errors": null,
  "request_id": "550e8400-e29b-41d4-a716-446655440002"
}
```

---

## 4. ACTOR PERMISSIONS MATRIX

| Capability | Customer | Vendor (Owner) | Driver | Admin/Dispatcher |
|---|---|---|---|---|
| **Place marketplace order** | Yes | — | — | — |
| **Cancel own order** | Yes (before preparing) | Yes (before driver_assigned) | — | Yes (any time) |
| **Accept/reject order** | — | Yes | — | — |
| **Update order status** | — | Yes (own stages) | Yes (delivery stages) | Yes (any stage) |
| **View all orders** | Own only | Own store only | Assigned only | All |
| **Request refund** | Yes | — | — | — |
| **Approve refund** | — | — | — | Yes |
| **Create parcel** | Yes | — | — | Yes |
| **Assign driver** | — | — | — | Yes |
| **Accept delivery job** | — | — | Yes | — |
| **Update delivery status** | — | — | Yes (assigned only) | Yes (override) |
| **View earnings** | — | Yes (own) | Yes (own) | All |
| **Request payout** | — | Yes | Yes | — |
| **Approve payout** | — | — | — | Yes |
| **Manage products** | — | Yes (own store) | — | Yes (all) |
| **View financial reports** | — | Own only | Own only | All |
| **Manage users** | — | — | — | Yes |
| **Override any status** | — | — | — | Yes |
| **Submit Order Anywhere** | Yes | — | — | — |
| **Fulfill Order Anywhere** | — | Yes (sourcing) | Yes (pickup/delivery) | Yes (administer) |
| **Create Fashion Fit profile** | Yes | — | — | — |
| **Bid on Fashion Fit request** | — | Yes | — | — |
| **Manage load board** | — | — | Yes (bid/accept) | Yes (create/assign) |
| **View audit logs** | — | — | — | Yes |
| **Use AI Concierge** | Yes | Yes | Yes | Yes |
| **View notifications** | Own | Own | Own | All |

---

## 5. CROSS-APP SHARED WORKFLOWS

### 5.1 MARKETPLACE ORDER

| Property | Value |
|---|---|
| **Entity Name** | Marketplace Order |
| **Database Table** | `orders` |
| **Canonical ID** | `order.id` (auto-increment) |
| **Primary Endpoint (Customer)** | `POST /api/v1/customer/order/place` |
| **Primary Endpoint (Vendor)** | `PUT /api/v1/seller/update-order-status` |
| **Primary Endpoint (Driver)** | `PUT /api/v1/delivery-man/update-order-status` |
| **Primary Endpoint (Admin)** | Admin Panel web routes |

#### Request Schema (Customer Place Order)

```json
{
  "store_id": 1,
  "address_id": 1,
  "order_amount": 25.99,
  "coupon_code": "SAVE10",
  "order_note": "Extra sauce please",
  "cutlery": true,
  "items": [
    {
      "item_id": 10,
      "quantity": 2,
      "price": 8.99,
      "variant": null,
      "add_on_ids": [1, 2],
      "customizations": []
    }
  ]
}
```

#### Response Schema (Place Order Success)

```json
{
  "success": true,
  "message": "Order placed successfully",
  "data": {
    "order_id": 12345,
    "order_status": "pending",
    "store_id": 1,
    "store_name": "Urban Kitchen",
    "total_amount": 25.99,
    "delivery_fee": 3.99,
    "payment_status": "pending",
    "estimated_delivery_time": "30-45 min"
  }
}
```

#### Allowed Status Transitions

| Current Status | Allowed Next | Triggered By |
|---|---|---|
| `pending` | `confirmed`, `vendor_rejected`, `cancelled` | Vendor/Admin |
| `confirmed` | `vendor_accepted`, `vendor_rejected` | Vendor |
| `vendor_accepted` | `preparing`, `cancelled` | Vendor |
| `preparing` | `ready_for_pickup`, `cancelled` | Vendor |
| `ready_for_pickup` | `driver_assigned`, `cancelled` | System/Admin |
| `driver_assigned` | `driver_accepted` | Driver |
| `driver_accepted` | `driver_arrived_pickup` | Driver |
| `driver_arrived_pickup` | `picked_up` | Driver |
| `picked_up` | `in_transit` | Driver |
| `in_transit` | `driver_arrived_dropoff` | Driver |
| `driver_arrived_dropoff` | `delivered` | Driver |
| `delivered` | `completed` | Customer/System |
| Any before `picked_up` | `cancelled` | Customer/Vendor/Admin |
| `delivered` | `returned` | Customer |
| `returned` | `refunded` | Admin |
| Any | `failed` | Driver/System |

#### Notification Events

| Status Change | Notification |
|---|---|
| Order placed | `order_created` → Vendor, Customer |
| Vendor accepted | `vendor_accepted` → Customer |
| Vendor rejected | `vendor_rejected` → Customer |
| Preparing | `order_preparing` → Customer |
| Ready for pickup | `ready_for_pickup` → Customer, Driver pool |
| Driver assigned | `driver_assigned` → Customer, Driver |
| Driver accepted | `driver_accepted` → Customer |
| Picked up | `picked_up` → Customer, Vendor |
| In transit | `in_transit` → Customer |
| Delivered | `delivered` → Customer, Vendor |
| Completed | `order_completed` → Customer, Vendor, Driver |
| Cancelled | `order_cancelled` → All affected parties |

#### Payment/Ledger Effect

| Event | Effect |
|---|---|
| Order placed | `payments` record created with status `pending` |
| Payment captured | `payments.status` → `captured`, ledger debit to UG platform |
| Order completed | `vendor_earnings` posted, `driver_earnings` posted |
| Refund issued | `payments.status` → `refunded`, ledger credit reversal |

#### Audit Log Effect

Every status change writes to `audit_logs`:
```json
{
  "entity_type": "order",
  "entity_id": 12345,
  "action": "status_changed",
  "old_status": "preparing",
  "new_status": "ready_for_pickup",
  "actor_type": "vendor",
  "actor_id": 100,
  "ip_address": "...",
  "user_agent": "...",
  "created_at": "2026-07-16T10:30:00Z"
}
```

#### Customer UI State

| Status | Screen State |
|---|---|
| `pending` | Order placed confirmation, spinner |
| `confirmed` | "Vendor confirmed your order" banner |
| `preparing` | Preparation progress bar |
| `ready_for_pickup` | "Looking for driver" state |
| `driver_assigned` | Driver info card with ETA |
| `picked_up` | Live map tracking |
| `in_transit` | Live map tracking with ETA |
| `delivered` | Delivery confirmation, rate driver prompt |
| `completed` | Order summary, review prompt |

#### Vendor UI State

| Status | Screen State |
|---|---|
| `pending` | New order notification, accept/reject buttons |
| `confirmed` | Order in "Confirmed" tab |
| `preparing` | Order in "Preparing" tab, timer |
| `ready_for_pickup` | "Ready for pickup" badge, driver assignment waiting |

#### Driver UI State

| Status | Screen State |
|---|---|
| `driver_assigned` | Job offer notification, accept/decline |
| `driver_accepted` | Active job card with navigation |
| `driver_arrived_pickup` | "Arrived at pickup" button |
| `picked_up` | "En route to customer" with navigation |
| `in_transit` | Live navigation |
| `delivered` | "Complete delivery" confirmation |

#### Admin UI State

| Status | Screen State |
|---|---|
| All | Order detail page with full timeline |
| Any status | Override status dropdown |
| `cancelled` | Cancellation reason display |
| `refunded` | Refund status and amount |

#### Error Behavior

- **422 Validation Error**: Return standard error response with field-level errors
- **409 Conflict**: Status transition not allowed — return `{"message": "Invalid status transition from {old} to {new}"}`
- **404 Not Found**: Order does not exist or not accessible by actor
- **403 Forbidden**: Actor does not have permission for this action
- **500 Server Error**: Return generic error, log internally, alert monitoring

#### Retry Behavior

- **Place Order**: Client retry up to 3 times with exponential backoff (1s, 2s, 4s)
- **Status Update**: Optimistic locking — if version mismatch, re-fetch and retry once
- **Payment**: Gateway retry per provider rules (typically 1 auto-retry)

#### Ownership Rules

- Customer can only view/manage their own orders
- Vendor can only view/manage orders for their store(s)
- Driver can only view/manage assigned orders
- Admin has full access to all orders

---

### 5.2 PARCEL / COURIER ORDER

| Property | Value |
|---|---|
| **Entity Name** | Parcel Order |
| **Database Table** | `parcel_orders` |
| **Canonical ID** | `parcel_order.id` |
| **Customer Endpoint** | `POST /api/v1/customer/order/place` (module=parcel) |
| **Driver Endpoint** | `PUT /api/v1/delivery-man/update-order-status` |
| **Admin Endpoint** | Admin Panel web routes |

#### Status Transitions

| Current | Next | Actor |
|---|---|---|
| `draft` | `quoted` | System/Admin |
| `quoted` | `awaiting_payment` | System |
| `awaiting_payment` | `paid` | Customer |
| `paid` | `available` | System |
| `available` | `assigned` | Admin/System |
| `assigned` | `accepted` | Driver |
| `accepted` | `arrived_pickup` | Driver |
| `arrived_pickup` | `picked_up` | Driver |
| `picked_up` | `in_transit` | Driver |
| `in_transit` | `arrived_dropoff` | Driver |
| `arrived_dropoff` | `delivered` | Driver |
| `delivered` | `completed` | System/Customer |
| Any before `picked_up` | `cancelled` | Customer/Admin |
| `delivered` | `return_required` | Admin/Customer |
| `return_required` | `returned` | Driver |

#### Error Behavior

- Same as Marketplace Order (Section 5.1)
- Parcel-specific: `return_required` → driver must confirm return within 24h or auto-reassign

---

### 5.3 ORDER ANYWHERE

| Property | Value |
|---|---|
| **Entity Name** | Order Anywhere Request |
| **Database Table** | `order_anywhere_requests` |
| **Canonical ID** | `order_anywhere_requests.id` |
| **Customer Endpoint** | `POST /api/v1/order-anywhere/requests` |
| **Vendor Endpoint** | `POST /api/v1/order-anywhere/vendor/requests/{id}/update` |
| **Driver Endpoint** | `POST /api/v1/order-anywhere/driver/{id}/accept` |
| **Admin Endpoint** | `POST /api/v1/order-anywhere/admin/requests/{id}/status` |

#### Request Schema (Customer Submit)

```json
{
  "description": "Need 2 boxes of Huggies diapers, size 4",
  "category": "grocery",
  "estimated_budget": 45.00,
  "pickup_location": "Walmart Supercenter, 123 Main St",
  "dropoff_address_id": 1,
  "special_instructions": "Front door delivery",
  "images": ["base64_encoded_image_data"]
}
```

#### Status Transitions

| Current | Next | Actor |
|---|---|---|
| `submitted` | `under_review` | System (auto) |
| `under_review` | `sourcing`, `cancelled` | Admin |
| `sourcing` | `quoted` | Admin/Vendor |
| `quoted` | `awaiting_customer_approval`, `cancelled` | System |
| `awaiting_customer_approval` | `approved`, `cancelled` | Customer |
| `approved` | `awaiting_payment` | System |
| `awaiting_payment` | `paid` | Customer |
| `paid` | `driver_assigned` | Admin/System |
| `driver_assigned` | `purchase_authorized` | System |
| `purchase_authorized` | `purchased` | Driver |
| `purchased` | `receipt_submitted` | Driver |
| `receipt_submitted` | `reconciled` | Admin |
| `reconciled` | `in_delivery` | System |
| `in_delivery` | `delivered` | Driver |
| `delivered` | `completed` | Customer/System |
| Any before `purchased` | `cancelled` | Customer/Admin |
| `delivered` | `refunded` | Admin |

#### Driver-Specific Actions

| Action | Endpoint | Description |
|---|---|---|
| Get purchase card | `GET /api/v1/urban-goodz/driver/order-anywhere/{id}/purchase-card` | Retrieve virtual card for purchase |
| Authorize purchase | `POST .../purchase-card/authorize` | Begin purchase with card |
| Complete purchase | `POST .../purchase-card/complete` | Confirm purchase, submit receipt |

---

### 5.4 LOAD BOARD

| Property | Value |
|---|---|
| **Entity Name** | Load Board Load |
| **Database Table** | `load_board_loads` |
| **Canonical ID** | `load_board_loads.id` |
| **Admin/Dispatcher Endpoint** | `GET /api/v1/urban-goodz/load-board/loads` (admin routes) |
| **Driver List Endpoint** | `GET /api/v1/urban-goodz/driver/load-board` |
| **Driver Bid Endpoint** | `POST /api/v1/urban-goodz/driver/load-board/{id}/bid` |
| **Driver Accept Endpoint** | `POST /api/v1/urban-goodz/driver/load-board/{id}/accept` |
| **Customer Browse Endpoint** | `GET /api/v1/urban-goodz/load-board/loads` |

#### Status Transitions

| Current | Next | Actor |
|---|---|---|
| `draft` | `sourced` | Admin |
| `sourced` | `recommended` | AI/System |
| `recommended` | `dispatcher_review` | System |
| `dispatcher_review` | `approved`, `rejected` | Dispatcher |
| `approved` | `available` | System |
| `available` | `assigned` | Dispatcher/System |
| `available` | `accepted` | Driver (via bid) |
| `assigned` | `accepted` | Driver |
| `accepted` | `en_route_pickup` | Driver |
| `en_route_pickup` | `arrived_pickup` | Driver |
| `arrived_pickup` | `loaded` | Driver |
| `loaded` | `in_transit` | Driver |
| `in_transit` | `arrived_delivery` | Driver |
| `arrived_delivery` | `unloaded` | Driver |
| `unloaded` | `pod_submitted` | Driver |
| `pod_submitted` | `completed` | Admin/System |
| Any before `loaded` | `cancelled` | Dispatcher/Driver |
| Any in transit | `exception` | Driver |
| `exception` | `completed`, `cancelled` | Dispatcher |

---

### 5.5 FASHION FIT

| Property | Value |
|---|---|
| **Entity Name** | Fashion Fit Profile / Request |
| **Database Tables** | `fashion_fit_profiles`, `fashion_fit_requests`, `fashion_fit_measurements` |
| **Canonical ID** | `fashion_fit_profiles.uuid`, `fashion_fit_requests.uuid` |
| **Customer Profile Endpoint** | `GET/POST /api/v1/fashion-fit/profiles` |
| **Customer Request Endpoint** | `POST /api/v1/fashion-fit/requests` |
| **Vendor Endpoint** | `GET/POST /api/v1/vendor/fashion-fit/requests/{id}/estimates` |
| **Admin Endpoint** | `GET /api/v1/admin/fashion-fit/requests` |

#### Customer Profile Flow

| Step | Endpoint | Action |
|---|---|---|
| Create profile | `POST /api/v1/fashion-fit/profiles` | Save body measurements |
| Upload photos | `POST /api/v1/fashion-fit/profiles/{uuid}/photos` | Upload reference photos |
| Submit analysis | `POST /api/v1/fashion-fit/profiles/{uuid}/analyses` | AI measurement extraction |
| Approve measurements | `POST /api/v1/fashion-fit/profiles/{uuid}/approve` | Customer confirms accuracy |

#### Stylist Request Flow

| Step | Endpoint | Actor |
|---|---|---|
| Submit stylist request | `POST /api/v1/fashion-fit/requests` | Customer |
| Receive bids | — | System populates |
| Review estimate | `POST /api/v1/fashion-fit/requests/{uuid}/estimates/{id}/decision` | Customer |
| Staged payment | `POST /api/v1/fashion-fit/requests/{uuid}/staged-payment` | Customer |
| Revoke request | `POST /api/v1/fashion-fit/requests/{uuid}/revoke` | Customer |

#### Vendor Actions

| Action | Endpoint |
|---|---|
| View assigned requests | `GET /api/v1/vendor/fashion-fit/requests` |
| Request clarification | `POST /api/v1/vendor/fashion-fit/requests/{uuid}/clarification` |
| Submit estimate | `POST /api/v1/vendor/fashion-fit/requests/{uuid}/estimates` |
| Update status | `POST /api/v1/vendor/fashion-fit/requests/{uuid}/status` |
| View earnings | `GET /api/v1/vendor/fashion-fit/earnings` |

---

### 5.6 SERVICE BOOKINGS

| Property | Value |
|---|---|
| **Entity Name** | Service Booking |
| **Database Table** | `service_bookings` |
| **Canonical ID** | `service_bookings.id` |
| **Customer Endpoint** | `POST /api/v1/customer/service-bookings` |
| **Vendor Endpoint** | `POST /api/v1/vendor/service-bookings/bookings/{id}/quote` |
| **Admin Endpoint** | `GET /api/v1/admin/service-bookings/bookings` |

#### Customer Flow

| Step | Endpoint | Method |
|---|---|---|
| List providers | `GET /api/v1/customer/service-bookings/providers` | GET |
| View provider | `GET /api/v1/customer/service-bookings/providers/{provider}` | GET |
| Create booking | `POST /api/v1/customer/service-bookings` | POST |
| View booking | `GET /api/v1/customer/service-bookings/{booking}` | GET |
| Accept quote | `POST /api/v1/customer/service-bookings/{booking}/accept-quote` | POST |
| Pay | `POST /api/v1/customer/service-bookings/{booking}/payment` | POST |
| Confirm completion | `POST /api/v1/customer/service-bookings/{booking}/confirm` | POST |
| Cancel | `POST /api/v1/customer/service-bookings/{booking}/cancel` | POST |
| Reschedule | `POST /api/v1/customer/service-bookings/{booking}/reschedule` | POST |
| Review | `POST /api/v1/customer/service-bookings/{booking}/review` | POST |

#### Vendor Flow

| Step | Endpoint | Method |
|---|---|---|
| View profile | `GET /api/v1/vendor/service-bookings/profile` | GET |
| Update profile | `PUT /api/v1/vendor/service-bookings/profile` | PUT |
| Manage services | `GET/POST/PUT/DELETE /api/v1/vendor/service-bookings/services` | CRUD |
| Update availability | `PUT /api/v1/vendor/service-bookings/availability` | PUT |
| View bookings | `GET /api/v1/vendor/service-bookings/bookings` | GET |
| Submit quote | `POST /api/v1/vendor/service-bookings/bookings/{id}/quote` | POST |
| Transition status | `POST /api/v1/vendor/service-bookings/bookings/{id}/status` | POST |
| View earnings | `GET /api/v1/vendor/service-bookings/earnings` | GET |

---

### 5.7 DRIVER ACTIVE JOBS (UNIFIED)

| Property | Value |
|---|---|
| **Entity Name** | Driver Active Job |
| **Database Table** | `driver_active_jobs` (view/aggregation) |
| **Canonical ID** | `job.id` |
| **List Endpoint** | `GET /api/v1/urban-goodz/driver/active-jobs` |
| **Detail Endpoint** | `GET /api/v1/urban-goodz/driver/active-jobs/{id}` |
| **Start** | `POST /api/v1/urban-goodz/driver/active-jobs/{id}/start` |
| **Complete** | `POST /api/v1/urban-goodz/driver/active-jobs/{id}/complete` |
| **Cancel** | `POST /api/v1/urban-goodz/driver/active-jobs/{id}/cancel` |
| **Update Status** | `POST /api/v1/urban-goodz/driver/active-jobs/{id}/status` |

This unified endpoint aggregates marketplace orders, parcels, Order Anywhere jobs, and load board jobs into a single driver-facing view.

---

### 5.8 DRIVER BUSINESS COURIER JOBS

| Property | Value |
|---|---|
| **Entity Name** | Business Courier Job |
| **Database Table** | `business_courier_jobs` |
| **Canonical ID** | `business_courier_jobs.id` |
| **List Endpoint** | `GET /api/v1/urban-goodz/driver/business-jobs` |
| **Detail** | `GET /api/v1/urban-goodz/driver/business-jobs/{id}` |
| **Accept** | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/accept` |
| **Start** | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/start` |
| **Pickup** | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/pickup` |
| **Delivery** | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/delivery` |
| **Proof Pickup** | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/proof-pickup` |
| **Proof Delivery** | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/proof-delivery` |
| **Exception** | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/exception` |

---

### 5.9 DRIVER EARNINGS & PAYOUTS

| Property | Value |
|---|---|
| **Entity Name** | Driver Earning / Payout |
| **Database Tables** | `driver_earnings`, `driver_payouts` |
| **Canonical ID** | `driver_earnings.id`, `driver_payouts.id` |
| **Earnings Endpoint** | `GET /api/v1/urban-goodz/driver/earnings` |
| **Payout Request** | `POST /api/v1/urban-goodz/driver/payout-request` |
| **Payout History** | `GET /api/v1/urban-goodz/driver/payout-history` |

---

### 5.10 VENDOR EARNINGS & PAYOUTS

| Property | Value |
|---|---|
| **Entity Name** | Vendor Earning / Payout |
| **Database Tables** | `store_earnings`, `store_payouts` |
| **Canonical ID** | `store_earnings.id`, `store_payouts.id` |
| **Earnings Endpoint** | `GET /api/v1/vendor/earning-info` |
| **Earning Report** | `GET /api/v1/vendor/earning-report` |
| **Withdraw Request** | `POST /api/v1/vendor/request-withdraw` |
| **Withdraw List** | `GET /api/v1/vendor/get-withdraw-list` |

---

### 5.11 AI CONCIERGE

| Property | Value |
|---|---|
| **Entity Name** | AI Concierge Query |
| **Database Table** | `ai_concierge_sessions`, `ai_concierge_messages` |
| **Canonical ID** | `ai_concierge_sessions.id` |
| **Query Endpoint** | `POST /api/v1/urban-goodz/ai-concierge/query` |
| **Chat Endpoint** | `POST /api/v1/urban-goodz/ai-concierge/chat` |
| **History Endpoint** | `GET /api/v1/urban-goodz/ai-concierge/history` |
| **Allowed Actors** | Customer, Vendor, Driver, Admin |

#### Request Schema

```json
{
  "message": "What's the fastest route to my next delivery?",
  "context": {
    "screen": "active_delivery",
    "current_order_id": 12345,
    "location": {"lat": 33.749, "lng": -84.388}
  }
}
```

---

### 5.12 DISCOVERY & OPPORTUNITIES

| Property | Value |
|---|---|
| **Entity Name** | Discovery Entity / Opportunity |
| **Database Tables** | `discovery_entities`, `discovery_opportunities` |
| **Search Endpoint** | `POST /api/v1/urban-goodz/discovery/search-capture` |
| **Entities Endpoint** | `GET /api/v1/urban-goodz/discovery/entities` |
| **Entity Detail** | `GET /api/v1/urban-goodz/discovery/entities/{id}` |
| **Entity Action** | `POST /api/v1/urban-goodz/discovery/entities/{id}/action` |
| **Opportunities** | `GET /api/v1/urban-goodz/discovery/opportunities` |
| **Accept Opportunity** | `POST /api/v1/urban-goodz/discovery/opportunities/{id}/accept` |

---

### 5.13 REELS & CREATOR COMMERCE

| Property | Value |
|---|---|
| **Entity Name** | Reel / Creator Promotion |
| **Database Tables** | `reels`, `creator_commerce_applications`, `creator_commerce_promotions` |
| **Reel List** | `GET /api/v1/customer/reels/list` |
| **Reel Details** | `GET /api/v1/customer/reels/details` |
| **Reel Stats** | `GET /api/v1/customer/reels/stats` |
| **Reel Like** | `GET /api/v1/customer/reels/like` |
| **Reel Visit** | `GET /api/v1/customer/reels/visit` |
| **UG Reels Action** | `POST /api/v1/urban-goodz/reels/action` |
| **UG Reels Conversion** | `POST /api/v1/urban-goodz/reels/conversion` |
| **Creator Applications** | `POST /api/v1/urban-goodz/creator-commerce/applications` |
| **Featured Reels** | `GET /api/v1/urban-goodz/creator-commerce/featured-reels` |

---

### 5.14 VENDOR PRODUCTS (ITEMS)

| Property | Value |
|---|---|
| **Entity Name** | Product / Item |
| **Database Table** | `items` |
| **Canonical ID** | `items.id` |
| **Customer Browse** | `GET /api/v1/items/latest`, `popular`, `most-reviewed` |
| **Item Detail** | `GET /api/v1/items/details/{id}` |
| **Vendor CRUD** | `POST/PUT/DELETE /api/v1/seller/item/store`, `update`, `delete` |
| **Vendor Stock** | `PUT /api/v1/seller/item/stock-update` |

---

## 6. OWNERSHIP RULES (GLOBAL)

| Entity | Owner | Access |
|---|---|---|
| Customer Account | Customer (self) | Full read/write on own profile |
| Vendor Account | Vendor (self) | Full read/write on own store |
| Driver Account | Driver (self) | Full read/write on own profile |
| Marketplace Order | Customer (placer) + Vendor (store) + Driver (assigned) | Scoped access per role |
| Parcel Order | Customer (sender) + Driver (assigned) | Scoped access per role |
| Order Anywhere Request | Customer (requester) + Admin (administers) | Customer sees own; Admin sees all |
| Load Board Load | Dispatcher (creator) + Driver (assigned) | Dispatcher manages; Driver operates |
| Fashion Fit Profile | Customer (owner) | Customer full access; Vendor sees assigned requests |
| Service Booking | Customer (booker) + Vendor (provider) | Scoped per role |
| Product/Item | Vendor (store owner) | Vendor manages own; Customer browses |
| Earnings | Driver/Vendor (earner) | Own earnings only; Admin sees all |
| Payout | Driver/Vendor (requester) | Own payouts; Admin approves |

---

## 7. WEBSOCKET / REALTIME EVENTS

All apps connect to Pusher on channel `private-{user_id}-{user_type}`.

| Event | Channel Pattern | Payload |
|---|---|---|
| `order-status-updated` | `private-{user_id}-customer` | `{order_id, old_status, new_status}` |
| `vendor-order-received` | `private-{vendor_id}-vendor` | `{order_id, items_summary, total}` |
| `driver-job-assigned` | `private-{driver_id}-driver` | `{job_id, type, pickup, dropoff}` |
| `new-notification` | `private-{user_id}-{user_type}` | `{notification_id, title, body, type}` |
| `load-recommended` | `private-{driver_id}-driver` | `{load_id, origin, destination, rate}` |
| `dispatch-alert` | `private-{admin_id}-admin` | `{alert_type, entity_type, entity_id}` |

---

## 8. AUTHENTICATION CONTRACT

| App | Auth Header | Token Endpoint | Token Storage Key |
|---|---|---|---|
| Customer | `Authorization: Bearer {token}` | `POST /api/v1/auth/login` | `6ammart_token` |
| Vendor | `Authorization: Bearer {token}` + `vendorType: owner` | `POST /api/v1/auth/vendor/login` | Vendor secure storage |
| Driver | `Authorization: Bearer {token}` | `POST /api/v1/auth/delivery-man/login` | Driver secure storage |
| Admin | `Authorization: Bearer {token}` (admin guard) | Admin Panel login | Session/Cookie |

All API calls require `Accept: application/json` header.  
Vendor app adds `vendorType: owner` header.  
Driver app uses `dm.api` middleware for protected routes.  
All routes rate-limited: `throttle:60,1` (60 requests per minute).

---

## 9. FILE UPLOAD CONTRACT

| Category | Endpoint | Allowed Types | Max Size |
|---|---|---|---|
| Fashion Fit Photos | `POST /api/v1/fashion-fit/profiles/{uuid}/photos` | jpg, png, heic | 10MB per file |
| Order Anywhere Images | Included in order creation payload | jpg, png | 5MB per file |
| Delivery Proof | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/proof-delivery` | jpg, png | 5MB per file |
| General Files | `POST /api/v1/urban-goodz/files/upload/{category}` | jpg, png, pdf | 10MB per file |
| Certification Docs | `POST /api/v1/urban-goodz/driver/certifications/{id}/upload` | pdf, jpg, png | 5MB per file |

---

## 10. VERSIONING & CHANGELOG

| Version | Date | Changes |
|---|---|---|
| 3.9 | 2026-07-16 | Initial master contract creation |

All breaking changes to this contract require:
1. Version bump in `app_constants.dart` (`appVersion`)
2. Backend route deprecation notice (minimum 2 release cycles)
3. Update to this document with migration notes
