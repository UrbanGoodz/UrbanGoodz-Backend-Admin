# Urban Goodz — Owner Decisions Required

Decisions only D'Andre Good can make. Technical questions agents can resolve from code are deliberately excluded.

## Blocking now

### OD-1 — Pull the current tester builds? **URGENT**
The distributed APKs (Shopper 3.9.0(5), Vendor 3.9.3(10), Driver 3.9.1(7)) predate every P0 fix. The Vendor build authenticates through a hardcoded mock (`vendor@urbangoodz.com`, 700 ms fake delay); the Shopper build still fabricates success on several screens. Testers on these builds are exercising fake behaviour, and their feedback will not be meaningful.

Options: (a) notify testers and pause until rebuilt, (b) let testing continue on known-stale builds, (c) rebuild immediately and redistribute.
**PM recommendation: (c), with (a) in the interim.**

### OD-2 — Approve deploying the 34 undeployed backend commits?
Production runs `3037ce7e`; the branch is now `46f2cc1`, **40 backend commits ahead**, including admin login repairs, an infinite-redirect fix, a fix for a 500 on non-role-1 admin logins, and a block of security work that was on `origin` and not in the PM brief: fail-closed CAPTCHA, restored module authorization and license activation checks, and normalized admin login errors. Every one of those security fixes is currently **not running in production**. Deployment requires a fresh backup and a proven rollback path — the existing backup `/home/urbakkej/backups/urban_goodz_deploy_20260722_074053` has **never been test-restored**.

### OD-3 — Who owns Production Operations?
No agent owns deployment, backups, rollback, monitoring, cron/queue, Firebase/SMTP/SMS, payment configuration, or Play Store operational readiness. Phase 5 cannot start without this. Options: staff a 4th agent, reassign a lane after its P0 work closes, or you own it directly.

### OD-4 — Who owns the Shopper app?
`claude-shopper-p0-recovery` @ `33e8c4b` has 4 unpushed P0 commits and no owning agent. Shopper is the primary revenue surface.

## Required before PRODUCTION_READY

- **OD-5** Enable Stripe live mode and approve real financial transactions. *Not to be enabled by any agent under any circumstances.*
- **OD-6** Approve Google Play production rollout and staged-rollout percentage.
- **OD-7** Approve commission percentages, pricing, and payout schedules.
- **OD-8** Approve age-restricted category launch (THC/CBD, Liquor) — likely needs regulatory and legal review beyond engineering.
- **OD-9** Approve launch markets/cities and service zones.
- **OD-10** Approve legal policy wording (privacy, terms). Requires attorney review; engineering cannot certify compliance.
- **OD-11** Approve the tester roster and Wave 1 distribution list.
- **OD-12** Final production release approval.

## Standing constraints in force

No agent will: enable live payments, deploy, force-push, run destructive database commands, write to the production database, or print or commit secrets. These stay in force regardless of blanket work authorization — they are release gates, not permission friction.
