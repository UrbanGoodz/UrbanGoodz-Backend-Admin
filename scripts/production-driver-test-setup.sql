-- ============================================================
-- PRODUCTION TEST DRIVER SETUP - SQL SCRIPT
-- ============================================================
-- Run this on the production database (urbakkej_urbangoodzdelivery)
-- via phpMyAdmin or MySQL CLI
--
-- This creates a test driver for UrbanGoodz Driver App acceptance testing.
-- SAFE TO RUN MULTIPLE TIMES (uses INSERT ... ON DUPLICATE KEY UPDATE)
--
-- INSTRUCTIONS:
-- 1. Replace the auth_token value below with a fresh random string
--    (run: openssl rand -hex 60  in your terminal)
-- 2. Adjust zone_id and vehicle_id to match your production data
-- 3. Run this SQL on the production database
-- ============================================================

-- First, find your zone_id:
SELECT id, name FROM zones WHERE status = 1 LIMIT 5;

-- Find your vehicle_id:
SELECT id, type, capacity FROM vehicles WHERE status = 1 LIMIT 5;

-- Find a valid auth_token (generate one):
-- SELECT CONCAT(REPLACE(UUID(), '-', ''), REPLACE(UUID(), '-', ''));

-- ============================================================
-- MAIN INSERT (uncomment after confirming IDs above)
-- ============================================================

-- Step 1: Create a vehicle if needed
INSERT INTO vehicles (type, capacity, min_cap, avg_cap, max_cap, status, created_at, updated_at)
VALUES ('car', 4, 1, 4, 6, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE id = id;

-- Step 2: Get the vehicle ID
SET @vehicle_id = (SELECT id FROM vehicles WHERE type = 'car' AND status = 1 LIMIT 1);

-- Step 3: Create the test driver
INSERT INTO delivery_men (
    f_name, l_name, email, phone, identity_type, identity_number,
    password, zone_id, earning, vehicle_id,
    application_status, status, active, available,
    is_delivery, is_ride,
    image, identity_image, auth_token, ref_code,
    fcm_token, current_orders, assigned_order_count,
    loyalty_point, created_at, updated_at
) VALUES (
    'Test', 'Driver001',
    'test.driver001@urbangoodzdelivery.com',
    '+15551230001',
    'passport', 'TEST-DM-001-IDENTITY',
    '$2y$10$YourBcryptHashHere',  -- SEE STEP 4 BELOW
    1,          -- zone_id: UPDATE THIS to your primary zone
    15.00,      -- $15/hr base earning
    @vehicle_id,
    'approved', -- application_status
    1,          -- status (approved)
    1,          -- active
    1,          -- available
    1,          -- is_delivery
    0,          -- is_ride
    'def.png',  -- image
    '[]',       -- identity_image (JSON)
    'YOUR_AUTH_TOKEN_HERE',  -- SEE STEP 5 BELOW
    'TEST' || LEFT(UUID(), 8),
    NULL,       -- fcm_token
    0,          -- current_orders
    0,          -- assigned_order_count
    0,          -- loyalty_point
    NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    application_status = 'approved',
    status = 1,
    active = 1,
    available = 1,
    auth_token = VALUES(auth_token),
    updated_at = NOW();

-- ============================================================
-- STEPS TO COMPLETE:
--
-- STEP 4: Generate bcrypt hash for password 'TestDriver2026!$'
--   Run this PHP on production:
--   php -r "echo password_hash('TestDriver2026!\$', PASSWORD_BCRYPT);"
--   Paste the output into the password field above
--
-- STEP 5: Generate auth_token
--   Run this in terminal:
--   openssl rand -hex 60
--   Paste the 120-character hex string into the auth_token field above
--
-- STEP 6: Verify the driver was created:
--   SELECT id, f_name, l_name, email, phone, application_status, status, active, auth_token
--   FROM delivery_men
--   WHERE email = 'test.driver001@urbangoodzdelivery.com';
-- ============================================================
