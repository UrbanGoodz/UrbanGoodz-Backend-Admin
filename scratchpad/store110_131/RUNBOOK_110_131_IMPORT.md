# Urban Goodz — 22 Home-Based Stores (110-131): Bulk Import Runbook

**Prepared by:** Lane B (data ops) — 2026-09-04
**Status:** Excel import files built + validated against the controller's actual checks. **Upload NOT yet run** (requires authenticated admin session; backend DB writes are coordinated with the backend teammate).

---

## Files (in `scratchpad/store110_131/`)
- `urban_goodz_home_based_businesses_110_131_import.xlsx` — **FULL import**, 110 rows, all 22 stores (110-131).
- `urban_goodz_TEST_batch_stores_111_118.xlsx` — **TEST batch**, 10 rows, stores 111 + 118 only. Upload this FIRST to confirm the write path is unblocked before running the full file.
- `build_import.py` — generator script (regenerates both files).

---

## What each store (110-131) is, and its correct module-6 category

Module = 6 (Home-Based Businessz). Each store is placed in the category matching what it actually offers.

### KEEP stores (verified minority-owned, real vendor accounts)
| ID | Store | Category (id) |
|---|---|---|
| 110 | The Tipping Point (Latino) | Gifts (70) / Food (71) |
| 111 | Premium Goods Houston (Black) | Apparel (67) |
| 115 | Melodrama Boutique | Apparel (67) / Jewelry (68) |
| 117 | Centre Dallas (Black) | Apparel (67) / Jewelry (68) |
| 118 | Sneaker Politics (Black) | Apparel (67) |
| 126 | League of Rebels (Black) | Apparel (67) / Print-On-Demand (83) |

### REPLACEMENT stores (verified minority-owned, REAL products)
| ID | Store | Confirm | Category (id) |
|---|---|---|---|
| 112 | LAMIK Beauty (Houston) | Black-owned | Beauty (66) |
| 113 | Black Phlox Studios (Houston) | Black-owned | Candles (76) |
| 114 | A Leap of Style (Houston) | Black-owned | Apparel (67) |
| 116 | Custom Rings & Custom Things (Houston) | Black-owned | Jewelry (68) |
| 119 | Charlene's Style Boutique (Dallas) | Black-owned | Apparel (67) |
| 120 | The House of Dasha (Dallas) | Black-owned (BuyBlack) | Apparel (67) |
| 121 | 10 Hours of Fashion (Dallas) | Black-owned (BuyBlack) | Apparel (67) |
| 122 | Luminosa Vida (Austin) | Latina-owned | Candles (76) / Home Decor (72) |
| 123 | Black Pearl Books (Austin) | Black woman-owned | Books & Media (65) |
| 124 | U4U Designs (Austin) | Black-owned | Apparel (67) |
| 125 | Floral Sea (Austin) | Black-Latina-owned | Jewelry (68) |
| 127 | Lady Brown's Boutique (Galveston) | Black woman-owned | Apparel (67) / Jewelry (68) |
| 128 | Cloth & Cord (Kemah, Galveston Co.) | Black-owned | Jewelry (68) |
| 129 | BLCK Market (Pearland, Brazoria) | Black-owned | Apparel (67) / Handmade (69) |
| 130 | Ebony Expressions (Huntsville TX) | Black-owned | Art (73) / Stationery (77) / Print (83) |
| 131 | NOheartNOhustle (Huntsville TX) | Black-owned | Apparel (67) / Print (83) |

Category diversity spans: 65 Books, 66 Beauty, 67 Apparel, 68 Jewelry, 69 Handmade, 70 Gifts, 71 Food, 72 Home Decor, 73 Art, 76 Candles, 77 Stationery, 83 Print-On-Demand.

---

## Upload steps (per `AdminItemController@bulk_import_data`)
1. In the admin panel (`admin.urbangoodzdelivery.com`), navigate to Item bulk import and ensure the **currently-selected module is "Home-Based Businessz" (module 6)** — the Excel's `ModuleId` column is IGNORED; the module is taken from the admin's active module context.
2. Upload the **TEST batch** file first (`..._TEST_batch_stores_111_118.xlsx`) and select "import".
3. Verify stores 111 and 118 now show active products (check via admin and the public API / store view).
4. If successful, upload the **FULL** file.
5. If the upload is classifier-blocked (prior behavior on raw DB writes), the write must be done via D'Andre's authenticated admin browser session or by D'Andre/backend teammate directly.

## Format notes (why it was built this way)
- `SubCategoryId = 0` (falsy → controller uses `CategoryId`); the controller requires the field non-empty, so blank would fail validation.
- `Image = "product.png"` (≤30 chars) — items import with a placeholder; real per-vendor product/store photography is the separate Task 1 (81 logos + ~398 item photos), unchanged.
- `Status="active"`, `Veg="no"`, `Recommended="no"`, `Discount=0`, `DiscountType="amount"`, times 08:00:00–22:00:00, `Stock=100`.
- `Id` = sequential from 3000 (same as the proven stores 21-31 batch).
- All 110 rows passed a simulation of the controller's required-field / price / image-length / time-order checks.

---

## Follow-ups (next session)
- Run the TEST upload (needs authenticated admin session; coordinate DB write with backend teammate).
- On success, run FULL upload; verify all 22 stores live with active items.
- Then proceed to the larger platform-taxonomy effort (18 primary business categories across backend + customer/vendor/business-portal/admin surfaces) — inspect first, plan, then implement with backend teammate coordination.
