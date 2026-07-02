# Fashion Measurement API Endpoints

## Ready
- `GET /api/urban-goodz/fashion/measurements/profile`
- `POST /api/urban-goodz/fashion/measurements/profile`
- `POST /api/urban-goodz/fashion/measurements/request`
- `POST /api/urban-goodz/fashion/measurements/photos`
- `GET /api/urban-goodz/fashion/measurements/{id}`
- `GET /api/vendor/urban-goodz/fashion/measurements`
- `GET /api/vendor/urban-goodz/fashion/measurements/{id}`
- `POST /api/vendor/urban-goodz/fashion/measurements/{id}/review`
- `POST /api/vendor/urban-goodz/fashion/measurement-settings`
- `GET /api/admin/urban-goodz/fashion/measurements`
- `GET /api/admin/urban-goodz/fashion/measurements/{id}`
- `POST /api/admin/urban-goodz/fashion/measurement-settings`
- `POST /api/admin/urban-goodz/fashion/measurements/{id}/privacy-status`
- `POST /api/admin/urban-goodz/fashion/measurements/{id}/status`

## Partial
- The same measurement endpoints are also available under `/api/v1/...` for existing route compatibility.

## Tester-safe
- Tester requests force `payment_status=waived` and `payment_required=false`.

## Exact next action
- Add auth policy decisions for customer/admin APIs before production.
