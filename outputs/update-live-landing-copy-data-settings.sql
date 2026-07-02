-- =============================================================================
-- UPDATE-LIVE-LANDING-COPY-DATA-SETTINGS.SQL
-- Purpose:  Copy-approval patch for live `data_settings` rows (admin_landing_page)
-- Author:   Auto-generated from approved static preview
-- Date:     2026-06-15
-- Warning:  This patch UPDATEs existing rows only.  No INSERT, DELETE, or ALTER.
--           Always take a DB backup before running on production.
-- Verify:   Run the SELECT preview queries first to confirm the target rows.
-- =============================================================================

-- ============================================================
-- 1. fixed_header_title  (Header Section > Title)
--    Blade now hardcodes hero text; this is a fallback only.
--    Old: Manage Your  Daily Life in one platform
--    New: Your Connection To Local Everything
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'fixed_header_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Your Connection To Local Everything',
       `updated_at` = NOW()
 WHERE `key` = 'fixed_header_title' AND `type` = 'admin_landing_page';

-- ============================================================
-- 2. fixed_module_title  (Module List Section > Title)
--    Appears in: Services section heading
--    Old: Your eCommerce venture starts here !
--    New: Everything Local. One Marketplace.
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'fixed_module_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Everything Local. One Marketplace.',
       `updated_at` = NOW()
 WHERE `key` = 'fixed_module_title' AND `type` = 'admin_landing_page';

-- ============================================================
-- 3. fixed_module_sub_title  (Module List Section > Sub Title)
--    Appears in: Services section subtitle
--    Old: Enjoy all services in one platform
--    New: Shop, rent, book, discover, and earn through one local marketplace.
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'fixed_module_sub_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Shop, rent, book, discover, and earn through one local marketplace.',
       `updated_at` = NOW()
 WHERE `key` = 'fixed_module_sub_title' AND `type` = 'admin_landing_page';

-- ============================================================
-- 4. fixed_referal_title  (Referral & Earning > Title)
--    Appears in: Referral banner section
--    Old: Earn point by
--    New: Earn With Urban Goodz
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'fixed_referal_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Earn With Urban Goodz',
       `updated_at` = NOW()
 WHERE `key` = 'fixed_referal_title' AND `type` = 'admin_landing_page';

-- ============================================================
-- 5. fixed_footer_article_title  (Footer Article > Title)
--    Appears in: Footer brand description
--    Old: 6amMart is a complete package!  It's time to empower your
--         multivendor online business with  powerful features!
--    New: Urban Goodz connects local commerce, services, rentals,
--         vendors, drivers, and community discovery in one platform.
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'fixed_footer_article_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Urban Goodz connects local commerce, services, rentals, vendors, drivers, and community discovery in one platform.',
       `updated_at` = NOW()
 WHERE `key` = 'fixed_footer_article_title' AND `type` = 'admin_landing_page';

-- ============================================================
-- 6. feature_title  (Feature Title & Short Description > Title)
--    Appears in: Features section heading
--    Old: Remarkable Features that You Can Count!
--    New: Powerful Features Built for Local Commerce
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'feature_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Powerful Features Built for Local Commerce',
       `updated_at` = NOW()
 WHERE `key` = 'feature_title' AND `type` = 'admin_landing_page';

-- ============================================================
-- 7. feature_short_description  (Feature Title & Short Description > Short Description)
--    Appears in: Features section subtitle
--    Old: Jam-packed with outstanding features to elevate your online ordering
--         and delivery easier, and smarter than ever before. It's time to
--         empower your multivendor online business with 6amMart's powerful features!
--    New: Urban Goodz combines shopping, rentals, services, logistics, events,
--         creators, and AI-powered discovery into one intelligent marketplace
--         designed to help communities thrive.
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'feature_short_description' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Urban Goodz combines shopping, rentals, services, logistics, events, creators, and AI-powered discovery into one intelligent marketplace designed to help communities thrive.',
       `updated_at` = NOW()
 WHERE `key` = 'feature_short_description' AND `type` = 'admin_landing_page';

-- ============================================================
-- 8. earning_title  (Download User App Section Content > Title)
--    Appears in: Earn section heading
--    Old: Earn Money
--    New: Everything You Need, $One App$
--    Note: $...$ delimiters are parsed by Helpers::highlight() to
--          wrap with <span class="hl">.
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'earning_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Everything You Need, $One App$',
       `updated_at` = NOW()
 WHERE `key` = 'earning_title' AND `type` = 'admin_landing_page';

-- ============================================================
-- 9. earning_sub_title  (Download User App Section Content > Sub Title)
--    Appears in: Earn section subtitle
--    Old: Earn money  by using different platform
--    New: Shop, rent, discover events, support local businesses, and
--         access services all from one app.
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'earning_sub_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'Shop, rent, discover events, support local businesses, and access services all from one app.',
       `updated_at` = NOW()
 WHERE `key` = 'earning_sub_title' AND `type` = 'admin_landing_page';

-- ============================================================
-- 10. why_choose_title  (Why Choose Us tab > Title)
--     Appears in: Why Choose Us / Special section heading
--     Old: What so Special About 6amMart ?
--     New: What's So Special About $Urban Goodz$?
--     Note: $...$ delimiters are parsed by Helpers::highlight() to
--           wrap with <span class="hl">.
-- ============================================================
SELECT id, `key`, `value`, `type`
  FROM `data_settings`
 WHERE `key` = 'why_choose_title' AND `type` = 'admin_landing_page';

UPDATE `data_settings`
   SET `value` = 'What''s So Special About $Urban Goodz$?',
       `updated_at` = NOW()
 WHERE `key` = 'why_choose_title' AND `type` = 'admin_landing_page';

-- =============================================================================
-- END OF PATCH
-- Confirm: Re-run the SELECT queries above to verify new values.
-- =============================================================================
