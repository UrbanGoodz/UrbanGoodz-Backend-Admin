<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRoutePackage;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recurring routes (for example a clinic-to-lab specimen run every weekday).
 *
 * A route with a supported `recurring_rule` is a TEMPLATE. Each day this creates the upcoming runs as
 * fresh copies: same pickup, stops, pay and requirements, new tracking codes, delivery windows moved to the
 * run's date, and NO driver and NO scan history, so dispatch assigns each run like any other.
 * Before this, the rule was saved text that nothing read, so a "weekly" route ran exactly once.
 *
 * Supported rules (case-insensitive):
 *   daily | weekdays | weekly | biweekly | monthly | weekly:mon,wed,fri
 * weekly/biweekly/monthly repeat on the template's own scheduled date.
 */
class RecurringRouteScheduler
{
    public const RULES = ['daily', 'weekdays', 'weekly', 'biweekly', 'monthly'];
    private const DAYS = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];

    public static function normalize(?string $rule): ?string
    {
        $rule = strtolower(trim((string) $rule));

        return $rule === '' ? null : preg_replace('/\s+/', '', $rule);
    }

    public static function isValid(?string $rule): bool
    {
        $rule = self::normalize($rule);
        if ($rule === null) {
            return true;                       // "not recurring" is valid
        }
        if (in_array($rule, self::RULES, true)) {
            return true;
        }

        return self::weekdaysOf($rule) !== null;
    }

    /** @return int[]|null ISO weekdays for a `weekly:mon,wed` rule, null if not that form */
    private static function weekdaysOf(string $rule): ?array
    {
        if (! str_starts_with($rule, 'weekly:')) {
            return null;
        }
        $days = [];
        foreach (explode(',', substr($rule, 7)) as $name) {
            if (! isset(self::DAYS[$name])) {
                return null;
            }
            $days[] = self::DAYS[$name];
        }

        return $days === [] ? null : array_values(array_unique($days));
    }

    /** Does the rule schedule a run on $date, given the template's own date as the anchor? */
    public static function runsOn(string $rule, CarbonInterface $date, CarbonInterface $anchor): bool
    {
        $date = $date->copy()->startOfDay();
        $anchor = $anchor->copy()->startOfDay();
        if ($date->lte($anchor)) {
            return false;                      // the template itself is the first run
        }

        return match (true) {
            $rule === 'daily' => true,
            $rule === 'weekdays' => $date->isWeekday(),
            $rule === 'weekly' => $date->dayOfWeekIso === $anchor->dayOfWeekIso,
            $rule === 'biweekly' => $date->dayOfWeekIso === $anchor->dayOfWeekIso && ((int) floor($anchor->diffInDays($date) / 7)) % 2 === 0,
            // A 31st-of-the-month route runs on the last day of shorter months instead of skipping them.
            $rule === 'monthly' => $date->day === min($anchor->day, $date->daysInMonth),
            ($days = self::weekdaysOf($rule)) !== null => in_array($date->dayOfWeekIso, $days, true),
            default => false,
        };
    }

    /** Create the runs due in the next $aheadDays days for every active template. */
    public function generate(int $aheadDays = 7, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $until = $today->copy()->addDays(max(1, $aheadDays));
        $created = $skipped = $unsupported = 0;

        UrbanGoodzDedicatedRoute::query()
            ->whereNull('recurring_parent_id')
            ->whereNotNull('recurring_rule')
            ->where('recurring_rule', '!=', '')
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('id')
            ->chunkById(100, function ($templates) use ($today, $until, &$created, &$skipped, &$unsupported) {
                foreach ($templates as $template) {
                    $rule = self::normalize($template->recurring_rule);
                    if (! self::isValid($rule)) {
                        $unsupported++;
                        continue;
                    }
                    $anchor = Carbon::parse($template->scheduled_date);

                    for ($day = $today->copy(); $day->lte($until); $day->addDay()) {
                        if (! self::runsOn($rule, $day, $anchor)) {
                            continue;
                        }
                        $this->createRun($template, $day->copy()) ? $created++ : $skipped++;
                    }
                }
            });

        return compact('created', 'skipped', 'unsupported');
    }

    /** @return bool true if a run was created, false if one already exists for that date */
    public function createRun(UrbanGoodzDedicatedRoute $template, CarbonInterface $date): bool
    {
        return DB::transaction(function () use ($template, $date) {
            $exists = UrbanGoodzDedicatedRoute::where('recurring_parent_id', $template->id)
                ->whereDate('scheduled_date', $date->toDateString())
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                return false;
            }

            $anchor = Carbon::parse($template->scheduled_date)->startOfDay();
            $shiftDays = (int) $anchor->diffInDays($date->copy()->startOfDay(), false);

            $run = UrbanGoodzDedicatedRoute::create([
                'recurring_parent_id' => $template->id,
                'business_client_id' => $template->business_client_id,
                'route_name' => $template->route_name,
                'route_label' => $template->route_label,
                'route_type' => $template->route_type,
                'source_module' => $template->source_module,
                'pickup_location' => $template->pickup_location,
                'pickup_lat' => $template->pickup_lat,
                'pickup_lng' => $template->pickup_lng,
                'end_location' => $template->end_location,
                'end_lat' => $template->end_lat,
                'end_lng' => $template->end_lng,
                'return_to_origin' => $template->return_to_origin,
                'scheduled_date' => $date->toDateString(),
                'recurring_rule' => $template->recurring_rule,
                'capacity_packages' => $template->capacity_packages,
                'capacity_weight_lbs' => $template->capacity_weight_lbs,
                'max_packages_per_batch' => $template->max_packages_per_batch,
                'vehicle_type_required' => $template->vehicle_type_required,
                'driver_pay_per_package' => $template->driver_pay_per_package,
                'business_charge_per_package' => $template->business_charge_per_package,
                'pickup_bonus' => $template->pickup_bonus,
                'route_completion_bonus' => $template->route_completion_bonus,
                'priority_package_bonus' => $template->priority_package_bonus,
                'failed_delivery_partial_pay' => $template->failed_delivery_partial_pay,
                'return_to_sender_pay' => $template->return_to_sender_pay,
                'instant_payout_allowed' => $template->instant_payout_allowed,
                'weekly_payout_allowed' => $template->weekly_payout_allowed,
                'payout_model' => $template->payout_model,
                'route_offer_amount' => $template->route_offer_amount,
                'contains_age_restricted_items' => $template->contains_age_restricted_items,
                // A new run starts unassigned and unscanned: dispatch assigns it like any other route.
                'status' => 'pending',
                'assigned_driver_id' => null,
                'created_by' => $template->created_by,
                'admin_notes' => 'Generated from recurring route #' . $template->id . ' (' . $template->recurring_rule . ').',
                'total_packages' => 0,
            ]);

            $count = 0;
            foreach ($template->packages()->orderBy('stop_order')->get() as $pkg) {
                $suffix = strtoupper(bin2hex(random_bytes(4)));
                UrbanGoodzRoutePackage::create([
                    'dedicated_route_id' => $run->id,
                    'business_client_id' => $pkg->business_client_id,
                    'tracking_id' => 'UGR-' . $date->format('Ymd') . '-' . $suffix,
                    'barcode' => 'BAR-' . $date->format('Ymd') . '-' . $suffix,
                    'qr_code' => 'QR-' . $date->format('Ymd') . '-' . $suffix,
                    'source_module' => $pkg->source_module,
                    'pickup_location_id' => $pkg->pickup_location_id,
                    'pickup_contact_name' => $pkg->pickup_contact_name,
                    'pickup_contact_phone' => $pkg->pickup_contact_phone,
                    'pickup_address' => $pkg->pickup_address,
                    'dropoff_name' => $pkg->dropoff_name,
                    'dropoff_address' => $pkg->dropoff_address,
                    'dropoff_city' => $pkg->dropoff_city,
                    'dropoff_state' => $pkg->dropoff_state,
                    'dropoff_zip' => $pkg->dropoff_zip,
                    'dropoff_phone' => $pkg->dropoff_phone,
                    'dropoff_lat' => $pkg->dropoff_lat,
                    'dropoff_lng' => $pkg->dropoff_lng,
                    'package_type' => $pkg->package_type,
                    'weight' => $pkg->weight,
                    'weight_unit' => $pkg->weight_unit,
                    'dimensions' => $pkg->dimensions,
                    'priority' => $pkg->priority,
                    'requires_signature' => $pkg->requires_signature,
                    'requires_photo' => $pkg->requires_photo,
                    // Custody, temperature and age rules are part of what makes a medical run safe: they carry over.
                    'requires_custody' => $pkg->requires_custody,
                    'temperature_requirement' => $pkg->temperature_requirement,
                    'age_restricted' => $pkg->age_restricted,
                    'requires_id_verification' => $pkg->requires_id_verification,
                    'no_contactless_delivery' => $pkg->no_contactless_delivery,
                    'delivery_window_start' => $pkg->delivery_window_start ? Carbon::parse($pkg->delivery_window_start)->addDays($shiftDays) : null,
                    'delivery_window_end' => $pkg->delivery_window_end ? Carbon::parse($pkg->delivery_window_end)->addDays($shiftDays) : null,
                    'stop_locked' => $pkg->stop_locked,
                    'locked_stop_order' => $pkg->locked_stop_order,
                    'stop_order' => $pkg->stop_order,
                    'notes' => $pkg->notes,
                    'status' => 'pending',
                ]);
                $count++;
            }

            $run->update(['total_packages' => $count]);

            return true;
        });
    }
}
