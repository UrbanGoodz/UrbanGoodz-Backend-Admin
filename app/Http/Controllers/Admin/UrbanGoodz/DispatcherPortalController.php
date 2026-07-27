<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzDispatchCommission;
use App\Models\UrbanGoodzDispatchAuditLog;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Services\UrbanGoodz\UrbanGoodzLoadBoardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use Toast;

class DispatcherPortalController extends Controller
{
    public function __construct()
    {
        $this->middleware(['business', 'dispatcher', 'dispatch-territory']);
    }

    protected function user(): UrbanGoodzBusinessClientUser
    {
        return auth('business')->user();
    }

    protected function companyId(): int
    {
        return $this->user()->business_client_id;
    }

    protected function territoryStates(): array
    {
        return request()->attributes->get('dispatch_territory_states', []);
    }

    protected function requireDispatchPermission(string $permission): void
    {
        if (!$this->user()->hasDispatchPermission($permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    protected function getAvailableDrivers()
    {
        $query = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved');

        $territory = $this->territoryStates();
        if (!empty($territory)) {
            $query->where(function ($q) use ($territory) {
                foreach ($territory as $state) {
                    $q->orWhereRaw("FIND_IN_SET(?, IFNULL(available_states, ''))", [$state]);
                }
            });
        }

        return $query->orderBy('f_name')->get();
    }

    public function dashboard()
    {
        $companyId = $this->companyId();

        $stats = [
            'available_loads' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $companyId)
                ->where('status', 'available')->count(),
            'assigned_loads' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $companyId)
                ->where('status', 'assigned')->count(),
            'in_transit_loads' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $companyId)
                ->where('status', 'in_transit')->count(),
            'delivered_loads_30d' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $companyId)
                ->where('status', 'delivered')
                ->where('delivered_at', '>=', now()->subDays(30))->count(),
            'pending_commissions' => UrbanGoodzDispatchCommission::where('dispatch_company_id', $companyId)
                ->where('status', 'pending')->sum('commission_amount'),
            'paid_commissions_30d' => UrbanGoodzDispatchCommission::where('dispatch_company_id', $companyId)
                ->where('status', 'paid')
                ->where('paid_at', '>=', now()->subDays(30))->sum('commission_amount'),
            'total_payout_30d' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $companyId)
                ->where('delivered_at', '>=', now()->subDays(30))
                ->sum('payout_amount'),
        ];

        $recentLoads = UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $companyId)
            ->with('assignedDriver')
            ->latest()
            ->limit(10)
            ->get();

        $recentCommissions = UrbanGoodzDispatchCommission::where('dispatch_company_id', $companyId)
            ->with('load')
            ->latest()
            ->limit(5)
            ->get();

        return view('business.dispatcher.dashboard', compact('stats', 'recentLoads', 'recentCommissions'));
    }

    public function loads(Request $request)
    {
        $this->requireDispatchPermission('dispatch_loads_view');

        $query = UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $this->companyId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('origin_state')) {
            $query->where('origin_state', $request->origin_state);
        }

        if ($request->filled('destination_state')) {
            $query->where('destination_state', $request->destination_state);
        }

        if ($request->filled('load_type')) {
            $query->where('load_type', $request->load_type);
        }

        if ($request->filled('equipment_type')) {
            $query->where('equipment_type', $request->equipment_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('load_number', 'like', "%{$search}%")
                  ->orWhere('origin_city', 'like', "%{$search}%")
                  ->orWhere('destination_city', 'like', "%{$search}%")
                  ->orWhere('commodity_description', 'like', "%{$search}%");
            });
        }

        $loads = $query->with('assignedDriver')
            ->latest()
            ->paginate(25);

        $stats = [
            'total' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $this->companyId())->count(),
            'available' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $this->companyId())
                ->where('status', 'available')->count(),
            'assigned' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $this->companyId())
                ->where('status', 'assigned')->count(),
            'in_transit' => UrbanGoodzLoadBoardLoad::where('dispatch_company_id', $this->companyId())
                ->where('status', 'in_transit')->count(),
        ];

        return view('business.dispatcher.loads.index', compact('loads', 'stats'));
    }

    public function showLoad($id)
    {
        $this->requireDispatchPermission('dispatch_loads_view');

        $load = UrbanGoodzLoadBoardLoad::where('id', $id)
            ->where('dispatch_company_id', $this->companyId())
            ->with(['assignedDriver', 'dispatcherUser', 'commissions'])
            ->firstOrFail();

        $drivers = $this->getAvailableDrivers();

        $commissions = UrbanGoodzDispatchCommission::where('load_id', $id)
            ->with('dispatcher')
            ->get();

        return view('business.dispatcher.loads.show', compact('load', 'drivers', 'commissions'));
    }

    public function assignDriver(Request $request, $id, UrbanGoodzLoadBoardService $service)
    {
        $this->requireDispatchPermission('dispatch_drivers_assign');

        $load = UrbanGoodzLoadBoardLoad::where('id', $id)
            ->where('dispatch_company_id', $this->companyId())
            ->firstOrFail();

        $request->validate([
            'driver_id' => 'required|exists:delivery_men,id',
        ]);

        $driver = DeliveryMan::where('id', $request->driver_id)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->first();

        if (!$driver) {
            Toast::error(translate('Selected driver is not available'));
            return redirect()->back();
        }

        $result = $service->acceptLoad($id, $request->driver_id);

        if (!$result) {
            Toast::error(translate('Failed to assign driver. Load may no longer be available.'));
            return redirect()->back();
        }

        $load->update([
            'dispatcher_id' => $this->user()->id,
            'dispatch_status' => 'dispatched',
            'assigned_by' => null,
        ]);

        UrbanGoodzDispatchAuditLog::log(
            $this->companyId(),
            $this->user()->id,
            $load->id,
            'driver_assigned',
            null,
            (string) $request->driver_id,
            ['driver_id' => $request->driver_id, 'driver_name' => $driver->f_name . ' ' . $driver->l_name],
            "Driver assigned to load {$load->load_number}"
        );

        if ($load->payout_amount > 0 && $load->dispatch_company_id) {
            $commissionRate = $this->user()->client->dispatch_default_commission_rate ?? 15.00;
            $commissionAmount = round($load->payout_amount * ($commissionRate / 100), 2);

            UrbanGoodzDispatchCommission::create([
                'dispatch_company_id' => $this->companyId(),
                'dispatcher_id' => $this->user()->id,
                'load_id' => $load->id,
                'load_payout' => $load->payout_amount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'status' => 'pending',
            ]);

            $load->update([
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
            ]);

            UrbanGoodzDispatchAuditLog::log(
                $this->companyId(),
                $this->user()->id,
                $load->id,
                'commission_created',
                null,
                (string) $commissionAmount,
                ['rate' => $commissionRate, 'amount' => $commissionAmount],
                "Commission created: \${$commissionAmount} at {$commissionRate}%"
            );
        }

        Toast::success(translate('Driver assigned successfully'));
        return redirect()->route('dispatcher.loads.show', $id);
    }

    public function updateLoadStatus(Request $request, $id, UrbanGoodzLoadBoardService $service)
    {
        $this->requireDispatchPermission('dispatch_status_update');

        $load = UrbanGoodzLoadBoardLoad::where('id', $id)
            ->where('dispatch_company_id', $this->companyId())
            ->firstOrFail();

        $request->validate([
            'status' => 'required|in:in_transit,picked_up,delivered,cancelled',
        ]);

        $driverId = $load->assigned_driver_id;
        $oldStatus = $load->status;

        $result = $service->updateStatus($id, $request->status, $driverId);

        if (!$result) {
            Toast::error(translate('Invalid status transition'));
            return redirect()->back();
        }

        UrbanGoodzDispatchAuditLog::log(
            $this->companyId(),
            $this->user()->id,
            $load->id,
            'status_changed',
            $oldStatus,
            $request->status,
            ['driver_id' => $driverId],
            "Load status changed from {$oldStatus} to {$request->status}"
        );

        Toast::success(translate('Load status updated'));
        return redirect()->route('dispatcher.loads.show', $id);
    }

    public function drivers()
    {
        $this->requireDispatchPermission('dispatch_drivers_view');

        $drivers = $this->getAvailableDrivers();

        return view('business.dispatcher.drivers.index', compact('drivers'));
    }

    public function routes()
    {
        $this->requireDispatchPermission('dispatch_loads_view');
        $routes = UrbanGoodzDedicatedRoute::where('business_client_id', $this->companyId())
            ->with('driver')
            ->latest()
            ->paginate(25);

        return view('business.dispatcher.routes.index', compact('routes'));
    }

    public function showRoute($id)
    {
        $this->requireDispatchPermission('dispatch_loads_view');
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $this->companyId())
            ->with(['driver', 'optimizationStops.package'])
            ->findOrFail($id);

        return view('business.dispatcher.routes.show', compact('route'));
    }

    public function commissions(Request $request)
    {
        $this->requireDispatchPermission('dispatch_commissions_view');

        $query = UrbanGoodzDispatchCommission::where('dispatch_company_id', $this->companyId())
            ->with('load', 'dispatcher');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $commissions = $query->latest()->paginate(25);

        $stats = [
            'total_pending' => UrbanGoodzDispatchCommission::where('dispatch_company_id', $this->companyId())
                ->where('status', 'pending')->sum('commission_amount'),
            'total_approved' => UrbanGoodzDispatchCommission::where('dispatch_company_id', $this->companyId())
                ->where('status', 'approved')->sum('commission_amount'),
            'total_paid' => UrbanGoodzDispatchCommission::where('dispatch_company_id', $this->companyId())
                ->where('status', 'paid')->sum('commission_amount'),
            'total_earned' => UrbanGoodzDispatchCommission::where('dispatch_company_id', $this->companyId())
                ->sum('commission_amount'),
        ];

        return view('business.dispatcher.commissions.index', compact('commissions', 'stats'));
    }

    public function territory()
    {
        $this->requireDispatchPermission('dispatch_territory_manage');

        $client = $this->user()->client;

        $allStates = [
            'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA',
            'KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
            'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT',
            'VA','WA','WV','WI','WY',
        ];

        $assignedStates = $client->territory_states ?? [];
        $corridors = $client->territory_corridors ?? [];

        return view('business.dispatcher.territory.index', compact('allStates', 'assignedStates', 'corridors'));
    }

    public function updateTerritory(Request $request)
    {
        $this->requireDispatchPermission('dispatch_territory_manage');

        $request->validate([
            'territory_states' => 'nullable|array',
            'territory_states.*' => 'string|size:2',
            'territory_corridors' => 'nullable|array',
            'territory_corridors.*' => 'string|max:10',
        ]);

        $this->user()->client->update([
            'territory_states' => $request->territory_states ?? [],
            'territory_corridors' => $request->territory_corridors ?? [],
        ]);

        Toast::success(translate('Territory updated successfully'));
        return redirect()->route('dispatcher.territory');
    }

    public function users()
    {
        $this->requireDispatchPermission('dispatch_users_manage');

        $users = UrbanGoodzBusinessClientUser::where('business_client_id', $this->companyId())
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get();

        return view('business.dispatcher.users.index', compact('users'));
    }

    public function createUser()
    {
        $this->requireDispatchPermission('dispatch_users_manage');

        return view('business.dispatcher.users.create');
    }

    public function storeUser(Request $request)
    {
        $this->requireDispatchPermission('dispatch_users_manage');

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:dispatch_manager,dispatcher,dispatch_readonly,dispatch_finance',
            'permissions' => 'nullable|array',
        ]);

        $exists = UrbanGoodzBusinessClientUser::where('business_client_id', $this->companyId())
            ->where('email', $validated['email'])
            ->exists();

        if ($exists) {
            Toast::error(translate('A user with this email already exists'));
            return redirect()->back();
        }

        UrbanGoodzBusinessClientUser::create([
            'business_client_id' => $this->companyId(),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'permissions' => $validated['permissions'] ?? [],
            'is_active' => true,
            'status' => 'active',
        ]);

        Toast::success(translate('User created successfully'));
        return redirect()->route('dispatcher.users');
    }

    public function editUser($id)
    {
        $this->requireDispatchPermission('dispatch_users_manage');

        $editUser = UrbanGoodzBusinessClientUser::where('id', $id)
            ->where('business_client_id', $this->companyId())
            ->firstOrFail();

        return view('business.dispatcher.users.edit', compact('editUser'));
    }

    public function updateUser(Request $request, $id)
    {
        $this->requireDispatchPermission('dispatch_users_manage');

        $editUser = UrbanGoodzBusinessClientUser::where('id', $id)
            ->where('business_client_id', $this->companyId())
            ->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role' => 'required|in:dispatch_manager,dispatcher,dispatch_readonly,dispatch_finance',
            'permissions' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($editUser->role === 'dispatch_owner') {
            Toast::error(translate('Cannot modify the owner account'));
            return redirect()->back();
        }

        $duplicate = UrbanGoodzBusinessClientUser::where('business_client_id', $this->companyId())
            ->where('email', $validated['email'])
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {
            Toast::error(translate('A user with this email already exists'));
            return redirect()->back();
        }

        $editUser->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'permissions' => $validated['permissions'] ?? [],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->filled('password')) {
            $editUser->update(['password' => bcrypt($request->password)]);
        }

        Toast::success(translate('User updated successfully'));
        return redirect()->route('dispatcher.users');
    }

    public function deactivateUser($id)
    {
        $this->requireDispatchPermission('dispatch_users_manage');

        $editUser = UrbanGoodzBusinessClientUser::where('id', $id)
            ->where('business_client_id', $this->companyId())
            ->firstOrFail();

        if ($editUser->id === $this->user()->id) {
            Toast::error(translate('Cannot deactivate your own account'));
            return redirect()->back();
        }

        if ($editUser->role === 'dispatch_owner') {
            Toast::error(translate('Cannot deactivate the owner account'));
            return redirect()->back();
        }

        $editUser->update(['is_active' => false, 'status' => 'inactive']);

        Toast::success(translate('User deactivated'));
        return redirect()->route('dispatcher.users');
    }
}
