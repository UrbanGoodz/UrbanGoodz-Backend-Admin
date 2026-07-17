# LOAD BOARD CROSS-SURFACE MATRIX

**Version:** 3.9  
**Last Updated:** 2026-07-16  
**Purpose:** Maps the Load Board feature across every surface — Customer app, Driver app, Admin Panel, and Dispatcher AI — showing who sees what, who does what, and how data flows.

---

## 1. LOAD BOARD ENTITY DEFINITION

| Property | Value |
|---|---|
| **Entity** | Load Board Load |
| **Database Table** | `load_board_loads` |
| **Primary Key** | `id` (auto-increment) |
| **UUID** | `uuid` (for external references) |
| **Owner** | Dispatcher/Admin (creator) |
| **Assigned To** | Driver (after assignment/acceptance) |
| **Visibility** | Admin: All; Driver: Available + Assigned; Customer: Browse only |

---

## 2. SURFACE ACCESS MATRIX

| Surface | Create Load | View All Loads | View Available | View Assigned | Accept/Bid | Update Status | View Analytics | Exception Management |
|---|---|---|---|---|---|---|---|---|
| **Admin Panel (Dispatcher)** | ✅ | ✅ | ✅ | ✅ | — | ✅ (override) | ✅ | ✅ |
| **Driver App** | ❌ | ❌ | ✅ (filtered) | ✅ (own only) | ✅ | ✅ (own only) | ❌ | ✅ (own only) |
| **Customer App** | ❌ | ✅ (browse only) | ✅ (browse only) | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Dispatcher AI** | ✅ (via parse) | ✅ | ✅ | ✅ | ✅ (auto-assign) | ✅ | ✅ | ✅ |

---

## 3. ADMIN PANEL — LOAD BOARD OPERATIONS

### 3.1 Admin Routes for Load Board

| Operation | Backend Route | Method | Auth |
|---|---|---|---|
| List Loads | Admin Panel web routes | GET | auth:admin |
| Create Load | Admin Panel web routes | POST | auth:admin |
| Edit Load | Admin Panel web routes | PUT | auth:admin |
| Delete Load | Admin Panel web routes | DELETE | auth:admin |
| Approve Load | Admin Panel web routes | POST | auth:admin |
| Assign Driver | Admin Panel web routes | POST | auth:admin |
| Override Status | Admin Panel web routes | POST | auth:admin |
| View Analytics | Admin Panel web routes | GET | auth:admin |
| AI Load Ranking | `POST /api/v1/urban-goodz/dispatcher/ai/load-ranking` | POST | auth:admin |
| AI Driver Match | `POST /api/v1/urban-goodz/dispatcher/ai/driver-match` | POST | auth:admin |
| AI Rate Estimate | `POST /api/v1/urban-goodz/dispatcher/ai/rate-estimate` | POST | auth:admin |
| AI Duplicate Check | `POST /api/v1/urban-goodz/dispatcher/ai/duplicate-check` | POST | auth:admin |
| AI Ops Summary | `GET /api/v1/urban-goodz/dispatcher/ai/ops-summary` | GET | auth:admin |
| AI Parse Load | `POST /api/v1/urban-goodz/dispatcher/ai/parse-load` | POST | auth:admin |
| AI Parse Email | `POST /api/v1/urban-goodz/dispatcher/ai/parse-email` | POST | auth:admin |
| AI Parse Batch | `POST /api/v1/urban-goodz/dispatcher/ai/parse-batch` | POST | auth:admin |
| AI Source Status | `GET /api/v1/urban-goodz/dispatcher/ai/source-status` | GET | auth:admin |
| AI Sync Source | `POST /api/v1/urban-goodz/dispatcher/ai/sync-source` | POST | auth:admin |

### 3.2 Admin Dashboard Load Board View

| Section | Data Displayed | Actions |
|---|---|---|
| Load Queue | All loads in `dispatcher_review` and `approved` status | Approve, Reject, Edit, Delete |
| Available Loads | Loads in `available` status | Assign Driver, View Bids |
| Active Loads | Loads in `in_transit`, `loaded`, `arrived_delivery` status | View Progress, Override Status |
| Exception Queue | Loads in `exception` status | Resolve, Reassign, Cancel |
| Completed Loads | Loads in `completed`, `pod_submitted` status | View POD, Close, Reopen |
| AI Recommendations | AI-suggested load-driver matches | Accept AI suggestion, Manual override |
| Analytics Dashboard | Total loads, revenue, avg rate, on-time %, exception rate | Filter by date, driver, origin/destination |

---

## 4. DRIVER APP — LOAD BOARD OPERATIONS

### 4.1 Driver API Routes for Load Board

| Operation | Backend Route | Method | Auth | Flutter Constant |
|---|---|---|---|---|
| View Available Loads | `GET /api/v1/urban-goodz/driver/load-board` | GET | dm.api | `ApiConfig.loadBoard` |
| Bid on Load | `POST /api/v1/urban-goodz/driver/load-board/{id}/bid` | POST | dm.api | `ApiConfig.loadBoardBid(id)` |
| Accept Load | `POST /api/v1/urban-goodz/driver/load-board/{id}/accept` | POST | dm.api | `ApiConfig.loadBoardAccept(id)` |
| View Opportunities | `GET /api/v1/urban-goodz/driver/opportunities` | GET | dm.api | `ApiConfig.opportunities` |
| Claim Opportunity | `POST /api/v1/urban-goodz/driver/opportunities/{id}/claim` | POST | dm.api | `ApiConfig.opportunityClaim(id)` |
| Load Recommendations (AI) | `GET /api/v1/ai/load-recommendations` | GET | dm.api | Via api client |

### 4.2 Driver App Load Board Screen Flow

```
Job Discovery Tab
├── Recommended Loads (AI-ranked)
│   ├── Load Card: origin → destination, rate, distance, equipment match
│   ├── Tap → Load Detail Screen
│   │   ├── Origin/Destination on map
│   │   ├── Pickup/Delivery windows
│   │   ├── Freight type, weight, dimensions
│   │   ├── Rate and payment terms
│   │   ├── Shipper rating
│   │   └── [Bid] [Accept] buttons
│   └── Bid Modal: bid amount, notes, estimated pickup time
├── Available Loads (all)
│   ├── Filter: origin, destination, rate range, equipment type
│   └── Sort: rate, distance, posted date
├── My Bids (pending)
│   └── Bid status: pending, accepted, rejected
├── Active Loads (accepted)
│   └── Status tracking with in-app actions
└── Completed Loads
    └── POD confirmation, earnings summary
```

### 4.3 Driver Load Board Data Contract

#### Available Loads Response

```json
{
  "success": true,
  "message": "Loads retrieved",
  "data": {
    "items": [
      {
        "id": 1234,
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "origin": {
          "city": "Atlanta",
          "state": "GA",
          "facility": "Warehouse A",
          "lat": 33.749,
          "lng": -84.388,
          "pickup_window_start": "2026-07-17T08:00:00Z",
          "pickup_window_end": "2026-07-17T12:00:00Z"
        },
        "destination": {
          "city": "Nashville",
          "state": "TN",
          "facility": "Distribution Center B",
          "lat": 36.1627,
          "lng": -86.7816,
          "delivery_window_start": "2026-07-17T14:00:00Z",
          "delivery_window_end": "2026-07-17T18:00:00Z"
        },
        "freight_type": "dry_van",
        "weight_lbs": 15000,
        "dimensions": "48x53x96",
        "rate": 1200.00,
        "payment_terms": "net_30",
        "distance_miles": 250,
        "special_requirements": ["liftergate", "pallet_jack"],
        "shipper_rating": 4.8,
        "posted_at": "2026-07-16T10:00:00Z",
        "status": "available"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 98
    }
  }
}
```

#### Bid Request

```json
{
  "amount": 1150.00,
  "notes": "Can pick up 30 mins early. Have dry van with lift gate.",
  "estimated_pickup": "2026-07-17T07:30:00Z"
}
```

---

## 5. CUSTOMER APP — LOAD BOARD BROWSE

### 5.1 Customer Routes for Load Board

| Operation | Backend Route | Method | Auth | Flutter Constant |
|---|---|---|---|---|
| Browse Loads | `GET /api/v1/urban-goodz/load-board/loads` | GET | auth:api | `ugLoadBoardLoadsUri` |
| View Load Detail | `GET /api/v1/urban-goodz/load-board/loads/{id}` | GET | auth:api | Via service |

### 5.2 Customer App Load Board Context

Customers interact with the load board as **shippers** — they can post loads or browse available capacity. The customer app view is limited to:
- Viewing available loads (for reference/pricing)
- Submitting a "Book Anything" or "Logistics" request that may be routed to the load board
- No direct bid/accept capabilities

---

## 6. DISPATCHER AI — LOAD BOARD INTELLIGENCE

### 6.1 Dispatcher AI Routes

| Operation | Backend Route | Method | Purpose |
|---|---|---|---|
| Rank Loads | `POST /api/v1/urban-goodz/dispatcher/ai/load-ranking` | POST | AI ranks loads by profitability, efficiency, driver match |
| Match Drivers | `POST /api/v1/urban-goodz/dispatcher/ai/driver-match` | POST | AI matches best drivers to a specific load |
| Estimate Rate | `POST /api/v1/urban-goodz/dispatcher/ai/rate-estimate` | POST | AI estimates fair market rate for a load |
| Check Duplicates | `POST /api/v1/urban-goodz/dispatcher/ai/duplicate-check` | POST | AI detects duplicate loads from multiple sources |
| Parse Load | `POST /api/v1/urban-goodz/dispatcher/ai/parse-load` | POST | AI parses raw text/email into structured load data |
| Parse Email | `POST /api/v1/urban-goodz/dispatcher/ai/parse-email` | POST | AI extracts load details from email body |
| Parse Batch | `POST /api/v1/urban-goodz/dispatcher/ai/parse-batch` | POST | AI processes multiple load sources simultaneously |
| Source Status | `GET /api/v1/urban-goodz/dispatcher/ai/source-status` | GET | Status of external load board integrations |
| Sync Source | `POST /api/v1/urban-goodz/dispatcher/ai/sync-source` | POST | Trigger sync from external load board |

### 6.2 Dispatcher AI Load Ranking Request/Response

#### Request

```json
{
  "filters": {
    "origin_states": ["GA", "AL", "TN"],
    "destination_states": ["TN", "KY", "VA"],
    "min_rate": 800,
    "max_rate": 2000,
    "freight_type": "dry_van",
    "date_range": {
      "start": "2026-07-17",
      "end": "2026-07-20"
    }
  },
  "sort_by": "profitability",
  "limit": 20
}
```

#### Response

```json
{
  "success": true,
  "message": "Loads ranked",
  "data": {
    "ranked_loads": [
      {
        "load_id": 1234,
        "rank": 1,
        "score": 92,
        "profitability_score": 95,
        "driver_match_score": 88,
        "urgency_score": 90,
        "estimated_profit": 350.00,
        "recommended_drivers": [
          {"driver_id": 101, "match_score": 94, "name": "John D."},
          {"driver_id": 205, "match_score": 87, "name": "Mike R."}
        ],
        "rate_assessment": "above_market",
        "market_rate_range": {"min": 950, "max": 1350, "median": 1150}
      }
    ],
    "summary": {
      "total_ranked": 20,
      "avg_score": 78,
      "avg_rate": 1100
    }
  }
}
```

### 6.3 AI Load Parse Request/Response

#### Request (from raw text)

```json
{
  "raw_text": "NEED: Dry van, Atlanta GA to Nashville TN, 15000 lbs, pickup 7/17 AM, deliver 7/17 PM. Rate: $1200. Contact: shipper@email.com"
}
```

#### Response

```json
{
  "success": true,
  "message": "Load parsed successfully",
  "data": {
    "parsed_load": {
      "origin": {"city": "Atlanta", "state": "GA"},
      "destination": {"city": "Nashville", "state": "TN"},
      "freight_type": "dry_van",
      "weight_lbs": 15000,
      "pickup_date": "2026-07-17",
      "delivery_date": "2026-07-17",
      "rate": 1200.00,
      "contact_email": "shipper@email.com",
      "confidence_score": 94
    },
    "validation_warnings": [],
    "suggested_actions": ["check_duplicate", "estimate_rate", "match_driver"]
  }
}
```

---

## 7. LOAD BOARD STATUS FLOW (CROSS-SURFACE)

| Status | Admin Action | Driver Action | AI Action | Notification Trigger |
|---|---|---|---|---|
| `draft` | Creates load | — | — | — |
| `sourced` | — | — | AI parses from external source | — |
| `recommended` | Reviews AI recommendation | — | AI ranks and recommends | `load_recommended` → Driver |
| `dispatcher_review` | Reviews and approves/rejects | — | — | — |
| `approved` | Approves for driver pool | — | — | — |
| `available` | — | Views in discovery, bids/accepts | AI matches drivers | `load_recommended` → Matched drivers |
| `assigned` | Assigns driver manually | — | AI auto-assigns | `load_assigned` → Driver, Admin |
| `accepted` | Monitors | Driver accepts | — | — |
| `en_route_pickup` | Monitors | Driver updates status | — | — |
| `arrived_pickup` | Monitors | Driver confirms arrival | — | — |
| `loaded` | Monitors | Driver confirms loaded | — | — |
| `in_transit` | Monitors | Driver updates location | AI monitors ETA | — |
| `arrived_delivery` | Monitors | Driver confirms arrival | — | — |
| `unloaded` | Monitors | Driver confirms unloaded | — | — |
| `pod_submitted` | Reviews POD | Driver submits proof | AI verifies POD | `load_pod_submitted` → Admin, Customer |
| `completed` | Closes load | — | — | `load_completed` → Admin, Dispatcher |
| `exception` | Resolves exception | Driver reports exception | AI detects anomaly | `load_exception` → Admin, Dispatcher |
| `cancelled` | Cancels load | — | — | — |
| `rejected` | Rejects load | Driver rejects assignment | — | — |

---

## 8. LOAD BOARD DATA FLOW DIAGRAM

```
EXTERNAL SOURCES                    URBAN GOODZ PLATFORM
┌──────────────┐                    ┌─────────────────────────┐
│ DAT          │──── AI Parse ─────>│ Dispatcher AI            │
│ Truckstop    │──── AI Parse ─────>│  ├── parse-load          │
│ Email        │──── AI Parse ─────>│  ├── parse-email         │
│ Manual Entry │──── Admin CRUD ───>│  └── parse-batch         │
└──────────────┘                    └───────────┬─────────────┘
                                                │
                                    ┌───────────v─────────────┐
                                    │ load_board_loads table   │
                                    │  status: sourced         │
                                    └───────────┬─────────────┘
                                                │
                                    ┌───────────v─────────────┐
                                    │ Dispatcher AI Ranking    │
                                    │  ├── load-ranking        │
                                    │  ├── driver-match        │
                                    │  └── rate-estimate       │
                                    └───────────┬─────────────┘
                                                │
                                    ┌───────────v─────────────┐
                                    │ Dispatcher Review        │
                                    │  status: approved        │
                                    └───────────┬─────────────┘
                                                │
                                    ┌───────────v─────────────┐
                                    │ Available Load Pool      │
                                    │  status: available       │
                                    └───────┬─────────┬───────┘
                                            │         │
                              ┌─────────────v─┐   ┌───v──────────────┐
                              │ Driver App    │   │ Customer App     │
                              │  - Discovery  │   │  - Browse Only   │
                              │  - Bid/Accept │   │  - Reference     │
                              └───────┬───────┘   └──────────────────┘
                                      │
                              ┌───────v──────────────┐
                              │ Driver Assigned       │
                              │  status: assigned     │
                              └───────┬──────────────┘
                                      │
                              ┌───────v──────────────┐
                              │ Active Job (Driver)   │
                              │  - en_route_pickup    │
                              │  - loaded             │
                              │  - in_transit         │
                              │  - arrived_delivery   │
                              │  - pod_submitted      │
                              └───────┬──────────────┘
                                      │
                              ┌───────v──────────────┐
                              │ Completed             │
                              │  - POD verified       │
                              │  - Earnings posted    │
                              │  - Payout eligible    │
                              └──────────────────────┘
```

---

## 9. LOAD BOARD UI STATES

### 9.1 Driver App — Load Discovery

| Screen | State | Content |
|---|---|---|
| Load Board Main | Empty | "No loads available. Check back soon." + refresh button |
| Load Board Main | Loading | Skeleton loaders for load cards |
| Load Board Main | Loaded | List of load cards with origin, destination, rate, distance |
| Load Board Main | Error | Error message with retry button |
| Load Detail | Empty | "Load details unavailable" |
| Load Detail | Loaded | Full load info + map + bid/accept buttons |
| Bid Submitted | Pending | "Bid submitted. Awaiting response." |
| Bid Accepted | Success | "Congratulations! Load assigned to you." |
| Bid Rejected | Rejected | "Bid not accepted. Try another load." |

### 9.2 Admin Panel — Dispatcher View

| Section | State | Content |
|---|---|---|
| Load Queue | Empty | "No loads pending review" |
| Load Queue | Loaded | Table with load cards, approval actions |
| Active Loads | Empty | "No active loads" |
| Active Loads | Loaded | Live map with load locations, driver positions |
| Exception Queue | Empty | "No exceptions" |
| Exception Queue | Loaded | Alert cards with exception details + resolve actions |
| AI Recommendations | Empty | "No AI recommendations available" |
| AI Recommendations | Loaded | Ranked load-driver matches with scores |

---

## 10. LOAD BOARD NOTIFICATION CHAIN

| Event | From Surface | To Surface | Notification Type | Payload |
|---|---|---|---|---|
| AI recommends load | Dispatcher AI | Driver App | Push + In-App | `{load_id, origin, destination, rate, match_score}` |
| Dispatcher approves load | Admin Panel | Driver App | Push + In-App | `{load_id, status: available}` |
| Driver bids on load | Driver App | Admin Panel | In-App | `{load_id, driver_id, bid_amount}` |
| Dispatcher assigns load | Admin Panel | Driver App | Push + In-App | `{load_id, driver_name, pickup_date}` |
| Driver accepts load | Driver App | Admin Panel | In-App | `{load_id, driver_name, eta_pickup}` |
| Driver reports exception | Driver App | Admin Panel | Push + In-App + Email | `{load_id, exception_type, details}` |
| POD submitted | Driver App | Admin Panel + Customer | Push + In-App + Email | `{load_id, pod_url, delivered_at}` |
| Load completed | Admin Panel | All surfaces | In-App | `{load_id, status: completed}` |
