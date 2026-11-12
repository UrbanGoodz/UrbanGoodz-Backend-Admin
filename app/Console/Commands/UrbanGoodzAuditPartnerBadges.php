<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Who is actually claiming to be an Urban Goodz Partner, and did anyone grant
 * it to them?
 *
 * stores.partner_badge_enabled shipped with a default of 1 and nothing in the
 * application ever wrote partner_badge_enabled_at, so a badge somebody
 * deliberately granted has been indistinguishable from one the column default
 * handed out at signup. That is the discriminator this command uses, and it
 * deliberately does NOT filter on the vendor's email address the way
 * urban-goodz:correct-sourced-store-claims does - that command only reaches
 * stores held by a UG placeholder vendor, which leaves the older
 * self-registered storefronts unexamined.
 */
class UrbanGoodzAuditPartnerBadges extends Command
{
    protected $signature = 'urban-goodz:audit-partner-badges
        {--withdraw-ungranted : Turn the badge off for stores that carry it with no grant on record.}
        {--store-id=* : Limit to specific store ids.}';

    protected $description = 'Report which stores claim the Urban Goodz Partner badge and whether anyone ever granted it.';

    public function handle(): int
    {
        $storeIds = array_filter((array) $this->option('store-id'));

        $carrying = Store::query()
            ->where('partner_badge_enabled', 1)
            ->when($storeIds, fn ($q) => $q->whereIn('id', $storeIds))
            ->orderBy('id')
            ->get();

        if ($carrying->isEmpty()) {
            $this->info('No store currently has partner_badge_enabled set.');
            return self::SUCCESS;
        }

        // Granted on purpose vs inherited from the column default.
        $granted = $carrying->filter(fn (Store $s) => $s->partner_badge_enabled_at !== null);
        $ungranted = $carrying->reject(fn (Store $s) => $s->partner_badge_enabled_at !== null);

        // Of the ungranted ones, these are the only ones a customer sees the
        // badge on today; the rest fail a contract check for other reasons.
        $visible = $ungranted->filter(fn (Store $s) => $s->canShowUrbanGoodzPartnerBadge());

        $this->table(['group', 'stores', 'meaning'], [
            ['granted on record', $granted->count(), 'partner_badge_enabled_at is set - somebody granted this'],
            ['no grant on record', $ungranted->count(), 'badge came from the column default, nobody granted it'],
            ['  ...and live to customers', $visible->count(), 'storefront says "Urban Goodz Partner" right now'],
        ]);

        if ($visible->isNotEmpty()) {
            $this->newLine();
            $this->warn('Claiming partner status with no grant on record:');
            $this->table(
                ['store', 'name', 'vendor email', 'business_status', 'contract', 'created'],
                $visible->take(30)->map(fn (Store $s) => [
                    $s->id,
                    mb_strimwidth((string) $s->name, 0, 32, '...'),
                    mb_strimwidth((string) ($s->vendor->email ?? '-'), 0, 34, '...'),
                    $s->business_status,
                    $s->contract_status,
                    optional($s->created_at)->toDateString(),
                ])->all()
            );
            if ($visible->count() > 30) {
                $this->line('  ... and ' . ($visible->count() - 30) . ' more.');
            }
        }

        if (!$this->option('withdraw-ungranted')) {
            $this->newLine();
            $this->info('Report only. Re-run with --withdraw-ungranted to turn the badge off for the '
                . $ungranted->count() . ' store(s) with no grant on record.');
            return self::SUCCESS;
        }

        if ($ungranted->isEmpty()) {
            $this->info('Nothing to withdraw: every badge on record was granted.');
            return self::SUCCESS;
        }

        $ids = $ungranted->pluck('id')->all();
        $written = 0;

        DB::transaction(function () use ($ids, &$written) {
            $written = Store::whereIn('id', $ids)->update([
                'partner_badge_enabled' => 0,
                'partner_badge_enabled_at' => null,
            ]);
        });

        $this->newLine();
        $this->info("Withdrew the badge from {$written} store(s) with no grant on record.");
        $this->line('Grant it back per store from Stores -> Partner Badge, which records who and when.');

        return self::SUCCESS;
    }
}
