<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzPackageScan;
use App\Services\UrbanGoodz\PackageScanWorkflowService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UrbanGoodzDriverPackageScanController extends Controller
{
    public function store(
        Request $request,
        $routeId,
        PackageScanWorkflowService $workflow
    ) {
        $driver = $this->driver($request);
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $route = $this->assignedRoute($routeId, $driver->id);
            $result = $workflow->process($route, $driver, $validator->validated());
            return response()->json($result, $result['duplicate'] ? 200 : 201);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Package or assigned route not found.'], 404);
        } catch (DomainException $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }
    }

    public function sync(
        Request $request,
        PackageScanWorkflowService $workflow
    ) {
        $driver = $this->driver($request);
        $validator = Validator::make($request->all(), [
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.route_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $results = [];
        foreach ($request->input('events') as $index => $event) {
            $event['was_offline'] = true;
            $eventValidator = Validator::make($event, $this->rules());
            if ($eventValidator->fails()) {
                $results[] = [
                    'index' => $index,
                    'synced' => false,
                    'status' => 422,
                    'errors' => $eventValidator->errors(),
                ];
                continue;
            }
            try {
                $route = $this->assignedRoute($event['route_id'], $driver->id);
                $result = $workflow->process($route, $driver, $eventValidator->validated());
                $results[] = array_merge([
                    'index' => $index,
                    'synced' => true,
                    'status' => $result['duplicate'] ? 200 : 201,
                ], $result);
            } catch (ModelNotFoundException) {
                $results[] = [
                    'index' => $index,
                    'synced' => false,
                    'status' => 404,
                    'error' => 'Package or assigned route not found.',
                ];
            } catch (DomainException $exception) {
                $results[] = [
                    'index' => $index,
                    'synced' => false,
                    'status' => 409,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return response()->json([
            'results' => $results,
            'accepted' => collect($results)->where('synced', true)->count(),
            'rejected' => collect($results)->where('synced', false)->count(),
        ]);
    }

    public function storeGroup(
        Request $request,
        $routeId,
        PackageScanWorkflowService $workflow
    ) {
        $driver = $this->driver($request);
        $validator = Validator::make($request->all(), $this->groupRules());
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $route = $this->assignedRoute($routeId, $driver->id);
            $result = $workflow->processGroup($route, $driver, $validator->validated());
            return response()->json($result, $result['duplicate'] ? 200 : 201);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Package or assigned route not found.'], 404);
        } catch (DomainException $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }
    }

    public function history(Request $request, $routeId)
    {
        $driver = $this->driver($request);
        $route = $this->assignedRoute($routeId, $driver->id);

        return response()->json([
            'events' => UrbanGoodzPackageScan::query()
                ->where('dedicated_route_id', $route->id)
                ->where('scanned_by', $driver->id)
                ->with('package:id,tracking_id,barcode,qr_code,status')
                ->latest('id')
                ->paginate(min(100, max(1, (int) $request->input('per_page', 25)))),
        ]);
    }

    private function rules(): array
    {
        return [
            'action' => ['required', 'in:load,pickup,delivery,proof,exception,fail,cancel,return,redelivery'],
            'identifier' => ['required', 'string', 'max:255'],
            'identifier_type' => ['required', 'in:barcode,qr_code,tracking_id,manual'],
            'input_method' => ['nullable', 'in:barcode,qr_code,manual'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
            'was_offline' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'string'],
            'signature' => ['nullable', 'string'],
            'exception_reason' => ['nullable', 'string', 'max:500'],
            'return_destination' => ['nullable', 'in:pickup,hub,business'],
            'return_location' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function groupRules(): array
    {
        return [
            'action' => ['required', 'in:load,pickup,delivery,proof,exception,fail,cancel,return,redelivery'],
            'group_idempotency_key' => ['required', 'string', 'max:100'],
            'packages' => ['required', 'array', 'min:1', 'max:100'],
            'packages.*.identifier' => ['required', 'string', 'max:255'],
            'packages.*.identifier_type' => ['required', 'in:barcode,qr_code,tracking_id,manual'],
            'input_method' => ['nullable', 'in:barcode,qr_code,manual'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
            'was_offline' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'string'],
            'signature' => ['nullable', 'string'],
            'exception_reason' => ['nullable', 'string', 'max:500'],
            'return_destination' => ['nullable', 'in:pickup,hub,business'],
            'return_location' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function driver(Request $request)
    {
        $driver = $request->user('delivery_men') ?? auth('delivery_men')->user();
        abort_unless($driver, 401, 'Unauthenticated driver');
        return $driver;
    }

    private function assignedRoute($routeId, int $driverId): UrbanGoodzDedicatedRoute
    {
        return UrbanGoodzDedicatedRoute::query()
            ->whereKey($routeId)
            ->where('assigned_driver_id', $driverId)
            ->firstOrFail();
    }
}
