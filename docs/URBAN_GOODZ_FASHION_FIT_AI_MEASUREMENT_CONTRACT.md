# Urban Goodz Fashion Fit AI Measurement Contract

Base URL: `/api/v1`  
Authentication: Customer routes use `auth:api`; provider routes require Bearer token, `vendorType`, `vendor.api`, and the Vendor-app feature gate; Admin routes use `auth:admin`. Photos are stored on the private local disk and are streamed only after ownership/access checks.

The external AI adapter is server-side only. Required deployment keys are `FASHION_FIT_AI_ENABLED`, `FASHION_FIT_AI_ENDPOINT`, `FASHION_FIT_AI_API_KEY`, `FASHION_FIT_AI_MODEL`, and `FASHION_FIT_AI_MODEL_VERSION`. The key is never returned to Flutter. When configuration is absent or the provider fails, the analysis becomes `failed`; the system does not fabricate measurements.

## Customer endpoints

| Method | Path | Request | Response/status | Authorization and privacy | Controller |
|---|---|---|---|---|---|
| GET | `/fashion-fit/requirements` | None | Required/optional views, units, guidance, consent version | Authenticated customer | `FashionFitCustomerController@requirements` |
| GET | `/fashion-fit/providers` | Optional `limit` | Approved active Fashion Fit providers | Returns allowlisted provider/store fields | `providers` |
| GET | `/fashion-fit/profiles` | None | Owned paginated profiles | `customer_id` from token | `index` |
| POST | `/fashion-fit/profiles` | `name`, `units`, `calibration_height`, consent flags; optional fit preferences | `201` profile in `photos_pending` | AI consent required; IP stored only as SHA-256 hash | `store` |
| GET | `/fashion-fit/profiles/{uuid}` | UUID | Owned profile, analyses and measurement results | Cross-customer UUID returns `404` | `show` |
| PUT | `/fashion-fit/profiles/{uuid}` | Name/units/height/preferences | Updated owned profile | Cross-customer denied | `update` |
| DELETE | `/fashion-fit/profiles/{uuid}` | None | Profile/photos deleted | Active provider work blocks deletion; grants revoked | `destroy` |
| POST | `/fashion-fit/profiles/{uuid}/consent` | AI, measurement, and photo sharing booleans | Versioned consent row | Revoking AI consent revokes provider grants | `updateConsent` |
| POST | `/fashion-fit/profiles/{uuid}/photos` | Multipart `photo`, `view`, `confirmed_for_upload` | `201` private photo metadata | MIME/size/dimensions and ownership validated; explicit confirmation required | `uploadPhoto` |
| GET | `/fashion-fit/profiles/{profileUuid}/photos/{photoUuid}` | None | Private streamed image | Owner only; no public URL | `downloadPhoto` |
| DELETE | same photo path | None | File and record deleted | Owner only | `deletePhoto` |
| POST | `/fashion-fit/profiles/{uuid}/analyses` | None | `202` queued analysis | Active AI consent plus required accepted views | `submitAnalysis` |
| GET | `/fashion-fit/profiles/{profileUuid}/analyses/{analysisUuid}` | None | Uploaded/processing/needs_retake/completed/failed and structured results | Owner only | `analysis` |
| PUT | `/fashion-fit/profiles/{profileUuid}/measurements/{id}` | Positive `value`, `unit` | Corrected measurement marked `manual_correction` | Owner only; profile returns to customer review | `correctMeasurement` |
| POST | `/fashion-fit/profiles/{uuid}/approve` | None | Approved profile | Completed measurements required; all confirmation flags resolved | `approve` |
| GET | `/fashion-fit/requests` | None | Owned request/history list | Owner only | `requests` |
| POST | `/fashion-fit/requests` | Approved `profile_uuid`, approved `vendor_id`, service/garment, sharing flags; optional notes/budget/date | `201` provider request and access grant | Approved profile and separate measurement/photo consent enforced | `createRequest` |
| GET | `/fashion-fit/requests/{uuid}` | None | Request and estimates | Owner only | `requestDetails` |
| POST | `/fashion-fit/requests/{uuid}/estimates/{id}/decision` | `accept` or `decline` | Updated request/estimate | Owner only; decline revokes access | `decideEstimate` |
| POST | `/fashion-fit/requests/{uuid}/staged-payment` | None | Persisted `staged_test` payment reference | Accepted request only; no live processing | `stagedPayment` |
| POST | `/fashion-fit/requests/{uuid}/revoke` | None | Access revoked | Owner only; immediate provider denial | `revoke` |

## Provider endpoints

| Method | Path | Request/response | Privacy and transition checks |
|---|---|---|---|
| GET/PUT | `/vendor/fashion-fit/profile` | Get or submit bio/categories/credentials | Updates return provider to `pending`; Admin approval required for work access |
| GET | `/vendor/fashion-fit/requests` | Assigned request list | Approved provider and matching `vendor_id` only |
| GET | `/vendor/fashion-fit/requests/{uuid}` | Request, approved measurements, estimates | Active request grant, approved master profile, and measurement permission required |
| GET | `/vendor/fashion-fit/requests/{requestUuid}/photos/{photoUuid}` | Private streamed image | Active grant plus request photo flag plus separately active customer photo consent; audit event recorded |
| POST | `/vendor/fashion-fit/requests/{uuid}/clarification` | Type and safe message | Assigned provider only; emits customer notification without logging message body |
| POST | `/vendor/fashion-fit/requests/{uuid}/estimates` | Amount, timeline, notes, requirements | Assigned approved provider; revision stored; customer notified |
| POST | `/vendor/fashion-fit/requests/{uuid}/status` | `in_progress`, `completed`, or `canceled` | Legal transition and payment gate; completion/cancel revokes access |
| GET | `/vendor/fashion-fit/earnings` | Read-only total and transaction history | Only transactions whose payable request belongs to provider |

## Admin endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/fashion-fit/dashboard` | Counts by status and redacted provider configuration state/model version |
| GET | `/admin/fashion-fit/requests` | Oversight with optional status filter |
| GET | `/admin/fashion-fit/audits` | Consent/access/analysis/workflow audit metadata; no images or measurements in metadata |
| POST | `/admin/fashion-fit/providers/{vendor}/status` | Approve or suspend the Fashion Fit provider profile without changing the whole Vendor account |

## External provider request and response

The HTTP adapter sends multipart private photo bytes, analysis/profile opaque UUIDs, height, unit, model, and model version. It never sends customer contact information. Required structured response:

```json
{
  "status": "completed",
  "model": "provider-model",
  "model_version": "version",
  "overall_confidence": 0.91,
  "measurements": [
    {
      "name": "shoulder_width",
      "value": 17.25,
      "unit": "in",
      "confidence": 0.93,
      "requires_confirmation": false
    }
  ],
  "retake_requirements": []
}
```

`status=needs_retake` requires view-specific retake reasons. Names are allowlisted, units are `in|cm`, values must be positive, and confidence is `0..1`. Invalid JSON/schema produces a failed analysis and no measurement rows.

## Persisted states and notifications

- Analysis: `uploaded -> processing -> completed|needs_retake|failed`.
- Profile: `photos_pending -> analysis_pending -> customer_review -> approved`; retake/failure states are explicit.
- Request: `submitted -> provider_review -> estimate_submitted -> accepted -> in_progress -> completed`; decline/cancel revoke access.
- Notifications are persisted for analysis completion/failure/retake, provider clarification, estimate, customer decision, payment, and provider status changes.
- Payment is persisted through `UrbanGoodzPaymentTransaction` using `staged_test`; live payments are not enabled by this contract.

## Tests and remaining external gate

Fixture contract tests validate completed, retake, and malicious/invalid provider responses. Source security tests verify customer/provider/Admin middleware, private disk storage, separate photo consent, and revocation. The only external AI gate is a configured provider deployment plus real guided-photo validation/accuracy run; provider absence is a visible failed state, never a mock fallback.
