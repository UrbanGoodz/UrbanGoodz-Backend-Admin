<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentRequest;
use App\Traits\Processor;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripePaymentController extends Controller
{
    use Processor;

    private $config_values;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('stripe', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }
        $this->payment = $payment;
    }

    public function index(Request $request): View|Factory|JsonResponse|Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        $config = $this->config_values;

        return view('payment-views.stripe', compact('data', 'config'));
    }

    public function payment_process_3d(Request $request): JsonResponse
    {
        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        $payment_amount = $data['payment_amount'];

        Stripe::setApiKey($this->config_values->api_key);
        header('Content-Type: application/json');
        $currency_code = $data->currency_code;

        $extraData = json_decode($data['additional_data'], true) ?: [];
        if (isset($extraData['business_name']) || isset($extraData['business_logo'])) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
            $business_logo = $business->business_logo ??  url('/');
        } else {
            $logo = \App\Models\BusinessSetting::where('key', 'logo')->first();
            $logo = $logo->value ?? '';
            $name = \App\Models\BusinessSetting::where('key', 'business_name')->first();
            $business_name = $name->value ?? "my_business";
            $business_logo = $logo ? asset('storage/app/public/business/' . $logo): url('/');
        }

        $currencies_not_supported_cents = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $checkout_session = Session::create([
            // 'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency_code ?? 'usd',
                    'unit_amount' => in_array($currency_code, $currencies_not_supported_cents) ? (int)$payment_amount : ($payment_amount * 100),
                    'product_data' => [
                        'name' => $business_name,
                        'images' => [$business_logo],
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/') . '/payment/stripe/success?session_id={CHECKOUT_SESSION_ID}&payment_id=' . $data->id,
            'cancel_url' => url('/') . '/payment/stripe/canceled?payment_id=' . $data->id,
        ]);

        // Remember which Checkout Session belongs to this payment: if the customer pays and then closes the tab, the
        // success page is never reached and only the scheduled reconciler (payments:reconcile-stripe-checkout) can finish it.
        $extraData['stripe_session_id'] = $checkout_session->id;
        $data->additional_data = json_encode($extraData);
        $data->save();

        return response()->json(['id' => $checkout_session->id]);
    }

    public function success(Request $request)
    {
        Stripe::setApiKey($this->config_values->api_key);
        $session = Session::retrieve($request->get('session_id'));

        if ($session->payment_status == 'paid' && $session->status == 'complete') {
            $this->completePaidSession((string) $request['payment_id'], $session);

            $data = $this->payment::where(['id' => $request['payment_id']])->first();

            return $this->payment_response($data,'success');
        }
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data,'fail');
    }

    public function canceled(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'cancel');
    }

    /**
     * Mark a payment paid and run its success hook exactly once. Both the customer's return to the success page and
     * the scheduled reconciler call this, so whichever arrives second does nothing (the is_paid = 0 guard).
     *
     * @return bool true when THIS call completed the payment
     */
    public function completePaidSession(string $paymentId, $session): bool
    {
        $claimed = $this->payment::where(['id' => $paymentId, 'is_paid' => 0])->update([
            'payment_method' => 'stripe',
            'is_paid' => 1,
            'transaction_id' => $session->payment_intent,
        ]);

        if ($claimed === 0) {
            return false;
        }

        $data = $this->payment::where(['id' => $paymentId])->first();
        if (isset($data) && function_exists($data->success_hook)) {
            call_user_func($data->success_hook, $data);
        }

        return true;
    }

    /** Used by the reconciler: look a Checkout Session up with this gateway's own keys. */
    public function retrieveSession(string $sessionId)
    {
        Stripe::setApiKey($this->config_values->api_key);

        return Session::retrieve($sessionId);
    }
}
