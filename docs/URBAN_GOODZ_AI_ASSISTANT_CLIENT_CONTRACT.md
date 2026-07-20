# Urban Goodz AI Assistant Client API Contract

This document outlines the API contracts for the Customer, Driver, Vendor, and Business AI Assistants, alongside Admin AI Operations endpoints.

---

## 1. Customer AI Companion ("Ask Urban Goodz Genie")

Unified endpoints designed to support natural language queries, conversation tracking, order status, and custom request processing.

### Customer Query
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/customer/query`
- **Authentication:** Bearer token (`auth:api`)
- **Required Inputs:**
  - `query` (string, required, max 2000 characters)
  - `context` (array, optional)
- **Response Format:**
  ```json
  {
    "success": true,
    "data": {
      "intent": "check_order_status",
      "response_text": "Your order #100234 is currently in transit. The driver is 4 minutes away.",
      "suggestions": ["Show route map", "Contact driver"],
      "confidence": 0.95
    }
  }
  ```

### Customer Conversation History
- **Method:** `GET`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/customer/history`
- **Authentication:** Bearer token (`auth:api`)
- **Response Format:**
  ```json
  {
    "success": true,
    "data": {
      "current_page": 1,
      "data": [
        {
          "id": 12,
          "query_text": "Where is my parcel?",
          "response_text": "Your parcel is picked up.",
          "status": "resolved",
          "created_at": "2026-07-19T18:00:00.000000Z"
        }
      ]
    }
  }
  ```

### Order Anywhere Request
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/customer/order-anywhere`
- **Authentication:** Bearer token (`auth:api`)
- **Required Inputs:**
  - `query` (string, required, max 2000 characters)
  - `context` (array, optional)
- **Response Format (If store details missing):**
  ```json
  {
    "success": true,
    "store_found": false,
    "parsed": {
      "item_details": "3 tacos"
    },
    "missing": ["store_name", "delivery_address"],
    "follow_up_prompts": ["Which store would you like these from?", "Where should we deliver them?"],
    "suggestions": ["Taco Bell", "Chipotle"]
  }
  ```
- **Response Format (If request created):**
  ```json
  {
    "success": true,
    "request_id": 42,
    "request_number": "OAW-XYZ123",
    "status": "requested"
  }
  ```

### Smart Reorder Comparison
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/customer/smart-reorder`
- **Authentication:** Bearer token (`auth:api`)
- **Required Inputs:**
  - `reference` (string, required, e.g., "last friday", "order #10022")
- **Response Format:**
  ```json
  {
    "success": true,
    "original_order": {
      "id": 10022,
      "number": "10022",
      "date": "Jul 15, 2026",
      "total": 45.50
    },
    "items": [
      {
        "item_id": 4,
        "name": "Burger Combo",
        "quantity": 2,
        "price": 15.00,
        "available": true,
        "price_changed": false
      }
    ],
    "unavailable": [],
    "price_changes": [],
    "cart_ready": true
  }
  ```

### Delivery ETA Prediction
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/customer/delivery-eta`
- **Authentication:** Bearer token (`auth:api`)
- **Required Inputs:**
  - `order_id` (integer, required)
- **Response Format:**
  ```json
  {
    "success": true,
    "order": {
      "id": 10022,
      "order_number": "10022",
      "status": "picked_up",
      "store": "Main Street Cafe"
    },
    "eta": {
      "predicted_duration_minutes": 18,
      "traffic_overhead_minutes": 3,
      "confidence": 0.88
    }
  }
  ```

---

## 2. Driver AI Assistant

Endpoints to analyze driver metrics, optimize delivery sequences, verify tasks, and check opportunities.

### Driver Daily Summary
- **Method:** `GET`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/driver/daily-summary`
- **Authentication:** Bearer token (`auth:api` / `dm` guard)
- **Response Format:**
  ```json
  {
    "success": true,
    "summary": "You have completed 4 runs today earning $85.50. High demand detected in Zone B."
  }
  ```

### Route Sequence Optimization
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/driver/route-optimization`
- **Authentication:** Bearer token (`auth:api` / `dm` guard)
- **Required Inputs:**
  - `route_id` (integer, required)
  - `preference` (string, optional: "distance", "time", "earnings")
- **Response Format:**
  ```json
  {
    "success": true,
    "optimization": {
      "original_distance": 22.4,
      "optimized_distance": 18.1,
      "suggested_stops": [
        {"sequence": 1, "package_id": 401, "address": "Stop A"},
        {"sequence": 2, "package_id": 402, "address": "Stop B"}
      ]
    }
  }
  ```

### Package Pickup & Delivery Verification (Image Scan)
- **Method:** `POST`
- **Routes:** 
  - Pickup: `/api/v1/urban-goodz/cross-app/ai/driver/verify-package`
  - Delivery: `/api/v1/urban-goodz/cross-app/ai/driver/verify-delivery`
- **Authentication:** Bearer token (`auth:api` / `dm` guard)
- **Required Inputs:**
  - `package_id` (integer, required)
  - `photo` (string, base64 data, required)
  - `gps_lat` (numeric, required)
  - `gps_lng` (numeric, required)
  - `recipient_name` (string, optional - delivery only)
  - `dropoff_instructions` (string, optional - delivery only)
- **Response Format:**
  ```json
  {
    "success": true,
    "verification": {
      "package_id": 122,
      "label_match": true,
      "items_verified": true,
      "condition_assessment": "good",
      "delivery_verified": true,
      "delivery_proof": {
        "proof_id": "DP-992",
        "timestamp": "2026-07-19T20:25:00Z"
      }
    }
  }
  ```

### Load Board Recommendations
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/driver/load-recommendations`
- **Authentication:** Bearer token (`auth:api` / `dm` guard)
- **Response Format:**
  ```json
  {
    "success": true,
    "loads": [
      {
        "id": 55,
        "load_number": "LD-8821",
        "origin": "Houston, TX",
        "destination": "Austin, TX",
        "payout": 450.00,
        "rate_per_mile": 2.80,
        "equipment": "cargo_van",
        "weight": 2200,
        "distance": 162
      }
    ],
    "ai_match": {
      "score": 85,
      "reasons": ["Matches cargo_van equipment", "High rate per mile"]
    }
  }
  ```

### Earnings Comparison & Benchmarks
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/driver/earnings-comparison`
- **Authentication:** Bearer token (`auth:api` / `dm` guard)
- **Required Inputs:**
  - `period` (string, optional: "week", "month", "year")
- **Response Format:**
  ```json
  {
    "success": true,
    "period": "week",
    "total_earnings": 650.00,
    "total_tips": 120.00,
    "avg_per_order": 22.50,
    "active_hours": 28.5,
    "earnings_per_hour": 22.81,
    "platform_avg_per_order": 19.50,
    "vs_platform": "above",
    "percentile": 78
  }
  ```

---

## 3. Vendor AI Success Assistant

Endpoints focused on sales optimization, menu pricing, demand analysis, and alerts.

### Vendor Daily Brief
- **Method:** `GET`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/vendor/daily-brief`
- **Authentication:** Bearer token (`auth:api` / `vendor` guard)
- **Response Format:**
  ```json
  {
    "success": true,
    "brief": "Yesterday revenue was $1,200 (+5%). 3 items are low in inventory. Suggested promotion: 10% off Dessert Combos."
  }
  ```

### Store Alert Stream
- **Method:** `GET`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/vendor/alerts`
- **Authentication:** Bearer token (`auth:api` / `vendor` guard)
- **Response Format:**
  ```json
  {
    "success": true,
    "alerts": [
      {
        "type": "low_inventory",
        "item_name": "Tenderloin Steak",
        "remaining": 2,
        "action_required": "Restock soon"
      }
    ]
  }
  ```

### Dynamic Pricing & Promos
- **Method:** `GET`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/vendor/pricing`
- **Authentication:** Bearer token (`auth:api` / `vendor` guard)
- **Response Format:**
  ```json
  {
    "success": true,
    "pricing": {
      "recommendations": [
        {"item_id": 14, "name": "Classic Burger", "current_price": 9.99, "recommended_price": 10.49, "reason": "High demand, low competitive supply"}
      ]
    }
  }
  ```

---

## 4. Business Portal AI Operations Assistant

Scoped endpoints mapping logistics flows and manifests for business client IDs.

### Manifest File Import
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/business/manifest/import`
- **Authentication:** Bearer token (`auth:api` / `business` guard)
- **Required Inputs:**
  - `file` (binary/multipart, required)
  - `source_type` (string, required: "csv", "excel", "pdf", "email")
  - `auto_create_packages` (boolean, optional)
- **Response Format:**
  ```json
  {
    "success": true,
    "packages_parsed": 12,
    "auto_created": true,
    "created_packages": [
      {"id": 901, "tracking_number": "TRK-00192", "delivery_address": "Street X"}
    ]
  }
  ```

### Package Pool Routing
- **Method:** `POST`
- **Route:** `/api/v1/urban-goodz/cross-app/ai/business/packages/group`
- **Authentication:** Bearer token (`auth:api` / `business` guard)
- **Required Inputs:**
  - `status` (string, optional)
  - `region` (string, optional)
- **Response Format:**
  ```json
  {
    "success": true,
    "total_packages": 48,
    "groups": [
      {
        "group_id": 1,
        "packages": [901, 902, 903],
        "suggested_vehicle": "cargo_van",
        "estimated_distance": 14.5
      }
    ],
    "unassigned": []
  }
  ```

---

## 5. Admin AI Operations & Workforce Management

View and control workforce tasks, actions, prospects, and recommendations.

### AI Task Board
- **Method:** `GET`
- **Route:** `/admin/urban-goodz/ai-operations/workforce/tasks`
- **Authentication:** Session Admin (`auth:admin`)
- **Optional Query Parameter:**
  - `id` (integer, optional - filters list to render the exact matching task)
- **Response Format:** Renders the task dashboard list.

### AI Approvals Panel
- **Method:** `GET`
- **Route:** `/admin/urban-goodz/ai-operations/workforce/approvals`
- **Authentication:** Session Admin (`auth:admin`)
- **Optional Query Parameter:**
  - `id` (integer, optional - filters list to render the exact matching approval)
- **Response Format:** Renders the approvals panel.

### AI Copilot Accept/Dismiss Recommendations
- **Method:** `POST`
- **Routes:**
  - Accept: `/admin/urban-goodz/ai-copilot/{id}/accept`
  - Dismiss: `/admin/urban-goodz/ai-copilot/{id}/dismiss`
- **Authentication:** Session Admin (`auth:admin`)
- **Response Format:**
  ```json
  {
    "success": true,
    "message": "Recommendation accepted successfully."
  }
  ```
