# Urban Goodz Parallel Flutter Handoff

This document details the backend contract, API schemas, and integration points for the parallel Flutter development track.

---

## 1. Environment & Auth

- **Branch:** `adminpanel-v39-backend-sprint`
- **Starting Commit SHA:** `58b983617becc4a3cce8c232c918a59ea3e414c4`
- **Base URL:** `http://localhost/api/v1` or `https://admin.urbangoodzdelivery.com/api/v1`
- **Authentication Headers:**
  ```http
  Authorization: Bearer <auth_token>
  Accept: application/json
  ```

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

---

## 4. Notifications Expectations
- **Queue/Channel:** Firebase Cloud Messaging (FCM) is configured for push notifications.
- **Key Triggers:**
  - `load_assigned` -> Driver push notification.
  - `load_status_changed` -> Customer and Vendor updates.
  - `stylist_bid_received` -> Customer push notification.
