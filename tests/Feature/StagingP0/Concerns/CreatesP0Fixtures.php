<?php

namespace Tests\Feature\StagingP0\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * The StagingP0 suite was written as a set of guardrails against a specific,
 * hand-curated "isolated staging" database (urbangoodz_isolated_staging_20260723)
 * with a fixed set of fixture rows at ids 9001-9003. That database is not
 * available in every environment this suite needs to run in (local dev,
 * this sandbox, CI). This trait recreates the same fixture rows the P0
 * tests already reference by id, idempotently, against whichever allowlisted
 * test database is actually connected - so the guardrails hold everywhere,
 * not just against one specific external database.
 *
 * Every test class using this trait also uses DatabaseTransactions, so
 * these rows are rolled back at the end of each test method and must be
 * (cheaply) re-created every time.
 */
trait CreatesP0Fixtures
{
    protected function ensureP0Fixtures(): void
    {
        DB::table('zones')->updateOrInsert(['id' => 9001], [
            'name' => 'P0 Fixture Zone',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $superRoleId = DB::table('admin_roles')->where('name', 'p0-fixture-super')->value('id');
        if (!$superRoleId) {
            $superRoleId = DB::table('admin_roles')->insertGetId([
                'name' => 'p0-fixture-super',
                'modules' => json_encode(['all']),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $restrictedRoleId = DB::table('admin_roles')->where('name', 'p0-fixture-restricted')->value('id');
        if (!$restrictedRoleId) {
            $restrictedRoleId = DB::table('admin_roles')->insertGetId([
                'name' => 'p0-fixture-restricted',
                'modules' => json_encode(['urban_goodz_dashboard']),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('admins')->updateOrInsert(['id' => 9001], [
            'f_name' => 'P0', 'l_name' => 'Super',
            'email' => 'p0-super@fixture.invalid',
            'phone' => '19000000901',
            'password' => bcrypt('not-a-production-password'),
            'role_id' => $superRoleId,
            'zone_id' => 9001,
            'is_logged_in' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('admins')->updateOrInsert(['id' => 9002], [
            'f_name' => 'P0', 'l_name' => 'Restricted',
            'email' => 'p0-restricted@fixture.invalid',
            'phone' => '19000000902',
            'password' => bcrypt('not-a-production-password'),
            'role_id' => $restrictedRoleId,
            'zone_id' => 9001,
            'is_logged_in' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $vendorId = DB::table('vendors')->where('email', 'p0-store-vendor@fixture.invalid')->value('id');
        if (!$vendorId) {
            $vendorId = DB::table('vendors')->insertGetId([
                'f_name' => 'P0', 'l_name' => 'Store Vendor',
                'phone' => '19000000910',
                'email' => 'p0-store-vendor@fixture.invalid',
                'password' => bcrypt('not-a-production-password'),
                'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $moduleId = DB::table('modules')->where('module_name', 'P0 Fixture Restaurants')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'module_name' => 'P0 Fixture Restaurants',
                'module_type' => 'food',
                'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Approved: the positive case proving the public listing can show a
        // legitimately vetted store, not just that it hides the other two.
        DB::table('stores')->updateOrInsert(['id' => 9001], [
            'name' => 'P0 Approved Store',
            'phone' => '19000000921',
            'vendor_id' => $vendorId,
            'zone_id' => 9001,
            'module_id' => $moduleId,
            'status' => 1,
            'active' => 1,
            'admin_approval_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('stores')->updateOrInsert(['id' => 9002], [
            'name' => 'P0 Pending Store',
            'phone' => '19000000920',
            'vendor_id' => $vendorId,
            'zone_id' => 9001,
            'module_id' => $moduleId,
            'status' => 1,
            'active' => 1,
            'admin_approval_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('stores')->updateOrInsert(['id' => 9003], [
            'name' => 'P0 Rejected Store',
            'phone' => '19000000930',
            'vendor_id' => $vendorId,
            'zone_id' => 9001,
            'module_id' => $moduleId,
            'status' => 1,
            'active' => 1,
            'admin_approval_status' => 'rejected',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('delivery_men')->updateOrInsert(['id' => 9001], [
            'f_name' => 'P0', 'l_name' => 'Online',
            'phone' => '19000000941',
            'password' => bcrypt('not-a-production-password'),
            'identity_image' => json_encode(['staging-fixture-id.png']),
            'status' => 1,
            'active' => 1,
            'zone_id' => 9001,
            'application_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('delivery_men')->updateOrInsert(['id' => 9002], [
            'f_name' => 'P0', 'l_name' => 'Two',
            'phone' => '19000000942',
            'password' => bcrypt('not-a-production-password'),
            'identity_image' => json_encode(['staging-fixture-id-2.png']),
            'status' => 1,
            'active' => 0,
            'zone_id' => 9001,
            'application_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('delivery_men')->updateOrInsert(['id' => 9003], [
            'f_name' => 'P0', 'l_name' => 'Pending',
            'phone' => '19000000943',
            'password' => bcrypt('not-a-production-password'),
            'identity_image' => json_encode(['staging-fixture-id-3.png']),
            'status' => 1,
            'active' => 0,
            'zone_id' => 9001,
            'application_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('vendors')->updateOrInsert(['email' => 'staging.vendor.approved@fixture.invalid'], [
            'f_name' => 'P0', 'l_name' => 'Vendor Approved',
            'phone' => '19000000950',
            'password' => bcrypt('not-a-production-password'),
            'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
