# Fashion Measurement Backend

## Ready
- `urban_goodz_measurement_requests` migration stores manual and photo-assisted measurement intake.
- `App\Models\MeasurementRequest` includes tester-safe defaults, casts, fillable fields, and allowed status constants.
- Free tester mode keeps payment waived and never marks unpaid requests as paid.

## Partial
- Photo upload endpoint stores tester placeholder paths only.
- Customer profile endpoint stores the latest manual measurement request shape rather than a separate profile table.

## Tester-safe
- Fees default to `0 USD`.
- Face blur status is tracked but set to `unavailable` until production processing exists.

## Exact next action
- Run migration on a safe local/test database and seed `MeasurementRequestSeeder`.
