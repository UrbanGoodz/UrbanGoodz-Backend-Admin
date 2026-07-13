<?php

namespace App\Services\FashionFit;

use App\Contracts\FashionFitMeasurementProvider;
use App\Models\FashionFitAnalysis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class HttpFashionFitMeasurementProvider implements FashionFitMeasurementProvider
{
    public function configured(): bool
    {
        return (bool) config('fashion_fit_ai.enabled')
            && filled(config('fashion_fit_ai.endpoint'))
            && filled(config('fashion_fit_ai.api_key'))
            && filled(config('fashion_fit_ai.model'));
    }

    public function name(): string
    {
        return (string) config('fashion_fit_ai.provider', 'http');
    }

    public function analyze(FashionFitAnalysis $analysis, Collection $photos): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Fashion Fit AI provider is not configured.');
        }

        $profile = $analysis->profile;
        $request = Http::acceptJson()
            ->withToken((string) config('fashion_fit_ai.api_key'))
            ->timeout((int) config('fashion_fit_ai.timeout', 90))
            ->retry(2, 500, throw: false);

        foreach ($photos as $photo) {
            $file = $photo->file;
            $contents = Storage::disk($file->disk)->get($file->stored_path);
            $request = $request->attach("photos[{$photo->view}]", $contents, basename($file->stored_path), [
                'Content-Type' => $file->mime_type,
            ]);
        }

        $response = $request->post((string) config('fashion_fit_ai.endpoint'), [
            'analysis_id' => $analysis->uuid,
            'profile_id' => $profile->uuid,
            'height' => (float) $profile->calibration_height,
            'unit' => $profile->units,
            'model' => config('fashion_fit_ai.model'),
            'model_version' => config('fashion_fit_ai.model_version'),
            'output_format' => 'structured_json',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Fashion Fit AI provider request failed with HTTP '.$response->status().'.');
        }

        $result = $response->json();
        if (! is_array($result)) {
            throw new RuntimeException('Fashion Fit AI provider returned invalid JSON.');
        }

        return $result;
    }
}
