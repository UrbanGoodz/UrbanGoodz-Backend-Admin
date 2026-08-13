<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic role fixtures for the ISOLATED staging database.
 *
 * Every row uses a fixed, reserved primary key in the 9000-block so the
 * fixture set is idempotent: re-running updates in place and never
 * duplicates. Nothing here is destructive - no truncate, no drop.
 *
 * The shared fixture password is NEVER stored in this file. Export it:
 *     export STAGING_FIXTURE_PASSWORD=...
 * The seeder refuses to run if it is unset, so no credential is committed.
 *
 * Guards covered (config/auth.php):
 *   admin   -> admins            (full admin + restricted admin)
 *   web/api -> users             (shopper)
 *   vendor  -> vendors + stores  (approved / pending / rejected)
 *   delivery_man -> delivery_men (online / offline / pending)
 *   business -> urban_goodz_business_clients
 *              + urban_goodz_business_client_users (business owner, dispatcher)
 */
class StagingRoleFixtureSeeder extends Seeder
{
    /** Reserved deterministic id block. */
    private const ID = [
        'role_super'      => 9001,
        'role_restricted' => 9002,
        'admin_full'      => 9001,
        'admin_limited'   => 9002,
        'shopper'         => 9001,
        'vendor_approved' => 9001,
        'vendor_pending'  => 9002,
        'vendor_rejected' => 9003,
        'store_approved'  => 9001,
        'store_pending'   => 9002,
        'store_rejected'  => 9003,
        'driver_online'   => 9001,
        'driver_offline'  => 9002,
        'driver_pending'  => 9003,
        'zone'            => 9001,
        'module'          => 9001,
        'biz_client'      => 9001,
        'biz_owner'       => 9001,
        'dispatcher'      => 9002,
    ];

    private string $hash;

    public function run(): void
    {
        $this->guardEnvironment();

        $plain = getenv('STAGING_FIXTURE_PASSWORD') ?: null;
        if (! $plain) {
            throw new \RuntimeException(
                'STAGING_FIXTURE_PASSWORD is not set; refusing to seed fixtures with a guessable password.'
            );
        }
        $this->hash = Hash::make($plain);
        unset($plain);

        $this->seedPrerequisites();
        $this->seedAdmins();
        $this->seedShopper();
        $this->seedVendors();
        $this->seedDrivers();
        $this->seedBusinessAndDispatcher();

        $this->command?->info('Staging role fixtures seeded (deterministic ids 9001+).');
    }

    /**
     * Refuse to touch anything that is not an obviously isolated staging schema.
     */
    private function guardEnvironment(): void
    {
        $db = DB::connection()->getDatabaseName();
        $env = app()->environment();

        if ($env === 'production') {
            throw new \RuntimeException("Refusing to seed fixtures in the production environment.");
        }
        if (! preg_match('/staging|test/i', (string) $db)) {
            throw new \RuntimeException(
                "Refusing to seed fixtures into database '{$db}': name does not look like an isolated staging schema."
            );
        }
    }

    /** upsert by fixed primary key. */
    private function put(string $table, int $id, array $attrs): void
    {
        $now = now();
        $exists = DB::table($table)->where('id', $id)->exists();

        if (DB::getSchemaBuilder()->hasColumn($table, 'updated_at')) {
            $attrs['updated_at'] = $now;
        }

        if ($exists) {
            DB::table($table)->where('id', $id)->update($attrs);

            return;
        }

        if (DB::getSchemaBuilder()->hasColumn($table, 'created_at')) {
            $attrs['created_at'] = $now;
        }
        DB::table($table)->insert($attrs + ['id' => $id]);
    }

    private function seedPrerequisites(): void
    {
        // Zone: stores/drivers are zone-scoped. Simple square polygon around a
        // fixed staging origin so geo lookups resolve deterministically.
        $polygon = "POLYGON((-90.2 38.5,-90.0 38.5,-90.0 38.7,-90.2 38.7,-90.2 38.5))";
        if (! DB::table('zones')->where('id', self::ID['zone'])->exists()) {
            DB::statement(
                'INSERT INTO zones (id, name, coordinates, status, created_at, updated_at)
                 VALUES (?, ?, ST_GeomFromText(?), 1, NOW(), NOW())',
                [self::ID['zone'], 'Staging Fixture Zone', $polygon]
            );
        }

        $this->put('modules', self::ID['module'], [
            'module_name' => 'Staging Fixture Module',
            'module_type' => 'grocery',
            'status'      => 1,
        ]);

        $this->put('admin_roles', self::ID['role_super'], [
            'name'    => 'Staging Super Admin',
            'modules' => json_encode(['all']),
            'status'  => 1,
        ]);

        // Restricted admin: read-only-ish slice, explicitly NOT 'all'.
        $this->put('admin_roles', self::ID['role_restricted'], [
            'name'    => 'Staging Restricted Admin',
            'modules' => json_encode(['order']),
            'status'  => 1,
        ]);
    }

    private function seedAdmins(): void
    {
        $this->put('admins', self::ID['admin_full'], [
            'f_name'   => 'Staging',
            'l_name'   => 'SuperAdmin',
            'email'    => 'staging.admin@fixture.invalid',
            'phone'    => '+15550009001',
            'password' => $this->hash,
            'role_id'  => self::ID['role_super'],
            'zone_id'  => self::ID['zone'],
        ]);

        $this->put('admins', self::ID['admin_limited'], [
            'f_name'   => 'Staging',
            'l_name'   => 'RestrictedAdmin',
            'email'    => 'staging.restricted.admin@fixture.invalid',
            'phone'    => '+15550009002',
            'password' => $this->hash,
            'role_id'  => self::ID['role_restricted'],
            'zone_id'  => self::ID['zone'],
        ]);
    }

    private function seedShopper(): void
    {
        $this->put('users', self::ID['shopper'], [
            'f_name'   => 'Staging',
            'l_name'   => 'Shopper',
            'email'    => 'staging.shopper@fixture.invalid',
            'phone'    => '+15550009101',
            'password' => $this->hash,
            'status'   => 1,
            'zone_id'  => self::ID['zone'],
        ]);
    }

    /**
     * Vendor approval state lives on stores.admin_approval_status
     * (approved|pending|rejected); vendors.status is the 0/1 active flag and
     * vendors.rejection_note carries the rejection reason.
     */
    private function seedVendors(): void
    {
        $cases = [
            ['vendor_approved', 'store_approved', 'approved', 1, null,  'Approved', '9201'],
            ['vendor_pending',  'store_pending',  'pending',  0, null,  'Pending',  '9202'],
            ['vendor_rejected', 'store_rejected', 'rejected', 0, 'Staging fixture: rejected for document mismatch.', 'Rejected', '9203'],
        ];

        foreach ($cases as [$vKey, $sKey, $approval, $active, $note, $label, $tail]) {
            $this->put('vendors', self::ID[$vKey], [
                'f_name'         => 'Staging',
                'l_name'         => "Vendor{$label}",
                'email'          => 'staging.vendor.'.strtolower($label).'@fixture.invalid',
                'phone'          => '+1555000'.$tail,
                'password'       => $this->hash,
                'status'         => $active,
                'rejection_note' => $note,
            ]);

            $this->put('stores', self::ID[$sKey], [
                'name'                  => "Staging Store {$label}",
                'phone'                 => '+1555001'.$tail,
                'email'                 => 'staging.store.'.strtolower($label).'@fixture.invalid',
                'vendor_id'             => self::ID[$vKey],
                'module_id'             => self::ID['module'],
                'zone_id'               => self::ID['zone'],
                'latitude'              => '38.6000',
                'longitude'             => '-90.1000',
                'address'               => "{$label} fixture address, staging",
                'status'                => $active,
                'active'                => $active,
                'admin_approval_status' => $approval,
                'admin_approved_at'     => $approval === 'approved' ? now() : null,
            ]);
        }
    }

    /**
     * delivery_men.active  -> 1 online / 0 offline
     * delivery_men.application_status -> approved|denied|pending
     */
    private function seedDrivers(): void
    {
        $cases = [
            ['driver_online',  'Online',  1, 'approved', '9301'],
            ['driver_offline', 'Offline', 0, 'approved', '9302'],
            ['driver_pending', 'Pending', 0, 'pending',  '9303'],
        ];

        foreach ($cases as [$key, $label, $active, $appStatus, $tail]) {
            $this->put('delivery_men', self::ID[$key], [
                'f_name'             => 'Staging',
                'l_name'             => "Driver{$label}",
                'email'              => 'staging.driver.'.strtolower($label).'@fixture.invalid',
                'phone'              => '+1555000'.$tail,
                'password'           => $this->hash,
                'identity_number'    => 'STG-'.$tail,
                'identity_type'      => 'passport',
                'identity_image'     => json_encode(['staging-fixture-id.png']),
                'zone_id'            => self::ID['zone'],
                'status'             => $appStatus === 'approved' ? 1 : 0,
                'active'             => $active,
                'application_status' => $appStatus,
                'earning'            => 1,
                'type'               => 'zone_wise',
            ]);
        }
    }

    private function seedBusinessAndDispatcher(): void
    {
        $this->put('urban_goodz_business_clients', self::ID['biz_client'], [
            'company_name' => 'Staging Fixture Logistics LLC',
            'email'        => 'staging.business@fixture.invalid',
            'phone'        => '+15550009401',
            'status'       => 'approved',
        ]);

        $this->put('urban_goodz_business_client_users', self::ID['biz_owner'], [
            'business_client_id' => self::ID['biz_client'],
            'first_name'         => 'Staging',
            'last_name'          => 'BusinessOwner',
            'email'              => 'staging.business.owner@fixture.invalid',
            'password'           => $this->hash,
            'role'               => 'owner',
            'is_active'          => 1,
            'status'             => 'active',
        ]);

        $this->put('urban_goodz_business_client_users', self::ID['dispatcher'], [
            'business_client_id' => self::ID['biz_client'],
            'first_name'         => 'Staging',
            'last_name'          => 'Dispatcher',
            'email'              => 'staging.dispatcher@fixture.invalid',
            'password'           => $this->hash,
            'role'               => 'dispatcher',
            'is_active'          => 1,
            'status'             => 'active',
        ]);
    }
}
