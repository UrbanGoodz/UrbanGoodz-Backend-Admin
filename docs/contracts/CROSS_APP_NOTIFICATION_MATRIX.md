# CROSS-APP NOTIFICATION MATRIX

**Version:** 3.9  
**Last Updated:** 2026-07-16  
**Purpose:** Every notification event across all apps — trigger, recipients, channels, payload, and destination screen.

---

## NOTIFICATION INFRASTRUCTURE

- **Push Provider:** Firebase Cloud Messaging (FCM)
- **Realtime:** Pusher (Laravel Echo)
- **Channel Pattern:** `private-{user_id}-{user_type}` (customer, vendor, driver, admin)
- **In-App Store:** `notifications` table, per-user scoped
- **Email:** Via Laravel Mailable (queued)

---

## MARKETPLACE ORDER EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `order_created` | Customer places order via `POST /api/v1/customer/order/place` | Vendor, Customer | Yes | Yes | Yes | No | `{order_id, store_name, items_summary, total, created_at}` | `order.id` | Vendor: Order detail page (New tab); Customer: Order confirmation screen |
| 2 | `vendor_accepted` | Vendor accepts order via `PUT /api/v1/vendor/update-order-status` with `status=confirmed` | Customer | Yes | Yes | Yes | No | `{order_id, store_name, estimated_prep_time}` | `order.id` | Customer: Order tracking screen |
| 3 | `vendor_rejected` | Vendor rejects order via `PUT /api/v1/vendor/update-order-status` with `status=canceled` | Customer | Yes | Yes | Yes | Yes | `{order_id, store_name, reason}` | `order.id` | Customer: Order list (Rejected tab) |
| 4 | `order_preparing` | Vendor updates status to `preparing` | Customer | Yes | Yes | Yes | No | `{order_id, store_name, estimated_time}` | `order.id` | Customer: Order tracking screen (preparation progress) |
| 5 | `ready_for_pickup` | Vendor updates status to `ready_for_pickup` | Customer, Driver Pool | Yes | Yes | Yes | No | `{order_id, store_name, pickup_address, items_count}` | `order.id` | Driver: Available jobs list; Customer: Waiting for driver screen |
| 6 | `driver_assigned` | Admin/System assigns driver | Customer, Driver | Yes | Yes | Yes | No | `{order_id, driver_name, driver_phone, pickup_address, dropoff_address}` | `order.id` | Driver: New job offer; Customer: Driver info card |
| 7 | `driver_accepted` | Driver accepts job via `PUT /api/v1/delivery-man/accept-order` | Customer | Yes | Yes | Yes | No | `{order_id, driver_name, driver_phone, eta}` | `order.id` | Customer: Live tracking with ETA |
| 8 | `driver_arrived_pickup` | Driver arrives at pickup via status update | Customer, Vendor | Yes | Yes | Yes | No | `{order_id, driver_name}` | `order.id` | Vendor: Ready to handoff notification; Customer: Driver at store |
| 9 | `picked_up` | Driver picks up order via status update | Customer, Vendor | Yes | Yes | Yes | No | `{order_id, driver_name}` | `order.id` | Customer: En route tracking; Vendor: Order picked up confirmation |
| 10 | `in_transit` | Driver updates to `in_transit` | Customer | Yes | Yes | Yes | No | `{order_id, eta, driver_location}` | `order.id` | Customer: Live map tracking |
| 11 | `driver_arrived_dropoff` | Driver arrives at dropoff | Customer | Yes | Yes | Yes | No | `{order_id, driver_name}` | `order.id` | Customer: Delivery imminent notification |
| 12 | `delivered` | Driver marks delivered | Customer, Vendor, Driver | Yes | Yes | Yes | Yes | `{order_id, delivered_at, tip}` | `order.id` | Customer: Delivery confirmation + rate prompt; Vendor: Delivered tab; Driver: Earnings update |
| 13 | `order_completed` | Customer confirms or auto-confirm after timeout | Customer, Vendor, Driver | Yes | Yes | Yes | No | `{order_id, total, vendor_earning, driver_earning}` | `order.id` | All: Order summary/completed view |
| 14 | `order_cancelled` | Any actor cancels order | All affected parties | Yes | Yes | Yes | Yes | `{order_id, cancelled_by, reason, refund_amount}` | `order.id` | All: Cancelled order detail with reason |
| 15 | `payment_authorized` | Gateway authorization succeeds | Customer | Yes | Yes | Yes | No | `{order_id, amount, payment_method}` | `order.id` | Customer: Payment confirmed indicator |
| 16 | `payment_captured` | Payment captured after delivery | Customer, Vendor | Yes | Yes | Yes | Yes | `{order_id, amount, platform_fee, vendor_payout}` | `order.id` | Vendor: Earnings updated |
| 17 | `payment_failed` | Payment fails at gateway | Customer | Yes | Yes | Yes | Yes | `{order_id, error_message, retry_url}` | `order.id` | Customer: Payment retry screen |
| 18 | `refund_issued` | Admin issues refund | Customer, Vendor | Yes | Yes | Yes | Yes | `{order_id, refund_amount, reason}` | `order.id` | Customer: Refund confirmation; Vendor: Adjustment in earnings |
| 19 | `vendor_earning_posted` | Earning record created on completion | Vendor | Yes | Yes | Yes | No | `{earning_id, order_id, amount, platform_fee, net_earning}` | `vendor_earning.id` | Vendor: Earnings wallet screen |
| 20 | `driver_earning_posted` | Earning record created on completion | Driver | Yes | Yes | Yes | No | `{earning_id, order_id, amount, platform_fee, net_earning}` | `driver_earning.id` | Driver: Earnings summary screen |
| 21 | `payout_approved` | Admin approves payout request | Vendor, Driver | Yes | Yes | Yes | Yes | `{payout_id, amount, method}` | `payout.id` | Vendor/Driver: Payout history (Approved tab) |
| 22 | `payout_paid` | Payout disbursement completed | Vendor, Driver | Yes | Yes | Yes | Yes | `{payout_id, amount, disbursed_at}` | `payout.id` | Vendor/Driver: Payout history (Paid tab) |

---

## PARCEL / COURIER EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 23 | `parcel_created` | Customer creates parcel order | Customer | Yes | Yes | Yes | No | `{parcel_id, pickup, dropoff, estimated_cost}` | `parcel_order.id` | Customer: Parcel tracking screen |
| 24 | `parcel_paid` | Customer pays for parcel | Customer, System | Yes | Yes | Yes | No | `{parcel_id, amount}` | `parcel_order.id` | Customer: Parcel active confirmation |
| 25 | `parcel_assigned` | Driver assigned to parcel | Customer, Driver | Yes | Yes | Yes | No | `{parcel_id, driver_name, pickup_address}` | `parcel_order.id` | Driver: New job; Customer: Driver info |
| 26 | `parcel_delivered` | Driver delivers parcel | Customer | Yes | Yes | Yes | Yes | `{parcel_id, delivered_at}` | `parcel_order.id` | Customer: Delivery confirmation |
| 27 | `parcel_return_required` | Return needed | Customer, Driver | Yes | Yes | Yes | No | `{parcel_id, reason}` | `parcel_order.id` | Driver: Return job; Customer: Return status |

---

## ORDER ANYWHERE EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 28 | `order_anywhere_submitted` | Customer submits request | Customer, Admin | Yes | Yes | Yes | Yes | `{request_id, description, budget}` | `order_anywhere_request.id` | Admin: New request queue; Customer: Request status screen |
| 29 | `order_anywhere_quoted` | Admin/vendor provides quote | Customer | Yes | Yes | Yes | Yes | `{request_id, quoted_amount, breakdown}` | `order_anywhere_request.id` | Customer: Quote approval screen |
| 30 | `order_anywhere_approved` | Customer approves quote | Admin, Vendor, Driver | Yes | Yes | Yes | No | `{request_id, approved_amount}` | `order_anywhere_request.id` | Admin: Ready for payment; Driver: Available job |
| 31 | `order_anywhere_paid` | Customer pays | Admin, Driver | Yes | Yes | Yes | Yes | `{request_id, amount, payment_method}` | `order_anywhere_request.id` | Admin: Assign driver queue; Driver: Purchase card available |
| 32 | `order_anywhere_driver_assigned` | Driver assigned | Customer, Driver | Yes | Yes | Yes | No | `{request_id, driver_name, purchase_card}` | `order_anywhere_request.id` | Driver: Job detail with purchase card |
| 33 | `order_anywhere_purchased` | Driver completes purchase | Customer, Admin | Yes | Yes | Yes | No | `{request_id, purchase_amount, receipt_url}` | `order_anywhere_request.id` | Admin: Reconciliation queue; Customer: Receipt viewable |
| 34 | `order_anywhere_delivered` | Driver delivers to customer | Customer | Yes | Yes | Yes | Yes | `{request_id, delivered_at}` | `order_anywhere_request.id` | Customer: Delivery confirmation |

---

## LOAD BOARD EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 35 | `load_recommended` | AI recommends load to driver | Driver | Yes | Yes | Yes | No | `{load_id, origin, destination, rate, equipment_match}` | `load_board_load.id` | Driver: Job discovery/recommended loads screen |
| 36 | `load_assigned` | Dispatcher assigns load to driver | Driver, Admin | Yes | Yes | Yes | No | `{load_id, driver_name, pickup_date, origin, destination}` | `load_board_load.id` | Driver: Active jobs; Admin: Load detail |
| 37 | `load_exception` | Driver reports exception on load | Admin, Dispatcher | Yes | Yes | Yes | Yes | `{load_id, exception_type, details, driver_name}` | `load_board_load.id` | Admin: Exception queue; Dispatcher: Ops dashboard alert |
| 38 | `load_pod_submitted` | Driver submits proof of delivery | Admin, Customer | Yes | Yes | Yes | No | `{load_id, pod_url, delivered_at}` | `load_board_load.id` | Admin: Reconciliation; Customer: Delivery confirmed |
| 39 | `load_completed` | Load fully closed | Admin, Dispatcher | Yes | Yes | Yes | No | `{load_id, total_miles, total_pay, completed_at}` | `load_board_load.id` | Admin: Load detail (Completed tab) |

---

## FASHION FIT EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 40 | `fashion_fit_profile_saved` | Customer creates/updates fit profile | Customer | Yes | Yes | Yes | No | `{profile_uuid, name, measurements_count}` | `fashion_fit_profile.uuid` | Customer: Fit profile detail |
| 41 | `fashion_fit_photos_uploaded` | Customer uploads reference photos | Customer, System | Yes | Yes | Yes | No | `{profile_uuid, photo_count}` | `fashion_fit_profile.uuid` | Customer: Photo gallery; System: Trigger AI analysis |
| 42 | `fashion_fit_analysis_complete` | AI measurement extraction finishes | Customer | Yes | Yes | Yes | No | `{profile_uuid, measurements}` | `fashion_fit_profile.uuid` | Customer: Review measurements screen |
| 43 | `fashion_fit_measurements_approved` | Customer approves measurements | System | Yes | Yes | Yes | No | `{profile_uuid}` | `fashion_fit_profile.uuid` | System: Ready for stylist matching |
| 44 | `stylist_request_received` | Customer submits request to stylists | Vendor (stylist) | Yes | Yes | Yes | No | `{request_uuid, description, profile_summary}` | `fashion_fit_request.uuid` | Vendor: New stylist request notification; Stylist requests list |
| 45 | `stylist_estimate_received` | Vendor submits estimate to customer | Customer | Yes | Yes | Yes | Yes | `{request_uuid, vendor_name, amount, items[], timeline}` | `fashion_fit_request.uuid` | Customer: Estimate review screen |
| 46 | `stylist_estimate_accepted` | Customer accepts an estimate | Vendor (stylist) | Yes | Yes | Yes | No | `{request_uuid, customer_name, amount}` | `fashion_fit_request.uuid` | Vendor: Accepted request; Begin work |
| 47 | `stylist_clarification_needed` | Vendor requests more info from customer | Customer | Yes | Yes | Yes | No | `{request_uuid, vendor_name, question}` | `fashion_fit_request.uuid` | Customer: Request detail with reply input |
| 48 | `fashion_fit_completed` | Measurement/garment delivered | Customer, Vendor | Yes | Yes | Yes | Yes | `{request_uuid, delivered_at}` | `fashion_fit_request.uuid` | Customer: Completion confirmation; Vendor: Earnings posted |

---

## SERVICE BOOKING EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 49 | `service_booking_created` | Customer creates booking | Vendor (provider) | Yes | Yes | Yes | Yes | `{booking_id, service_name, date, customer_name}` | `service_booking.id` | Vendor: New booking notification; Bookings list |
| 50 | `service_quote_received` | Vendor submits quote | Customer | Yes | Yes | Yes | Yes | `{booking_id, vendor_name, amount, breakdown}` | `service_booking.id` | Customer: Quote review screen |
| 51 | `service_quote_accepted` | Customer accepts quote | Vendor | Yes | Yes | Yes | No | `{booking_id, customer_name}` | `service_booking.id` | Vendor: Confirmed booking detail |
| 52 | `service_booking_paid` | Customer pays | Vendor, Admin | Yes | Yes | Yes | Yes | `{booking_id, amount, payment_method}` | `service_booking.id` | Vendor: Payment confirmed; Admin: Revenue |
| 53 | `service_booking_confirmed` | Customer confirms completion | Vendor, Admin | Yes | Yes | Yes | No | `{booking_id, completed_at}` | `service_booking.id` | Vendor: Completed bookings; Admin: Revenue |
| 54 | `service_booking_cancelled` | Customer/vendor cancels | Other party | Yes | Yes | Yes | Yes | `{booking_id, cancelled_by, reason}` | `service_booking.id` | Other party: Cancelled booking detail |
| 55 | `service_booking_rescheduled` | Customer reschedules | Vendor | Yes | Yes | Yes | No | `{booking_id, old_date, new_date}` | `service_booking.id` | Vendor: Updated booking calendar |
| 56 | `service_booking_reviewed` | Customer leaves review | Vendor | Yes | Yes | Yes | No | `{booking_id, rating, comment}` | `service_booking.id` | Vendor: Review notification; Profile reviews |

---

## DRIVER DISPATCH & CAPABILITY EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 57 | `dispatch_notification_received` | System dispatches new alert | Driver | Yes | Yes | Yes | No | `{notification_id, type, title, body, entity_type, entity_id}` | `dispatch_notification.id` | Driver: Dispatch notifications inbox |
| 58 | `dispatch_notification_read` | Driver reads notification | Admin/Dispatcher | No | Yes | Yes | No | `{notification_id}` | `dispatch_notification.id` | Admin: Read status indicator |
| 59 | `capability_profile_updated` | Driver updates capability profile | Admin | No | Yes | Yes | No | `{driver_id, updated_fields[]}` | `driver.id` | Admin: Driver profile review queue |
| 60 | `certification_uploaded` | Driver uploads new certification | Admin | Yes | Yes | Yes | No | `{driver_id, cert_type, document_url}` | `driver_certification.id` | Admin: Certification review queue |
| 61 | `certification_expiring` | Certification expiring within 30 days | Driver, Admin | Yes | Yes | Yes | Yes | `{driver_id, cert_type, expires_at}` | `driver_certification.id` | Driver: Renewal prompt; Admin: Compliance dashboard |

---

## BUSINESS COURIER EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 62 | `courier_job_assigned` | Dispatcher assigns courier job | Driver | Yes | Yes | Yes | No | `{job_id, pickup_address, dropoff_address, packages_count}` | `business_courier_job.id` | Driver: New courier job notification |
| 63 | `courier_job_accepted` | Driver accepts courier job | Admin | Yes | Yes | Yes | No | `{job_id, driver_name, eta_pickup}` | `business_courier_job.id` | Admin: Job accepted indicator |
| 64 | `courier_pickup_confirmed` | Driver confirms pickup with proof | Admin, Customer | Yes | Yes | Yes | No | `{job_id, proof_url, packages_count}` | `business_courier_job.id` | Admin: In-transit status; Customer: Package picked up |
| 65 | `courier_delivery_confirmed` | Driver confirms delivery with proof | Admin, Customer | Yes | Yes | Yes | Yes | `{job_id, proof_url, delivered_at}` | `business_courier_job.id` | Admin: Completed; Customer: Delivered |
| 66 | `courier_exception_reported` | Driver reports exception | Admin, Dispatcher | Yes | Yes | Yes | Yes | `{job_id, exception_type, details, proof_url}` | `business_courier_job.id` | Admin: Exception queue; Dispatcher: Ops alert |

---

## EARNINGS & PAYOUT EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 67 | `vendor_earning_posted` | Vendor earning created on order completion | Vendor | Yes | Yes | Yes | No | `{earning_id, order_id, gross_amount, platform_fee, net_amount}` | `vendor_earning.id` | Vendor: Earnings wallet |
| 68 | `driver_earning_posted` | Driver earning created on delivery completion | Driver | Yes | Yes | Yes | No | `{earning_id, order_id, delivery_fee, tip, net_amount}` | `driver_earning.id` | Driver: Earnings wallet |
| 69 | `payout_approved` | Admin approves payout request | Vendor/Driver | Yes | Yes | Yes | Yes | `{payout_id, amount, method, approved_at}` | `payout.id` | Vendor/Driver: Payout history |
| 70 | `payout_processing` | Payout being processed | Vendor/Driver | No | Yes | Yes | No | `{payout_id, amount}` | `payout.id` | Vendor/Driver: Processing indicator |
| 71 | `payout_paid` | Payout completed | Vendor/Driver | Yes | Yes | Yes | Yes | `{payout_id, amount, disbursed_at, reference}` | `payout.id` | Vendor/Driver: Paid confirmation |
| 72 | `payout_failed` | Payout failed | Vendor/Driver, Admin | Yes | Yes | Yes | Yes | `{payout_id, amount, failure_reason}` | `payout.id` | Vendor/Driver: Error + retry; Admin: Failed payouts queue |
| 73 | `payout_reversed` | Admin reverses payout | Vendor/Driver | Yes | Yes | Yes | Yes | `{payout_id, amount, reason}` | `payout.id` | Vendor/Driver: Reversal notice |

---

## SYSTEM & PLATFORM EVENTS

| # | EVENT NAME | TRIGGER | RECIPIENT ROLES | PUSH | IN-APP | REALTIME | EMAIL | PAYLOAD | ENTITY ID | EXPECTED SCREEN DESTINATION |
|---|---|---|---|---|---|---|---|---|---|---|
| 74 | `new_notification` | Any notification created | Respective user | Yes | Yes | Yes | No | `{notification_id, type, title, body, entity_type, entity_id}` | `notification.id` | Respective app notification bell/badge |
| 75 | `opportunity_available` | New earn-money opportunity posted | Customer | Yes | Yes | Yes | No | `{opportunity_id, title, reward, type}` | `opportunity.id` | Customer: Opportunities feed |
| 76 | `opportunity_claimed` | Customer claims opportunity | Admin | No | Yes | Yes | No | `{opportunity_id, customer_name}` | `opportunity.id` | Admin: Opportunity detail |
| 77 | `event_new` | New event posted | Customer | Yes | Yes | Yes | Yes | `{event_id, title, date, location}` | `event.id` | Customer: Events feed |
| 78 | `event_interest_expressed` | Customer expresses interest in event | Admin, Vendor | No | Yes | Yes | No | `{event_id, customer_name}` | `event.id` | Admin: Event registrations; Vendor: Opportunity |
| 79 | `reel_featured` | Reel featured by system | Creator | Yes | Yes | Yes | No | `{reel_id, title}` | `reel.id` | Creator: Featured reels tab |
| 80 | `creator_application_submitted` | Creator submits application | Admin | Yes | Yes | Yes | No | `{application_id, creator_name}` | `creator_application.id` | Admin: Applications queue |
| 81 | `creator_application_approved` | Admin approves creator application | Creator | Yes | Yes | Yes | Yes | `{application_id}` | `creator_application.id` | Creator: Application status screen |
| 82 | `identity_verification_complete` | Identity verification finishes | Customer | Yes | Yes | Yes | No | `{profile_id, status}` | `identity_profile.id` | Customer: Identity profile screen |
| 83 | `account_security_alert` | Suspicious activity detected | Customer/Vendor/Driver | Yes | Yes | Yes | Yes | `{alert_type, details, action_url}` | N/A | All: Security alert banner |

---

## NOTIFICATION TEMPLATE SUMMARY

### Push Notification Titles

| Event | Title Template |
|---|---|
| `order_created` | "New order from {customer_name}" (Vendor) / "Order #{order_id} placed!" (Customer) |
| `vendor_accepted` | "{store_name} confirmed your order" |
| `vendor_rejected` | "{store_name} couldn't fulfill your order" |
| `driver_assigned` | "{driver_name} is heading to pick up your order" |
| `driver_accepted` | "Driver {driver_name} accepted your delivery" |
| `picked_up` | "Your order has been picked up!" |
| `in_transit` | "Your order is on its way! ETA: {eta}" |
| `delivered` | "Your order has been delivered!" |
| `order_cancelled` | "Order #{order_id} has been cancelled" |
| `payment_failed` | "Payment failed for order #{order_id}" |
| `refund_issued` | "Refund of ${amount} has been issued" |
| `payout_paid` | "Payout of ${amount} has been sent!" |
| `load_recommended` | "New load: {origin} → {destination} — ${rate}" |
| `stylist_request_received` | "New fit request from {customer_name}" |
| `stylist_estimate_received` | "Estimate received from {vendor_name}: ${amount}" |
| `courier_job_assigned` | "New courier job: {pickup} → {dropoff}" |
| `courier_delivery_confirmed` | "Delivery confirmed for job #{job_id}" |
| `dispatch_notification_received` | "{title}" (dynamic) |

### In-App Notification Badge Rules

| App | Badge Behavior |
|---|---|
| Customer | Increment on each new notification; clear when viewing notification list |
| Vendor | Increment for new orders, messages, reviews; clear per category |
| Driver | Increment for new jobs, dispatch alerts, earnings updates; clear per tab |
| Admin | Increment for pending approvals, exceptions, disputes; clear on dashboard load |

---

## EMAIL NOTIFICATION TEMPLATES

| Event | Email Subject | Recipient |
|---|---|---|
| `vendor_rejected` | "Your order from {store_name} was cancelled" | Customer |
| `delivered` | "Your order has been delivered — Order #{order_id}" | Customer |
| `order_cancelled` | "Order #{order_id} has been cancelled" | Customer, Vendor |
| `payment_failed` | "Payment failed — Action required" | Customer |
| `refund_issued` | "Refund of ${amount} processed — Order #{order_id}" | Customer |
| `payment_captured` | "Payment of ${amount} received — Order #{order_id}" | Vendor |
| `payout_approved` | "Your payout request of ${amount} has been approved" | Vendor, Driver |
| `payout_paid` | "Payout of ${amount} sent — {method}" | Vendor, Driver |
| `payout_failed` | "Payout failed — Please update your payment method" | Vendor, Driver |
| `certification_expiring` | "Your {cert_type} expires on {date} — Please renew" | Driver |
| `order_anywhere_submitted` | "Order Anywhere request #{id} submitted" | Customer |
| `order_anywhere_quoted` | "Quote received: ${amount} — Action needed" | Customer |
| `service_booking_created` | "New service booking #{id} from {customer}" | Vendor |
| `service_quote_received` | "Quote from {vendor}: ${amount}" | Customer |
| `service_booking_reviewed` | "New {rating}-star review from {customer}" | Vendor |
| `fashion_fit_estimate_received` | "Estimate from {vendor}: ${amount}" | Customer |

---

## NOTIFICATION RETRY & FAILURE HANDLING

| Scenario | Behavior |
|---|---|
| FCM send fails | Retry 3 times with exponential backoff (1s, 5s, 30s); log failure; mark notification as `push_failed` |
| Pusher realtime fails | No retry; notification still saved to in-app store and email queue |
| Email send fails | Laravel queue retry (3 attempts); log to `failed_jobs` table |
| User has no FCM token | Skip push; still save in-app notification and send email if applicable |
| User has notifications disabled | Skip push; still save in-app notification; skip email unless critical |
