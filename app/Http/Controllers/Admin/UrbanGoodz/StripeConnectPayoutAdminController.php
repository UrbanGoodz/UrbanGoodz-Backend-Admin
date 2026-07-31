<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzConnectedAccount;
use App\Models\UrbanGoodzConnectedPayout;
use App\Models\UrbanGoodzPayoutTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StripeConnectPayoutAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManage();

        return response()->json(['data' => [
            'accounts' => UrbanGoodzConnectedAccount::query()
                ->when($request->filled('role'), fn ($q) => $q->where('owner_role', $request->string('role')))
                ->latest()->paginate(100),
            'failed_transfers' => UrbanGoodzPayoutTransfer::whereIn('status', ['failed', 'blocked', 'manual_review'])
                ->with(['account', 'recipient.settlement'])->latest()->limit(100)->get(),
            'failed_or_returned_payouts' => UrbanGoodzConnectedPayout::whereIn('status', ['failed', 'returned'])
                ->latest()->limit(100)->get(),
            'role_controls' => DB::table('urban_goodz_payout_role_controls')->orderBy('owner_role')->get(),
            'audit_history' => DB::table('urban_goodz_payout_audit_events')->latest('created_at')->limit(200)->get(),
        ]]);
    }

    public function updateAccount(Request $request, UrbanGoodzConnectedAccount $account)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'admin_payouts_enabled' => ['sometimes', 'boolean'],
            'manual_hold' => ['sometimes', 'boolean'],
            'refund_hold' => ['sometimes', 'boolean'],
            'is_suspended' => ['sometimes', 'boolean'],
            'instant_payout_eligible' => ['sometimes', 'boolean'],
            'minimum_payout_cents' => ['sometimes', 'integer', 'min:0'],
            'payout_delay_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'payout_schedule' => ['sometimes', Rule::in(['manual', 'daily', 'weekly', 'monthly'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $reason = $data['reason'];
        unset($data['reason']);
        $account->update($data);
        $this->audit('connected_account.admin_control_updated', $account->id, ['reason' => $reason, 'changes' => array_keys($data)]);

        return response()->json(['data' => $account->fresh()]);
    }

    public function updateRole(Request $request, string $role)
    {
        $this->authorizeManage();
        abort_unless(in_array($role, UrbanGoodzConnectedAccount::ROLES, true), 422);
        $data = $request->validate([
            'payouts_enabled' => ['required', 'boolean'],
            'minimum_payout_cents' => ['required', 'integer', 'min:0'],
            'payout_schedule' => ['required', Rule::in(['manual', 'daily', 'weekly', 'monthly'])],
            'payout_delay_days' => ['required', 'integer', 'min:0', 'max:90'],
            'refund_hold' => ['required', 'boolean'],
            'instant_payout_allowed' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $reason = $data['reason'];
        unset($data['reason']);
        DB::transaction(function () use ($role, $data) {
            DB::table('urban_goodz_payout_role_controls')->updateOrInsert(
                ['owner_role' => $role],
                $data + ['updated_by_admin_id' => auth('admin')->id(), 'updated_at' => now(), 'created_at' => now()]
            );
            UrbanGoodzConnectedAccount::where('owner_role', $role)->update([
                'admin_payouts_enabled' => $data['payouts_enabled'],
                'minimum_payout_cents' => $data['minimum_payout_cents'],
                'payout_schedule' => $data['payout_schedule'],
                'payout_delay_days' => $data['payout_delay_days'],
                'refund_hold' => $data['refund_hold'],
                'instant_payout_eligible' => $data['instant_payout_allowed'],
            ]);
        });
        $this->audit('payout_role.control_updated', 0, ['role' => $role, 'reason' => $reason]);

        return response()->json(['data' => DB::table('urban_goodz_payout_role_controls')
            ->where('owner_role', $role)->first()]);
    }

    public function bindActor(Request $request)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'owner_role' => ['required', Rule::in(UrbanGoodzConnectedAccount::ROLES)],
            'owner_id' => ['required', 'integer', 'min:1'],
            'actor_type' => ['required', Rule::in(['vendor', 'driver', 'user', 'business_user'])],
            'actor_id' => ['required', 'integer', 'min:1'],
            'can_manage' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $reason = $data['reason'];
        unset($data['reason']);
        DB::table('urban_goodz_payout_actor_bindings')->updateOrInsert(
            collect($data)->only(['owner_role', 'owner_id', 'actor_type', 'actor_id'])->all(),
            ['can_manage' => $data['can_manage'], 'created_by_admin_id' => auth('admin')->id(),
                'created_at' => now(), 'updated_at' => now()]
        );
        $this->audit('payout_actor.binding_updated', 0, ['reason' => $reason] + $data);

        return response()->json(['data' => $data], 201);
    }

    private function authorizeManage(): void
    {
        abort_unless(Helpers::module_permission_check('urban_goodz_financial_control_manage'), 403);
    }

    private function audit(string $action, int $id, array $metadata): void
    {
        DB::table('urban_goodz_payout_audit_events')->insert([
            'actor_type' => 'admin',
            'actor_id' => auth('admin')->id(),
            'action' => $action,
            'auditable_type' => $id ? UrbanGoodzConnectedAccount::class : 'payout_control',
            'auditable_id' => $id,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
        ]);
    }
}
