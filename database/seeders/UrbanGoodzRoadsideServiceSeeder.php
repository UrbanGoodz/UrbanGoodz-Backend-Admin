<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Stranded Roadside Assistance catalogue.
 *
 * `samaritan_eligible` encodes the safety rule from the specification, not a
 * preference: a verified community member may handle simple situations, but
 * towing, recovery, major mechanical work and hazardous scenes require a
 * licensed professional and must never be broadcast to a Samaritan.
 *
 * Prices are minor units (cents). Where the spec says "market", "hourly" or
 * "custom quote" the min is 0 and the note carries the meaning, so the client
 * shows a quote flow instead of a fixed price.
 */
class UrbanGoodzRoadsideServiceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // slug, name, min, max, samaritan_eligible, note, typical minutes
        $services = [
            ['jump-start',              'Jump Start',                     3900,  5900, true,  null,               20],
            ['battery-replacement',     'Dead Battery Replacement',       0,     null, false, 'Priced by battery', 45],
            ['flat-tire-change',        'Flat Tire Change',               5900,  8900, true,  null,               30],
            ['tire-repair',             'Tire Repair',                    5900,  8900, true,  null,               35],
            ['fuel-delivery',           'Fuel Delivery',                  2900,  null, true,  'Plus fuel cost',   25],
            ['vehicle-lockout',         'Vehicle Lockout',                5900,  9900, true,  'If qualified',     25],
            ['winch-pull-out',          'Winch / Pull Out',               9500,  null, false, 'From $95',         45],
            ['vehicle-recovery',        'Vehicle Recovery',               0,     null, false, 'Market pricing',   60],
            ['minor-mechanical',        'Minor Mechanical Help',          0,     null, false, 'Hourly',           60],
            ['overheating',             'Overheating Assistance',         0,     null, false, 'Quoted on scene',  40],
            ['battery-diagnostics',     'Battery Diagnostics',            3000,  5000, true,  null,               25],
            ['mobile-tire-install',     'Mobile Tire Installation',       0,     null, false, 'Priced by tire',   60],
            ['mobile-mechanic',         'Mobile Mechanic',                0,     null, false, 'Hourly',           90],
            ['tow-truck',               'Tow Truck Request',              0,     null, false, 'Market pricing',   60],
            ['roadside-inspection',     'Emergency Roadside Inspection',  0,     null, false, 'Quoted on scene',  30],
            ['motorcycle-assistance',   'Motorcycle Assistance',          0,     null, false, 'Quoted on scene',  40],
            ['commercial-vehicle',      'Commercial Vehicle Assistance',  0,     null, false, 'Custom quote',     90],
            ['fleet-support',           'Fleet Roadside Support',         0,     null, false, 'Contract pricing', 90],
            ['ev-assistance',           'Electric Vehicle Assistance',    0,     null, false, 'Quoted on scene',  45],
            ['trailer-assistance',      'Trailer Assistance',             0,     null, false, 'Quoted on scene',  60],
        ];

        foreach ($services as $i => [$slug, $name, $min, $max, $samaritan, $note, $minutes]) {
            DB::table('urban_goodz_roadside_services')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'base_price_min_minor' => $min,
                    'base_price_max_minor' => $max,
                    'currency' => 'USD',
                    'pricing_note' => $note,
                    'samaritan_eligible' => $samaritan,
                    'typical_duration_minutes' => $minutes,
                    'sort_order' => $i,
                    'enabled' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
