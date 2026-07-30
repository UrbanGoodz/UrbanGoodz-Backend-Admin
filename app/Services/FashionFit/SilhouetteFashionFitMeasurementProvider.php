<?php

namespace App\Services\FashionFit;

use App\Contracts\FashionFitMeasurementProvider;
use App\Models\FashionFitAnalysis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * In-platform measurement provider. Runs {@see SilhouetteMeasurementEngine}
 * against the customer's stored front and side photos, so Fashion Fit produces
 * real measurements without depending on an external AI vendor.
 */
class SilhouetteFashionFitMeasurementProvider implements FashionFitMeasurementProvider
{
    public function __construct(private readonly SilhouetteMeasurementEngine $engine) {}

    public function configured(): bool
    {
        return extension_loaded('gd');
    }

    public function name(): string
    {
        return 'silhouette';
    }

    public function analyze(FashionFitAnalysis $analysis, Collection $photos): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Fashion Fit silhouette engine is not configured: the GD extension is missing.');
        }

        $byView = $photos->keyBy('view');
        $front = $byView->get('front');
        $side = $byView->get('side');
        if (! $front || ! $side) {
            throw new RuntimeException('Fashion Fit required photo views are missing.');
        }

        $profile = $analysis->profile;

        return $this->engine->measure(
            $this->contents($front),
            $this->contents($side),
            (float) $profile->calibration_height,
            (string) $profile->units,
        );
    }

    private function contents(object $photo): string
    {
        $file = $photo->file;
        $contents = Storage::disk($file->disk)->get($file->stored_path);
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('A Fashion Fit photo could not be read from storage.');
        }

        return $contents;
    }
}
