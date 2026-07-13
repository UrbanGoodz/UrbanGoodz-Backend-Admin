<?php

namespace App\Contracts;

use App\Models\FashionFitAnalysis;
use Illuminate\Support\Collection;

interface FashionFitMeasurementProvider
{
    public function analyze(FashionFitAnalysis $analysis, Collection $photos): array;
    public function configured(): bool;
    public function name(): string;
}
