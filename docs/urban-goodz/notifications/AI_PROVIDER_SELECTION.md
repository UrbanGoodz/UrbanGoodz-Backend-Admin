# AI provider selection — exact path, and what switching to Gemini requires

## 1. The selection path (only one exists)

| # | File:line | What happens |
| --- | --- | --- |
| 1 | `.env` → `AI_PROVIDER` | Operator input. Absent in most environments. |
| 2 | `config/urban_goodz_ai.php:8` — `'provider' => env('AI_PROVIDER', 'openai')` | Shipped default is **`openai`**. |
| 3 | `app/Services/UrbanGoodz/AI/AIProviderManager.php:27` — `resolve()` | Lower-cases/trims the configured name. |
| 4 | `app/Services/UrbanGoodz/AI/AIProviderManager.php:31-36` — `match ($provider)` | `openai`/`openrouter` → `OpenAICompatibleProvider`; `gemini` → `GeminiProvider`; `disabled` and **anything unrecognised** → `DisabledAIProvider` (fails closed). |
| 5 | `app/Services/UrbanGoodz/UrbanGoodzAIService.php:14` — `__construct()` | Resolves once per service instance; every `chat()`/`classifyIntent()` call goes through that one provider. |

**Consequence:** with `AI_PROVIDER` unset, the platform builds
`OpenAICompatibleProvider('openai')`. `GEMINI_API_KEY` is read at
`config/urban_goodz_ai.php` provider-config level but the Gemini provider is
never instantiated, so **the Gemini key is not in use**. A key is never allowed
to imply a provider — that is deliberate (a leaked or stale key must not
silently redirect traffic).

## 2. Making it explicit and testable

`AIProviderManager` now exposes:

- `AIProviderManager::SUPPORTED` — the allowlist (`openai`, `openrouter`,
  `gemini`, `disabled`).
- `selectedProvider(?string $provider = null): string` — the name that will be
  used for the next call.
- `selectionDiagnostics(?string $provider = null): array` — credential-free
  report: `selected`, `source` (`explicit_argument` | `config_ai_provider` |
  `shipped_default`), `is_supported`, `shipped_default`, `is_shipped_default`,
  `configured`, `model`, `switch_requires[]`.

`config/urban_goodz_ai.php` gained `'default_provider' => 'openai'` so a test can
assert that the shipped default has not silently changed.

Covered by `tests/Unit/UrbanGoodzAIProviderContractTest.php`
(`test_provider_selection_is_explicit_and_defaults_to_openai`,
`test_gemini_key_presence_alone_does_not_select_gemini`,
`test_selection_diagnostics_report_source_and_switch_requirements`).

## 3. The production default was NOT changed

`AI_PROVIDER` still defaults to `openai`. Switching the live provider is an
owner decision with cost and behavioural consequences, so it is surfaced, not
performed.

### What switching to Gemini requires — OWNER ACTION

1. In production `.env`:
   ```
   AI_PROVIDER=gemini
   GEMINI_API_KEY=<the key — never paste it into a ticket>
   GEMINI_MODEL=gemini-flash-latest
   ```
2. `php artisan config:clear` (and `php artisan config:cache` if the deploy
   caches config). Without this the old value stays live.
3. Restrict the key in Google Cloud console
   (**APIs & Services → Credentials → the key → API restrictions**) to
   **Generative Language API** only, and set an **Application restriction** of
   *IP addresses* limited to the production server IP. A server-side key with
   "None" restriction is usable by anyone who obtains it.
4. Confirm afterwards:
   `app(\App\Services\UrbanGoodz\AI\AIProviderManager::class)->selectionDiagnostics()`
   should report `selected => "gemini"`, `source => "config_ai_provider"`,
   `configured => true`, `model => "gemini-flash-latest"`.

### Behavioural differences to accept before switching

- Response shape and prompt-following differ from GPT-4o-mini; the JSON-only
  prompts in `UrbanGoodzAIService::classifyIntent()` and
  `NotificationAIController::personalizeWithAI()` already fall back to
  deterministic rules on unparseable output, so a switch degrades rather than
  breaks — but output quality changes.
- Free-tier Gemini has per-minute and per-day quotas; a 429 becomes
  `error_code = provider_error` and the caller falls back.

## 4. Gemini model default

`gemini-flash-latest` is now the default in both
`config/urban_goodz_ai.php` and `GeminiProvider::DEFAULT_MODEL`.

| Model | Result observed |
| --- | --- |
| `gemini-2.5-flash` | HTTP 404 — "no longer available to new users" |
| `gemini-2.0-flash` | HTTP 429 on the free tier |
| `gemini-flash-latest` | works; Google repoints the alias as snapshots retire |

Keep the default on a `-latest` alias. Pinning a numbered snapshot re-creates the
404 the next time Google retires one.

`.env.example` previously declared `GEMINI_API_KEY`/`GEMINI_MODEL` **twice**;
the second block silently overrode the first. It is now declared once.
