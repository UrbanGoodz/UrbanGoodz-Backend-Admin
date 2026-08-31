<?php

namespace App\Console\Commands;

use App\Models\UrbanGoodzSourcedBusiness;
use App\Services\UrbanGoodzCompletenessScoringService;
use Illuminate\Console\Command;

class UrbanGoodzScoreCompleteness extends Command
{
    protected $signature = 'urban-goodz:score-completeness
        {--business-id= : Score a single sourced business by id.}
        {--apply : Persist scores. Without this flag, prints results only.}';

    protected $description = 'Compute (and optionally persist) profile-completeness scores for sourced businesses.';

    public function handle(UrbanGoodzCompletenessScoringService $scorer)
    {
        $apply = (bool) $this->option('apply');
        $businessId = $this->option('business-id');

        $query = UrbanGoodzSourcedBusiness::with(['images', 'products.sourcedImages']);
        if ($businessId) {
            $query->where('id', $businessId);
        }

        $businesses = $query->get();
        if ($businesses->isEmpty()) {
            $this->warn('No matching sourced businesses found.');
            return self::SUCCESS;
        }

        $this->info($apply ? 'MODE: APPLY (scores will be saved)' : 'MODE: DRY-RUN (no changes saved)');

        foreach ($businesses as $business) {
            $result = $scorer->score($business);
            $missing = collect($result['breakdown'])->filter(fn ($met) => !$met)->keys()->implode(', ');
            $this->line(sprintf(
                '  id=%d %-40s score=%d%%  missing: %s',
                $business->id,
                $business->name,
                $result['score'],
                $missing ?: 'none'
            ));

            if ($apply) {
                $business->update([
                    'completeness_score' => $result['score'],
                    'completeness_breakdown' => $result['breakdown'],
                ]);
            }
        }

        $this->info("Scored {$businesses->count()} business(es).");
        return self::SUCCESS;
    }
}
