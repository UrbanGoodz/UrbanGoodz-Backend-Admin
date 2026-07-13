# Urban Goodz Service Booking API Contract

All paths are relative to `/api/v1`. Customer mutations require `auth:api`; Vendor routes require `vendor.api` plus `actch:vendor_app`; Admin routes require `auth:admin`. Money fields are integer minor units and are never accepted from a completion/status client action.

Supported categories: barber, hair stylist, braider, nail technician, makeup artist, mobile mechanic, photographer, DJ, contractor, tax professional, home health provider, and personal trainer.

## Customer

| Feature | Method/path | Required fields | Response/errors | Authorization/tests |
|---|---|---|---|---|
| Discover | `GET customer/service-bookings/providers` | optional category/pagination | Approved active providers, real services and availability | Public read; pending/suspended excluded |
| Provider details | `GET customer/service-bookings/providers/{provider}` | provider id | Profile/services/slots, 200 or 404 | Only verified active provider |
| Create | `POST customer/service-bookings` | provider/service, future time, location mode; optional notes/location | Persisted request, 201 | Auth customer; service ownership, category, offered location, schedule and collision validated |
| History/details | `GET customer/service-bookings[/{booking}]` | — | Owned records/events | Cross-customer access is 404 |
| Accept quote | `POST .../{booking}/accept-quote` | — | `quoted -> accepted` | Customer owner only |
| Sandbox payment | `POST .../{booking}/payment` | opaque payment token, idempotency key | Redacted accepted/failure response | Throttled; gateway key server-side; provider response structurally validated; amount server-derived |
| Confirm | `POST .../{booking}/confirm` | — | `accepted -> confirmed` | Required payment must already be accepted |
| Reschedule/cancel | `POST .../{booking}/reschedule|cancel` | new future time or reason | Audited status | Legal transitions only |
| Review | `POST .../{booking}/review` | rating 1–5, optional comment | Review, 201 | Owner and completed booking only; one review |

## Provider/Vendor

| Feature | Method/path | Rules |
|---|---|---|
| Profile | `GET/PUT vendor/service-bookings/profile` | Vendor-owned profile; approval starts pending. |
| Services CRUD | `GET/POST/PUT/DELETE vendor/service-bookings/services[/{service}]` | Approved provider; required category/duration/pricing; cross-provider access denied; active-booking deletion rejected. |
| Availability | `PUT vendor/service-bookings/availability` | Atomically replaces validated weekly slots with timezone. |
| Requests | `GET vendor/service-bookings/bookings[/{booking}]` | Provider ownership required. |
| Quote | `POST .../bookings/{booking}/quote` | Server stores amount, deposit, schedule, notes; only requested booking. |
| Status | `POST .../bookings/{booking}/status` | accepted/declined/en_route/started/completed; customer must accept a quote; illegal transitions return 409. |
| Earnings | `GET vendor/service-bookings/earnings` | Server-calculated gross, fee, and provider payable after completion. |

## Admin, statuses, payments, and events

Admin endpoints under `admin/service-bookings` expose provider approval/suspension, booking oversight, earnings, and immutable audit events. Status progression is `requested -> quoted -> accepted -> confirmed -> en_route -> started -> completed`; decline, cancellation, and reschedule branches are explicitly checked. Completion requires accepted payment when money is due, creates the Provider earning once, and generates Customer/Vendor notifications.

The configured sandbox gateway makes an actual authenticated server-side request. Missing endpoint/secret, provider rejection, invalid structured response, or timeout fails closed with a redacted 502; no local static-success fallback exists. Deployment must configure only sandbox credentials until live payments are separately authorized.

Automated coverage: `ServiceBookingContractTest` validates the outbound sandbox contract/failure path, ownership guards, transition enforcement, server-derived ledger, notifications, and the complete required category list.
