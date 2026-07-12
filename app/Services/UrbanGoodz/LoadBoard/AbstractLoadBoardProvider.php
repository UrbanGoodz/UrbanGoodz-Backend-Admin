<?php

namespace App\Services\UrbanGoodz\LoadBoard;

use App\Contracts\LoadBoard\LoadBoardProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractLoadBoardProvider implements LoadBoardProviderInterface
{
    protected array $config;
    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->baseUrl = $config['base_url'] ?? '';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['api_key']) && !empty($this->baseUrl);
    }

    public function getProviderSlug(): string
    {
        return class_basename(static::class);
    }

    protected function get(string $endpoint, array $query = []): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning("Load board provider {$this->getProviderSlug()} is not configured");
            return null;
        }

        try {
            $response = Http::timeout($this->config['timeout'] ?? 30)
                ->withHeaders($this->buildHeaders())
                ->get(rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/'), $query);

            if ($response->failed()) {
                Log::error("Load board provider {$this->getProviderSlug()} API error", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Load board provider {$this->getProviderSlug()} request failed", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function post(string $endpoint, array $payload = []): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning("Load board provider {$this->getProviderSlug()} is not configured");
            return null;
        }

        try {
            $response = Http::timeout($this->config['timeout'] ?? 30)
                ->withHeaders($this->buildHeaders())
                ->post(rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/'), $payload);

            if ($response->failed()) {
                Log::error("Load board provider {$this->getProviderSlug()} POST error", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Load board provider {$this->getProviderSlug()} POST failed", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    abstract protected function buildHeaders(): array;

    /**
     * Common field normalizer — maps raw state abbreviations, trims strings,
     * and casts numerics.
     */
    protected function normalizeState(?string $state): ?string
    {
        if (!$state) return null;
        $state = strtoupper(trim($state));
        $map = [
            'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
            'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE',
            'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI', 'IDAHO' => 'ID',
            'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA', 'KANSAS' => 'KS',
            'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME', 'MARYLAND' => 'MD',
            'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN', 'MISSISSIPPI' => 'MS',
            'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE', 'NEVADA' => 'NV',
            'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM', 'NEW YORK' => 'NY',
            'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH', 'OKLAHOMA' => 'OK',
            'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI', 'SOUTH CAROLINA' => 'SC',
            'SOUTH DAKOTA' => 'SD', 'TENNESSEE' => 'TN', 'TEXAS' => 'TX', 'UTAH' => 'UT',
            'VERMONT' => 'VT', 'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA', 'WEST VIRGINIA' => 'WV',
            'WISCONSIN' => 'WI', 'WYOMING' => 'WY',
        ];
        return $map[$state] ?? (strlen($state) === 2 ? $state : null);
    }

    protected function castFloat($value): ?float
    {
        if ($value === null || $value === '') return null;
        return (float) preg_replace('/[^0-9.\-]/', '', $value);
    }

    protected function castInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        return (int) preg_replace('/[^0-9\-]/', '', $value);
    }

    protected function castBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_string($value)) return in_array(strtolower($value), ['yes', 'true', '1', 'y'], true);
        return (bool) $value;
    }

    protected function parseDateTime(?string $value): ?string
    {
        if (!$value) return null;
        try {
            $dt = new \DateTime($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
