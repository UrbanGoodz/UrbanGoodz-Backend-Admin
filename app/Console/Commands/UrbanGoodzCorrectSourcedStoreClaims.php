<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sourcing batches imported before urban-goodz:phase3-provision encoded the
 * policy took the stores table's column defaults: business_status
 * 'active_partner', contract_status 'contracted', badge_status
 * 'urban_goodz_partner', can_direct_checkout 1. None of that is true of a
 * business that never signed up.
 *
 * Two consequences, both live:
 *  - the storefront claims "Urban Goodz Partner" for businesses that have no
 *    contract with Urban Goodz, which is a claim we must not make; and
 *  - Store::shouldRouteToOrderAnywhere() returns false, so a customer checkout
 *    is handed to a vendor dashboard nobody is watching instead of going
 *    through Order Anywhere with Urban Goodz fulfilling.
 *
 * A store is only rewritten when its vendor account is a UG-held placeholder
 * on urbangoodzdelivery.com - the identity urban-goodz:phase3-provision mints
 * for a business that has not been onboarded. A store whose vendor signed up
 * with their own address is never touched.
 */
class UrbanGoodzCorrectSourcedStoreClaims extends Command
{
    protected $signature = 'urban-goodz:correct-sourced-store-claims
        {--apply : Write the corrections. Without this the command only reports.}
        {--store-id=* : Limit to specific store ids. Default is every affected store.}';

    protected $description = 'Clear partner claims from stores held by a UG placeholder vendor, so unsigned businesses route through Order Anywhere.';

    /** What a store belonging to a business that never signed up must say. */
    public const CORRECTED = [
        'business_status' => 'public_sourced',
        'contract_status' => 'not_contracted',
        'fulfillment_mode' => 'order_anywhere_backend',
        'badge_status' => 'public_listing',
        'can_direct_checkout' => 0,
        'partner_badge_enabled' => 0,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $storeIds = array_filter((array) $this->option('store-id'));

        $affected = $this->affectedStores($storeIds);

        if ($affected->isEmpty()) {
            $this->info('Nothing to correct: no store held by a UG placeholder vendor still claims partner status.');
            return self::SUCCESS;
        }

        $this->warn($affected->count() . ' store(s) held by a UG placeholder vendor differ from the unsigned-business shape.');
        $this->newLine();

        // Two very different groups match, and lumping them together hides the
        // only one that changes what a customer sees. Split them before anyone
        // decides whether to --apply.
        $claimsPartner = $affected->filter(fn ($r) => $r->business_status !== self::CORRECTED['business_status']
            || $r->contract_status !== self::CORRECTED['contract_status']
            || (int) $r->can_direct_checkout !== self::CORRECTED['can_direct_checkout']);
        $badgeToggleOnly = $affected->count() - $claimsPartner->count();

        $this->table(['group', 'stores', 'what changes'], [
            [
                'claims partner status',
                $claimsPartner->count(),
                'storefront stops saying Urban Goodz Partner, and checkout reroutes to Order Anywhere',
            ],
            [
                'badge toggle only',
                $badgeToggleOnly,
                'partner_badge_enabled 1 -> 0; no customer-visible change today',
            ],
        ]);
        $this->newLine();
        $this->table(
            ['store', 'name', 'vendor email', 'business_status', 'contract', 'badge', 'direct checkout'],
            $affected->take(15)->map(fn ($r) => [
                $r->id,
                mb_strimwidth((string) $r->name, 0, 32, '...'),
                mb_strimwidth((string) $r->vendor_email, 0, 34, '...'),
                $r->business_status,
                $r->contract_status,
                $r->badge_status,
                $r->can_direct_checkout,
            ])->all()
        );

        if ($affected->count() > 15) {
            $this->line('  ... and ' . ($affected->count() - 15) . ' more.');
        }

        $this->newLine();
        $this->line('Each would become: ' . json_encode(self::CORRECTED));

        if (!$apply) {
            $this->newLine();
            $this->info('Dry run. Nothing was written. Re-run with --apply to commit.');
            return self::SUCCESS;
        }

        $ids = $affected->pluck('id')->all();

        DB::transaction(function () use ($ids, &$written) {
            $written = Store::whereIn('id', $ids)->update(self::CORRECTED);
        });

        $this->newLine();
        $this->info("Corrected {$written} store(s).");

        $remaining = $this->affectedStores([])->count();
        $this->line("Stores still claiming partner status on a placeholder vendor: {$remaining} (expected 0).");

        return $remaining === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Claiming partner status while held by a placeholder vendor. Matched on
     * any partner-ish field rather than all of them, so a half-corrected row
     * from an interrupted run is still picked up - this is re-runnable.
     */
    private function affectedStores(array $storeIds)
    {
        return DB::table('stores')
            ->join('vendors', 'vendors.id', '=', 'stores.vendor_id')
            ->where('vendors.email', 'like', '%@urbangoodzdelivery.com')
            ->where(function ($q) {
                $q->where('stores.business_status', '!=', self::CORRECTED['business_status'])
                    ->orWhere('stores.contract_status', '!=', self::CORRECTED['contract_status'])
                    ->orWhere('stores.fulfillment_mode', '!=', self::CORRECTED['fulfillment_mode'])
                    ->orWhere('stores.badge_status', '!=', self::CORRECTED['badge_status'])
                    ->orWhere('stores.can_direct_checkout', '!=', self::CORRECTED['can_direct_checkout'])
                    ->orWhere('stores.partner_badge_enabled', '!=', self::CORRECTED['partner_badge_enabled']);
            })
            ->when($storeIds, fn ($q) => $q->whereIn('stores.id', $storeIds))
            ->orderBy('stores.id')
            ->get([
                'stores.id',
                'stores.name',
                'stores.business_status',
                'stores.contract_status',
                'stores.badge_status',
                'stores.can_direct_checkout',
                'vendors.email as vendor_email',
            ]);
    }
}
