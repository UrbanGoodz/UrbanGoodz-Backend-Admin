<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzConnectedAccount;
use App\Services\UrbanGoodz\Payouts\ConnectedPayoutService;
use App\Services\UrbanGoodz\Payouts\PayoutActorResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutAccountController extends Controller
{
    public function __construct(
        private readonly PayoutActorResolver $actors,
        private readonly ConnectedPayoutService $payouts
    ) {}

    public function show(Request $request)
    {
        $actor = $this->actors->resolve($request);
        $account = $this->account($actor);

        if (! $account) {
            return response()->json(['data' => [
                'account' => [
                    'owner_role' => $actor['role'],
                    'owner_id' => $actor['id'],
                    'status' => 'setup_required',
                    'restriction_status' => 'not_started',
                    'payouts_enabled' => false,
                    'available_balance_cents' => 0,
                    'pending_balance_cents' => 0,
                ],
                'required_owner_actions' => ['begin_payout_setup'],
                'payouts' => [],
                'transfers' => [],
                'settlements' => [],
            ]]);
        }

        return response()->json(['data' => $this->payouts->status($account, $actor)]);
    }

    public function begin(Request $request)
    {
        $actor = $this->actors->resolve($request);
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'display_name' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'entity_type' => ['required', Rule::in(['individual', 'company', 'non_profit', 'government_entity'])],
        ]);

        return response()->json(['data' => $this->payouts->beginSetup($actor, $data)], 201);
    }

    public function continue(Request $request)
    {
        $actor = $this->actors->resolve($request);
        $account = $this->accountOrFail($actor);

        return response()->json(['data' => $this->payouts->continueSetup($account, $actor)]);
    }

    public function manage(Request $request)
    {
        $actor = $this->actors->resolve($request);
        $account = $this->accountOrFail($actor);

        return response()->json(['data' => $this->payouts->managementLink($account, $actor)]);
    }

    public function refresh(Request $request)
    {
        $actor = $this->actors->resolve($request);
        $account = $this->accountOrFail($actor);

        return response()->json(['data' => $this->payouts->status(
            $this->payouts->refresh($account, $actor),
            $actor
        )]);
    }

    private function account(array $actor): ?UrbanGoodzConnectedAccount
    {
        return UrbanGoodzConnectedAccount::where([
            'owner_role' => $actor['role'],
            'owner_id' => $actor['id'],
        ])->first();
    }

    private function accountOrFail(array $actor): UrbanGoodzConnectedAccount
    {
        $account = $this->account($actor);
        abort_unless($account, 404, 'Payout account has not been created.');

        return $account;
    }
}
