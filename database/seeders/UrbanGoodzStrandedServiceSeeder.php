<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Urban Goodz Stranded service catalogue.
 *
 * `samaritan_eligible` encodes the specification's safety rule, not a
 * preference. A verified community member may handle a jump start, a tire
 * change, fuel, a lockout or battery assistance. Towing, collision recovery,
 * major mechanical work, heavy-duty and accident scenes require a licensed
 * professional and must never be broadcast to a Samaritan.
 *
 * Prices are minor units. Where the spec says market/hourly/custom the
 * minimum is 0 and the note carries the meaning, so the client shows a quote
 * flow instead of a figure it cannot honour.
 */
class UrbanGoodzStrandedServiceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // slug, name, min, max, samaritan_eligible, note, typical minutes
        $services = [
            ['dead-battery',          'Dead Battery',                  3000,  5000, true,  null,                25],
            ['jump-start',            'Jump Start',                    3900,  5900, true,  null,                20],
            ['flat-tire',             'Flat Tire',                     5900,  8900, true,  null,                30],
            ['fuel-delivery',         'Fuel Delivery',                 2900,  null, true,  'Plus fuel cost',    25],
            ['locked-out',            'Locked Out',                    5900,  9900, true,  'If qualified',      25],
            ['stuck-vehicle',         'Stuck Vehicle',                 0,     null, true,  'Quoted on scene',   30],
            ['need-ride-home',        'Need Help Getting Home',        0,     null, true,  'Quoted on scene',   30],
            ['wont-start',            "Vehicle Won't Start",           0,     null, false, 'Quoted on scene',   40],
            ['tow-truck',             'Tow Truck',                     0,     null, false, 'Market pricing',    60],
            ['winch-recovery',        'Winch / Recovery',              9500,  null, false, 'From $95',          45],
            ['mobile-mechanic',       'Mobile Mechanic',               0,     null, false, 'Hourly',            90],
            ['battery-replacement',   'Battery Replacement',           0,     null, false, 'Priced by battery', 45],
            ['ev-charging',           'EV Charging',                   0,     null, false, 'Quoted on scene',   45],
            ['accident-assistance',   'Accident Assistance',           0,     null, false, 'Quoted on scene',   60],
            ['heavy-duty',            'Heavy Duty Roadside',           0,     null, false, 'Custom quote',      90],
            ['motorcycle',            'Motorcycle Assistance',         0,     null, false, 'Quoted on scene',   40],
            ['rv-assistance',         'RV Assistance',                 0,     null, false, 'Custom quote',      75],
            ['commercial-fleet',      'Commercial Fleet Assistance',   0,     null, false, 'Contract pricing',  90],
            ['other',                 'Other',                         0,     null, false, 'Quoted on scene',   45],
        ];

        foreach ($services as $i => [$slug, $name, $min, $max, $samaritan, $note, $minutes]) {
            DB::table('urban_goodz_stranded_services')->updateOrInsert(
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
