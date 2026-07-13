# Urban Goodz Vendor API Contract

Baseline: `0576608b4aec286fe362596db84b3c80f872251e`  
Base URL: `https://admin.urbangoodzdelivery.com/api/v1`  
Discovery source: `routes/api/v1/api.php`, `routes/api/urban_goodz_measurements.php`, Vendor controllers, `VendorTokenIsValid`, and the RC2 Flutter repository.

## Authentication and common responses

Login is the only P0 call that does not require a token. Authenticated calls require `Authorization: Bearer <vendor auth_token>` and `vendorType: owner` (or `employee` for supported employee sessions). The `vendor.api` middleware resolves the token, rejects missing/revoked tokens with `401`, rejects an invalid/missing Vendor type with `403`, rejects inactive Vendor/store accounts with `403`, and injects the authenticated Vendor. `actch:vendor_app` additionally enforces the Vendor-app feature switch.

Validation errors use `{"errors":[{"code":"field","message":"..."}]}` (usually `403` in the inherited API). Missing owned resources use `404`. Successful mutations generally use `{"message":"..."}` with `200`. No mobile request may provide a Vendor/store ID to widen authorization; ownership is derived from the token.

## P0 endpoint contract

| Feature | Method and exact path | Required / optional input | Success response | Controller | Tests | RC2 |
|---|---|---|---|---|---|---|
| Login | `POST /auth/vendor/login` | Required: `email`, `password` (min 6), `vendor_type=owner` | `200` with `token`, `zone_wise_topic`, `module_type`; some subscription states wrap these in `subscribed` | `Auth\VendorLoginController@login` | source/security + Flutter client | Integrated |
| Password reset request | `POST /auth/vendor/forgot-password` | Existing controller: Vendor email/phone identity | Existing reset response; validation `403` | `Auth\VendorPasswordResetController@reset_password_request` | Existing suite | API available; UI pending |
| Password reset verify | `POST /auth/vendor/verify-token` | Reset identity and token | Existing verification response | `Auth\VendorPasswordResetController@verify_token` | Existing suite | API available; UI pending |
| Password reset submit | `PUT /auth/vendor/reset-password` | Reset identity/token and new password fields | Existing reset response | `Auth\VendorPasswordResetController@reset_password_submit` | Existing suite | API available; UI pending |
| Profile | `GET /vendor/profile` | Auth headers only | Vendor identity plus one formatted `stores` object, schedules/module, order counts and read-only wallet/earnings totals | `Vendor\VendorController@get_profile` | source/security | Integrated |
| Logout | `POST /vendor/logout` | Auth headers only | `200 {"message":"Logged out"}`; server token is cleared | `Vendor\VendorController@logout` | source/security + Flutter client | Integrated |
| Store availability | `POST /vendor/update-active-status` | Auth headers only | Existing message and toggled active state | `Vendor\VendorController@active_status` | Existing suite | Integrated |
| Items | `GET /vendor/get-items-list` | Optional: `limit` (25), `offset` (1), `type`, `category_id`, `search` | `{total_size,limit,offset,items:[...]}`; items are restricted to authenticated store | `Vendor\VendorController@get_items` | source ownership | Integrated |
| Item details | `GET /vendor/item/details/{id}` | Path `id` | Formatted item or error; authenticated-store ownership hardening remains required | `Vendor\ItemController@get_item` | Existing suite | API available; ownership blocker |
| Item status | `GET /vendor/item/status` | Controller fields: item identifier and status | Message | `Vendor\ItemController@status` | Existing suite | API available |
| Stock update | `PUT /vendor/item/stock-update` | Required: `product_id`, `current_stock`; variation products also require `type` and per-variation price/stock fields | `200` message; `404` for a product outside the authenticated store | `Vendor\ItemController@stock_update` | source ownership | Integrated |
| Current orders | `GET /vendor/current-orders` | Auth headers only | Array of non-POS active orders for authenticated store | `Vendor\VendorController@get_current_orders` | source ownership | Integrated |
| All orders | `GET /vendor/all-orders` | Auth headers only | Array of authenticated-store orders | `Vendor\VendorController@get_all_orders` | source ownership | Integrated |
| Completed orders | `GET /vendor/completed-orders` | Required by controller: pagination/status query as applicable (`limit`, `offset`, `status`) | Paginated completed/history response | `Vendor\VendorController@get_completed_orders` | Existing suite | Integrated through all-orders |
| Canceled orders | `GET /vendor/canceled-orders` | Optional pagination | Paginated canceled response | `Vendor\VendorController@get_canceled_orders` | Existing suite | Integrated through all-orders |
| Order details | `GET /vendor/order-details?order_id={id}` | Required: `order_id` | Owned order details/customer fields; cross-Vendor returns `404` | `Vendor\VendorController@get_order_details` | source ownership | Mapping integrated |
| Order status | `PUT /vendor/update-order-status` | Required: `order_id`, `status`; `reason` when canceled; optional `processing_time`, `otp`, max 5 `order_proof` files. Status is one of `confirmed,processing,handover,delivered,canceled` | `200` message; illegal transition `409`; cross-Vendor/missing `404` | `Vendor\VendorController@update_order_status` | source transition/ownership + Flutter mapping | Integrated |
| FCM token | `PUT /vendor/update-fcm-token` | Required by controller: `fcm_token` | Message | `Vendor\VendorController@update_fcm_token` | Flutter client | Integrated after login |
| Notifications | `GET /vendor/notifications` | Auth headers only | Vendor notification array | `Vendor\VendorController@get_notifications` | Existing suite | Integrated (list/count) |
| Earnings/wallet | `GET /vendor/earning-info` | Auth headers only | Read-only earning summary | `Vendor\VendorController@get_earning_data` | Existing suite | Integrated via profile/earnings repository |
| Earnings report | `GET /vendor/earning-report` | Controller pagination/filter query | Authenticated-store earning report | `Vendor\StoreEarningReportController@getEarningReport` | Existing suite | API available |
| Withdrawal methods | `GET /vendor/get-withdraw-method-list` | Auth headers only | Configured method list | `Vendor\WithdrawMethodController@withdraw_method_list` | Existing suite | Integrated |
| Withdrawal history | `GET /vendor/get-withdraw-list` | Auth headers only | Owned request list with Pending/Approved/Denied status | `Vendor\VendorController@withdraw_list` | Existing suite | Integrated |
| Withdrawal request | `POST /vendor/request-withdraw` | Required: positive numeric `amount`, method `id`; optional method-defined fields | `200` message; insufficient available balance `403`; missing method `404` | `Vendor\VendorController@request_withdraw` | source limits | Integrated |
| Fashion Fit list | `GET /vendor/urban-goodz/fashion/measurements` | Auth headers; optional `limit` | Paginated requests assigned to authenticated Vendor only | `Vendor\UrbanGoodzFashionMeasurementController@index` | privacy source | Integrated |
| Fashion Fit details | `GET /vendor/urban-goodz/fashion/measurements/{id}` | Path `id` | Assigned request; photo identifiers/paths only when customer consent is true and privacy review is approved | same `show` | privacy source | Integrated |
| Fashion Fit review | `POST /vendor/urban-goodz/fashion/measurements/{id}/review` | Optional: nonnegative `vendor_review_fee`, `tailor_notes`, allowed `review_status`, allowed `measurement_status` | Updated assigned request with protected photo fields | same `review` | privacy source | Integrated |
| Fashion Fit fee setting | `POST /vendor/urban-goodz/fashion/measurement-settings` | Required nonnegative `vendor_review_fee` | Tester-mode acknowledgement; no payment authority granted | same `settings` | privacy source | API available |

Order transition map enforced for Vendor control: `pending -> confirmed|canceled`, `confirmed -> processing`, `processing -> handover`, `handover -> delivered`. Delivery remains additionally constrained by OTP, self-delivery/takeaway, pickup, cancellation, and payment rules already in the controller. Driver-owned `picked_up` is not a Vendor transition.

## Extended existing Vendor surface

All paths below inherit `vendor.api` and `actch:vendor_app`; controller validation is authoritative. They are existing APIs, not newly duplicated Urban Goodz endpoints.

| Area | Existing methods and paths | Controller |
|---|---|---|
| Profile/business | `PUT /vendor/update-profile`, `PUT /vendor/update-announcment`, `PUT /vendor/update-basic-info`, `PUT /vendor/update-business-setup`, `PUT /vendor/update-bank-info`, `DELETE /vendor/remove-account` | `VendorController`, `BusinessSettingsController` |
| Schedule | `POST /vendor/schedule/store`, `DELETE /vendor/schedule/{store_schedule}` | `BusinessSettingsController` |
| Orders/payment | `GET /vendor/order`, `PUT /vendor/update-order-amount`, `PUT /vendor/send-order-otp`, `POST /vendor/make-collected-cash-payment`, `POST /vendor/make-wallet-adjustment`, `GET /vendor/wallet-payment-list` | `VendorController` |
| Campaign | `GET /vendor/get-basic-campaigns`, `PUT /vendor/campaign-join`, `PUT /vendor/campaign-leave` | `VendorController` |
| Reports | `GET /vendor/get-expense`, `GET /vendor/get-tax-report`, `GET /vendor/get-disbursement-report`, `GET /vendor/subscription-transaction` | `ReportController`, `SubscriptionController` |
| Withdraw profiles | `GET /vendor/withdraw-method/list`, `POST /vendor/withdraw-method/store`, `POST /vendor/withdraw-method/make-default`, `DELETE /vendor/withdraw-method/delete` | `WithdrawMethodController` |
| Catalog reference | `GET /vendor/unit`, `GET /vendor/attributes`, `GET /vendor/categories/`, `GET /vendor/categories/childes/{category_id}`, `GET /vendor/categories/category-wise-products/{id}` | matching Vendor controllers |
| Item authoring | `POST /vendor/item/store`, `PUT /vendor/item/update`, `DELETE /vendor/item/delete`, `POST /vendor/item/search`, `GET /vendor/item/reviews`, `PUT /vendor/item/reply-update`, `GET /vendor/item/recommended`, `GET /vendor/item/organic`, `GET /vendor/item/pending/item/list`, `GET /vendor/item/requested/item/view/{id}`, `GET /vendor/item/stock-limit-list` | `ItemController` |
| Add-ons | `GET /vendor/addon/`, `POST /vendor/addon/store`, `PUT /vendor/addon/update`, `GET /vendor/addon/status`, `DELETE /vendor/addon/delete` | `AddOnController` |
| Coupons | `GET /vendor/coupon/list`, `GET /vendor/coupon/view`, `GET /vendor/coupon/view-without-translate`, `POST /vendor/coupon/store`, `POST /vendor/coupon/update`, `POST /vendor/coupon/status`, `POST /vendor/coupon/delete`, `POST /vendor/coupon/search` | `CouponController` |
| Banners | `GET /vendor/banner/`, `POST /vendor/banner/store`, `PUT /vendor/banner/update`, `GET /vendor/banner/status`, `DELETE /vendor/banner/delete`, `GET /vendor/banner/edit/{id}` | `BannerController` |
| Advertising | `GET /vendor/advertisement/`, `GET /vendor/advertisement/details/{id}`, `DELETE /vendor/advertisement/delete/{id}`, `POST /vendor/advertisement/store`, `POST /vendor/advertisement/update/{id}`, `PUT /vendor/advertisement/status`, `POST /vendor/advertisement/copy-add-post` | `AdvertisementController` |
| Delivery staff | `POST /vendor/delivery-man/store`, `GET /vendor/delivery-man/list`, `GET /vendor/delivery-man/preview`, `GET /vendor/delivery-man/status`, `POST /vendor/delivery-man/update/{id}`, `DELETE /vendor/delivery-man/delete`, `POST /vendor/delivery-man/search` | `DeliveryManController` |
| POS | `GET /vendor/pos/orders`, `POST /vendor/pos/place-order`, `GET /vendor/pos/customers` | `POSController` |
| Messaging | `GET /vendor/message/list`, `GET /vendor/message/search-list`, `GET /vendor/message/details`, `POST /vendor/message/send` | `ConversationController` |

Public/subscription Vendor endpoints also exist at `GET /vendor/package-view`, `POST /vendor/business_plan`, `POST /vendor/subscription/payment/api`, `POST /vendor/package-renew`, `POST /vendor/cancel-subscription`, and `GET /vendor/check-product-limits`; these use their existing subscription controller rules.

## Known contract limitations

- The schema has boolean Vendor/store status fields, so "rejected" is not independently distinguishable from other inactive states without an additional authoritative status/reason field. Clients must display the backend message and never guess.
- Password reset APIs exist, but the Vendor Flutter UI is not yet wired.
- Notification list exists, but a dedicated read/unread mutation was not found and remains a P0 full-experience gap.
- There is no Vendor-controlled `picked_up` transition. Pickup belongs to the Driver flow; clients must refresh the backend-owned state.
- Product create/edit/delete APIs exist, but ownership validation across every item mutation still requires dedicated coverage.
- Fashion Fit AI photo analysis, Creator/Reels commerce attribution, and Service Booking end-to-end contracts remain P0 blockers outside this verified Vendor endpoint matrix.
