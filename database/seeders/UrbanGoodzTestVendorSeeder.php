<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Models\Store;
use App\Models\Module;
use App\Models\Zone;
use App\Models\Storage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UrbanGoodzTestVendorSeeder extends Seeder
{
    public function run(): void
    {
        // Find the Restaurants module (created by UrbanGoodzIngestionSeeder)
        $module = Module::where('module_name', 'Restaurants')->first();
        if (!$module) {
            $this->command?->warn('Restaurants module not found. Run UrbanGoodzIngestionSeeder first.');
            return;
        }

        // Find the Houston zone (created by UrbanGoodzIngestionSeeder)
        $zone = Zone::where('name', 'Houston')->first();
        if (!$zone) {
            $this->command?->warn('Houston zone not found. Run UrbanGoodzIngestionSeeder first.');
            return;
        }

        // Check if vendor already exists
        $existing = Vendor::where('email', 'test.restaurant@gmail.com')->first();
        if ($existing) {
            $this->command?->info('Test vendor already exists (ID: ' . $existing->id . '). Skipping.');
            return;
        }

        // Create the Vendor record
        $vendor = Vendor::create([
            'f_name'       => 'Test',
            'l_name'       => 'Restaurant',
            'email'        => 'test.restaurant@gmail.com',
            'phone'        => '+15550001001',
            'password'     => Hash::make('12345678'),
            'status'       => 1,   // active
            'image'        => null,
            'auth_token'   => null,
        ]);

        // Create the Store record
        $store = Store::create([
            'name'                 => 'Test Restaurant',
            'phone'                => '+15550001001',
            'email'                => 'test.restaurant@gmail.com',
            'logo'                 => null,
            'cover_photo'          => null,
            'address'              => '1234 Main St, Houston, TX 77001',
            'latitude'             => 29.7604,
            'longitude'            => -95.3698,
            'vendor_id'            => $vendor->id,
            'zone_id'              => $zone->id,
            'module_id'            => $module->id,
            'delivery_time'        => '30-60 Minute',
            'minimum_order'        => 10.00,
            'comission'            => 10.00,
            'status'               => 1,   // active
            'active'               => 1,
            'featured'             => 0,
            'schedule_order'       => 1,
            'delivery'             => 1,
            'take_away'            => 1,
            'item_section'         => 1,
            'tax'                  => 0,
            'reviews_section'      => 1,
            'veg'                  => 0,
            'non_veg'              => 1,
            'order_count'          => 0,
            'total_order'          => 0,
            'free_delivery'        => 0,
            'self_delivery_system' => 0,
            'pos_system'           => 0,
            'slug'                 => 'test-restaurant',
            'footer_text'          => 'Thank you for ordering from Test Restaurant!',
            'store_business_model' => 'none',
            'pickup_zone_id'       => json_encode([]),
            'per_km_shipping_charge' => 0,
            'minimum_shipping_charge' => 0,
            'maximum_shipping_charge' => 0,
            'prescription_order'   => 0,
            'cutlery'              => 0,
            'announcement'         => 0,
            'announcement_message' => '',
            'comment'              => '',
            'meta_title'           => 'Test Restaurant',
            'meta_description'     => 'A test restaurant for Urban Goodz E2E testing',
            'tin'                  => null,
            'tin_expire_date'      => null,
            'tin_certificate_image'=> null,
            'business_type_slug'   => 'restaurant',
            'business_status'      => 'active',
            'contract_status'      => 'active',
            'vendor_admin_status'  => 'active',
            'banking_status'       => 'pending',
            'subscription_status'  => 'inactive',
            'admin_approval_status'=> 'approved',
            'badge_status'         => 0,
            'fulfillment_mode'     => 'self',
            'is_public_sourced'    => 0,
            'is_claimed'           => 0,
            'is_partner'           => 0,
            'can_direct_checkout'  => 1,
            'requires_admin_quote' => 0,
            'vendor_admin_account_created' => 1,
            'vendor_has_logged_in' => 1,
            'order_anywhere_enabled' => 0,
            'invited_at'           => null,
            'claimed_at'           => null,
            'vendor_panel_activated_at' => now(),
            'banking_submitted_at' => null,
            'banking_verified_at'  => null,
            'contracted_at'        => now(),
            'subscription_activated_at' => null,
            'admin_approved_at'    => now(),
            'partner_badge_enabled_at' => null,
        ]);

        // Create storage records for logo and cover (public disk)
        Storage::create([
            'data_type' => 'App\Models\Store',
            'data_id'   => $store->id,
            'key'       => 'logo',
            'value'     => 'public',
        ]);

        Storage::create([
            'data_type' => 'App\Models\Store',
            'data_id'   => $store->id,
            'key'       => 'cover_photo',
            'value'     => 'public',
        ]);

        // Create storage record for vendor image
        Storage::create([
            'data_type' => 'App\Models\Vendor',
            'data_id'   => $vendor->id,
            'key'       => 'image',
            'value'     => 'public',
        ]);

        $this->command?->info("Test Vendor created:");
        $this->command?->info("  Vendor ID: {$vendor->id}");
        $this->command?->info("  Store ID:  {$store->id}");
        $this->command?->info("  Email:     test.restaurant@gmail.com");
        $this->command?->info("  Password:  12345678");
        $this->command?->info("  Zone:      Houston ({$zone->id})");
        $this->command?->info("  Module:    Restaurants ({$module->id})");
    }
}
