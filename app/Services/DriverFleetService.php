<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where are my drivers right now? One place decides who may see which driver:
 * admins see everyone (optionally filtered to one store); a vendor sees ONLY the drivers that belong to their own
 * store (delivery_men.store_id), never Urban Goodz's own fleet or another vendor's drivers.
 */
class DriverFleetService
{
    public function __construct(private readonly DriverLocator $locator) {}

    /**
     * @param int|null $storeId null = every driver (admin); an id = only that store's own drivers
     * @return array{drivers: array<int, array<string,mixed>>, generated_at: string}
     */
    public function fleet(?int $storeId = null): array
    {
        $query = DB::table('delivery_men')->where('application_status', 'approved');
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }
        $drivers = $query->get(['id', 'f_name', 'l_name', 'phone', 'active', 'status', 'store_id', 'image']);
        if ($drivers->isEmpty()) {
            return ['drivers' => [], 'generated_at' => now()->toIso8601String()];
        }

        $ids = $drivers->pluck('id')->map(fn ($i) => (int) $i)->all();
        $positions = $this->locator->positions($ids);
        $routes = $this->activeRoutes($ids);

        $out = [];
        foreach ($drivers as $d) {
            $pos = $positions[(int) $d->id] ?? null;
            $route = $routes[(int) $d->id] ?? null;
            $out[] = [
                'id' => (int) $d->id,
                'name' => trim($d->f_name . ' ' . $d->l_name),
                'phone' => $d->phone,
                'online' => (bool) $d->active && (int) $d->status === 1,
                'store_id' => $d->store_id !== null ? (int) $d->store_id : null,
                'lat' => $pos['lat'] ?? null,
                'lng' => $pos['lng'] ?? null,
                'position_source' => $pos['source'] ?? null,        // live | saved
                'position_age_minutes' => $pos['age_minutes'] ?? null,
                'state' => $this->state($d, $pos, $route),
                'route' => $route,
            ];
        }

        // Drivers on a route first, then available, then the rest; stable by name.
        usort($out, fn ($a, $b) => [$this->rank($a['state']), $a['name']] <=> [$this->rank($b['state']), $b['name']]);

        return ['drivers' => $out, 'generated_at' => now()->toIso8601String()];
    }

    private function state($driver, ?array $pos, ?array $route): string
    {
        if ($route) {
            return 'on_route';
        }
        if (! ((bool) $driver->active && (int) $driver->status === 1)) {
            return 'offline';
        }

        return ($pos && ($pos['source'] ?? '') === 'live') ? 'available' : 'no_signal';
    }

    private function rank(string $state): int
    {
        return ['on_route' => 0, 'available' => 1, 'no_signal' => 2, 'offline' => 3][$state] ?? 4;
    }

    /** @return array<int, array<string,mixed>> driver id => the route they are working right now */
    private function activeRoutes(array $driverIds): array
    {
        if (! Schema::hasTable('urban_goodz_dedicated_routes')) {
            return [];
        }
        $rows = DB::table('urban_goodz_dedicated_routes')
            ->whereIn('assigned_driver_id', $driverIds)
            ->whereIn('status', ['in_progress', 'active', 'started'])
            ->orderByDesc('id')
            ->get(['id', 'route_name', 'assigned_driver_id', 'total_packages', 'completed_packages']);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->assigned_driver_id;
            if (isset($out[$id])) {
                continue;
            }
            $out[$id] = [
                'id' => (int) $r->id,
                'name' => $r->route_name,
                'total_packages' => (int) $r->total_packages,
                'completed_packages' => (int) $r->completed_packages,
            ];
        }

        return $out;
    }
}
