<?php

namespace App\Jobs;

use App\Models\FashionFitAnalysis;
use App\Services\FashionFit\FashionFitAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFashionFitAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public array $backoff = [30, 120, 300];

    public function __construct(public int $analysisId)
    {
        $this->tries = (int) config('fashion_fit_ai.max_attempts', 3);
    }

    public function handle(FashionFitAnalysisService $service): void
    {
        $service->process(FashionFitAnalysis::findOrFail($this->analysisId));
    }
}
