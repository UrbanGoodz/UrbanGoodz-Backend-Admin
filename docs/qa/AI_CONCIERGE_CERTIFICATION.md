# AI actions + Concierge certification

Existing coverage re-run: 337 AI-related tests pass (execution engine, action registry, role isolation,
admin AI authorization, platform safety, copilot, workforce, driver AI, creator AI security).

## Verified by reading code + tests
- Guest `ai-concierge/query` is text-only: the service writes conversation rows and never calls the action/tool
  registries, so a guest or a prompt-injected query cannot trigger an action.
- Signed-in context (orders, payments) is scoped to the caller's id; guests get none (`buildCustomerContext`).
- Guest history is read only with `customer_id IS NULL AND session_id = ?`, never a customer's rows.
- Provider failure returns 503, not a fake success (tested). Throttles: 15/min guests, 60/min signed in, 10/min video start.

## Defects fixed
1. **Any signed-in customer could end any video-avatar room by id** (`video-avatar/{id}/end`). Owner is now
   recorded at start and checked at end (404 otherwise).
2. **Every start opened a new billed Tavus room** (double tap, app restart). A user's live room is now resumed.
   Test: `ConciergeVideoAvatarOwnershipTest`.

## Open / notes
- Guest session ids are client-chosen (max 64 chars): a guessable id lets another guest read that guest's chat
  history. Mitigation: have the app generate a random UUID per install (server could enforce a minimum length).
- Guest conversations are never pruned (only signed-in ones are); an open endpoint at 15/min/IP grows the table. Add a
  retention job.
- Ownership uses the cache: production needs a shared cache store (Redis), not `file` on multiple servers.
- Tavus was faked in tests; not called for real. Digital humans (non-Tavus) are a separate lane (C2).
