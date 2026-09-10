# Decisions — ANSWERED 2026-09-10

Answers to `DECISIONS_NEEDED_20260904.md`, given directly by D'Andre.
Both items are now unblocked. Do not re-ask.

---

## 1. Voice clone quality — PARTIAL PASS

| Persona | Verdict | Action |
|---|---|---|
| **Monique** | **APPROVED** | Ship as-is. No further generation needed. |
| **Skylar** | **REJECTED — choppy** | Regenerate. |

D'Andre's words: *"they are kind of choppy, if you are referring to Skylar, Monique is fine."*

### What this tells us

The round-2 repetition-penalty fix worked for Monique but not Skylar. The
anomaly flagged in the previous doc — `skylar_1` running 48s for a short
sentence — was not an isolated bad sample; choppiness is characteristic of
the Skylar voice across the set.

Since the same pipeline and the same fix produced an acceptable Monique,
the variable is **Skylar's reference audio or her per-voice generation
params**, not the pipeline. Start there rather than re-tuning globally,
which would risk regressing Monique.

### Still open (was gated behind this)

Delivery mechanism — **hosted inference vs. pre-generated phrase library**
— is still unanswered and still needs D'Andre. It is now the only thing
blocking voice from reaching production, and it only needs to be settled
for Monique to start shipping; Skylar can follow once regenerated.

Full technical writeup: `UrbanGoodz2026-Revised/docs/HANDOFF_VOICE_CLONING_20260903.md`

---

## 2. Taxonomy implementation plan — APPROVED

D'Andre's words: *"The taxonomy plan is approved."*

`docs/TAXONOMY_IMPLEMENTATION_PLAN_DRAFT_20260904.md` is approved as
drafted. Implementation may begin.

### The finding that plan rests on

`urban_goodz_business_types` (the "18 business types" registry) has **zero
rows and zero real usage** anywhere in the app. It is designed scaffolding
that was never wired up. The live system is the older `modules` /
`categories` tables.

Approval of the plan is therefore also approval of whichever of those two
the plan selected as the base. Whoever implements should re-read that
section before writing code — building on the empty registry would mean
migrating a live catalog onto a table nothing currently reads.

---

## Provenance

Recorded by the 2026-09-10 session, from direct user instruction in chat.
Both answers came from D'Andre, not inferred.
