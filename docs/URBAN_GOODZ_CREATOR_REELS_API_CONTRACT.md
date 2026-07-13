# Urban Goodz Creator Commerce and Reels API Contract

All paths are relative to `/api/v1`. JSON validation errors use HTTP 422 unless an established Reels request uses the legacy `errors[]` envelope. Vendor routes require `vendor.api` and `actch:vendor_app`; customer mutation routes require `auth:api`; Admin routes require `auth:admin`.

## Customer and feed

| Feature | Method | Path | Request | Success | Privacy and errors | Controller/tests |
|---|---|---|---|---|---|---|
| Approved creators | GET | `/customer/creators` | `limit`, optional `handle` | Paginated approved profiles, 200 | Pending/suspended creators excluded | `CreatorCommerceController@profiles`; `CreatorCommerceContractTest` |
| Reel feed | GET | `/customer/reels/list` | pagination, module/store filters | Published, moderated reels with creator and tags, 200 | Draft/rejected/removed excluded | `ReelController@index`; Reels module tests |
| Reel details/playback | GET | `/customer/reels/details?reel_id=` | optional `stream=1`, Range header | Metadata or range-capable media stream, 200/206 | Only active moderated content | `ReelController@show`; Reels module tests |
| Like | POST | `/customer/reels/like` | `reel_id` | Toggle and count, 200 | Authenticated user; unique engagement | `ReelController@like` |
| Report | POST | `/customer/reels/report` | `reel_id`, reason, details | Report id, 201 | Authenticated; throttled 10/minute | `CreatorCommerceController@report` |
| Start attribution | POST | `/customer/reels/attribution` | `reel_id`, `tag_id` | UUID attribution and tagged target, 201 | Tag must belong to reel; expires after 24 hours | `CreatorCommerceController@beginAttribution` |
| Convert order | POST | `/customer/reels/attribution/order` | attribution UUID, order id | Commission record, 200 | Customer and store ownership; one conversion only | `CreatorCommerceController@convertOrder`; contract test |

## Vendor creator tools

| Feature | Method | Path | Notes |
|---|---|---|---|
| Creator profile | GET/PUT | `/vendor/creator/profile` | Creates pending profile; handle is unique; Admin approval required to publish. |
| Own reels | GET | `/vendor/reel/list` | Store-scoped pagination, status, search, engagement counts. |
| Upload draft | POST multipart | `/vendor/reel/store` | Description, thumbnail, video, visibility, JSON `tags`; actual MIME/size validated; tags must be products owned by the Vendor store. |
| Details/update/delete | GET/PUT/DELETE | `/vendor/reel/details`, `/vendor/reel/update`, `/vendor/reel/delete` | `reel_id`; every mutation is store scoped. |
| Submit publication | POST | `/vendor/reel/{reel}/publish` | Requires approved creator, media, and authorized tags; enters `pending_review`. |
| Unpublish | POST | `/vendor/reel/{reel}/unpublish` | Owner only; returns reel to draft. Direct status activation is rejected. |
| Revenue | GET | `/vendor/creator/earnings` | Status totals and paginated immutable earnings. |

## Admin moderation

| Feature | Method | Path | Notes |
|---|---|---|---|
| Creators | GET | `/admin/creator-commerce/creators` | Paginated approval queue. |
| Creator decision | PUT | `/admin/creator-commerce/creators/{profile}/status` | approved/pending/suspended/rejected; suspension removes public reels. |
| Reels queue | GET | `/admin/creator-commerce/reels` | Filter by moderation status. |
| Moderate reel | PUT | `/admin/creator-commerce/reels/{reel}/moderate` | approve/reject/remove; only approval makes content public. |
| Reports | GET/PUT | `/admin/creator-commerce/reports[/{report}]` | Review and optionally remove reported content. |

## Status and money rules

Publication is `draft -> pending_review -> published`, with `rejected`, `removed`, or Vendor-initiated return to `draft`. A customer attribution is `initiated -> converted`; conversion requires the authenticated customer's order at the reel's store. The server derives gross order value and commission; the client cannot submit financial totals. Creator earnings are initially pending so existing Admin payout controls remain authoritative. Profile/moderation/conversion events create persisted Vendor notifications.

The former `CreatorCommerceTesterController` JSON-file routes are not registered. Tester/release traffic has no static or file-backed reel fallback.
