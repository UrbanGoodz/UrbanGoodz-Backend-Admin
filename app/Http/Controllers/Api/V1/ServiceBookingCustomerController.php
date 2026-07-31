<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ServiceBookingPaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzProviderService;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceRequest;
use App\Models\UrbanGoodzServiceReview;
use App\Models\UrbanGoodzServiceDispute;
use App\Services\ServiceBookings\ServiceBookingAvailabilityService;
use App\Services\ServiceBookings\ServiceBookingRefundService;
use App\Services\ServiceBookings\ServiceBookingWorkflow;
use App\Services\ServiceBookings\ServiceProviderDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;

class ServiceBookingCustomerController extends Controller
{
    public function categories()
    {
        return response()->json(['data' => collect(config('service_bookings.categories'))->map(
            fn (string $slug) => ['slug' => $slug, 'name' => Str::headline($slug)]
        )->values()]);
    }

    public function providers(Request $request, ServiceProviderDiscoveryService $discovery)
    {
        $data = $request->validate([
            'category' => 'nullable|in:'.implode(',', config('service_bookings.categories')),
            'postal_code' => 'nullable|string|max:24',
            'city' => 'nullable|string|max:120',
            'location_mode' => 'nullable|in:mobile,in_person,remote',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'radius_miles' => 'nullable|integer|min:1|max:500',
            'search' => 'nullable|string|max:120',
            'sort' => 'nullable|in:distance,rating,newest',
        ]);

        $query = $discovery->baseQuery()
            ->when($request->filled('category'), fn($q)=>$q->whereHas('services', fn($s)=>$s->where('category',$request->string('category'))->where('is_active',true)))
            ->when($request->filled('postal_code'), fn($q)=>$q->whereHas('areas', fn($a)=>$a->where('postal_code',$request->string('postal_code'))->where('is_active',true)))
            ->when($request->filled('city'), fn($q)=>$q->whereHas('areas', fn($a)=>$a->where('city',$request->string('city'))->where('is_active',true)))
            ->when($request->filled('location_mode'), fn($q)=>$q->whereJsonContains('location_modes',(string)$request->string('location_mode')))
            ->when($request->filled('search'), fn($q)=>$q->where('business_name','like','%'.$request->string('search').'%'))
            ->with([
                'services'=>fn($q)=>$q->where('is_active',true),
                'availability'=>fn($q)=>$q->where('is_active',true),
                'areas'=>fn($q)=>$q->where('is_active',true),
                'portfolioItems'=>fn($q)=>$q->where('is_active',true)->orderBy('sort_order'),
            ]);

        $limit = min((int) $request->input('limit', 20), 100);

        // Distance filtering cannot be expressed as a plain paginated query
        // because coverage depends on each area's own radius, so the exact
        // check runs after a bounding-box narrowing.
        if (isset($data['latitude'], $data['longitude'])) {
            $lat = (float) $data['latitude'];
            $lon = (float) $data['longitude'];
            $radius = isset($data['radius_miles']) ? (int) $data['radius_miles'] : null;
            $candidates = $discovery->scopeWithinReach($query, $lat, $lon, $radius)
                ->limit(500)
                ->get();
            $matched = $discovery->attachDistances($candidates, $lat, $lon, $radius);
            if (($data['sort'] ?? 'distance') === 'rating') {
                usort($matched, fn ($a, $b) => (float) $b->rating <=> (float) $a->rating);
            }

            return response()->json([
                'data' => array_slice($matched, 0, $limit),
                'meta' => ['total' => count($matched), 'sorted_by' => $data['sort'] ?? 'distance'],
            ]);
        }

        $query = match ($data['sort'] ?? null) {
            'rating' => $query->orderByDesc('rating'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('rating'),
        };

        return response()->json($query->paginate($limit));
    }

    public function provider(UrbanGoodzServiceProvider $provider)
    {
        abort_unless($provider->approval_status==='approved' && $provider->is_verified && $provider->is_active,404);
        return response()->json($provider->load([
            'services'=>fn($q)=>$q->where('is_active',true),
            'availability'=>fn($q)=>$q->where('is_active',true),
            'areas'=>fn($q)=>$q->where('is_active',true),
            'portfolioItems'=>fn($q)=>$q->where('is_active',true)->orderBy('sort_order'),
            'reviews',
        ]));
    }

    public function slots(
        Request $request,
        UrbanGoodzServiceProvider $provider,
        UrbanGoodzProviderService $service,
        ServiceBookingAvailabilityService $availability
    ) {
        abort_unless((int) $service->provider_id === (int) $provider->id && $service->is_active, 404);
        abort_unless($provider->approval_status === 'approved' && $provider->is_verified && $provider->is_active, 404);
        $data = $request->validate(['from' => 'required|date|after_or_equal:today', 'until' => 'required|date|after_or_equal:from']);
        $from = Carbon::parse($data['from']);
        $until = Carbon::parse($data['until']);
        abort_if($from->diffInDays($until) > 31, 422, 'Availability searches are limited to 31 days.');

        return response()->json(['data' => $availability->slots($provider, $service, $from, $until)]);
    }

    public function index(Request $request)
    {
        return response()->json(UrbanGoodzServiceRequest::where('user_id',$request->user()->id)->with(['assignedProvider','service','serviceArea','appointments','activeQuote'])->latest()->paginate(30));
    }

    public function store(Request $request, ServiceBookingAvailabilityService $availability)
    {
        $data = $request->validate([
            'provider_id'=>'required|integer','service_id'=>'required|integer','requested_start_at'=>'required|date|after:now',
            'location_mode'=>'required|in:mobile,in_person,remote','location_details'=>'nullable|string|max:1000','notes'=>'nullable|string|max:2000',
            'service_area_id'=>'nullable|integer',
        ]);
        $provider = UrbanGoodzServiceProvider::whereKey($data['provider_id'])->where('approval_status','approved')->where('is_verified',true)->where('is_active',true)->firstOrFail();
        $service = UrbanGoodzProviderService::whereKey($data['service_id'])->where('provider_id',$provider->id)->where('is_active',true)->firstOrFail();
        abort_unless(in_array($service->category,config('service_bookings.categories'),true),422,'Unsupported service category.');
        abort_unless(in_array($data['location_mode'],$provider->location_modes ?? ['in_person'],true),422,'Location mode is not offered by this provider.');
        if (isset($data['service_area_id'])) {
            abort_unless(
                $provider->areas()->whereKey($data['service_area_id'])->where('is_active', true)->exists(),
                422,
                'The selected service area is not offered by this provider.'
            );
        }
        if ($data['location_mode'] === 'mobile') {
            abort_unless(
                isset($data['service_area_id']) && $provider->areas()->whereKey($data['service_area_id'])->where('is_active', true)->exists(),
                422,
                'An active provider service area is required for mobile service.'
            );
        }
        $start = Carbon::parse($data['requested_start_at']);
        $end = $availability->assertAvailable($provider, $service, $start);

        $user=$request->user();
        $booking=UrbanGoodzServiceRequest::create([
            'user_id'=>$user->id,'customer_name'=>trim($user->f_name.' '.$user->l_name),'customer_email'=>$user->email,'customer_phone'=>$user->phone,
            'service_type'=>$service->category,'description'=>$data['notes']??null,'status'=>'requested','assigned_vendor_id'=>$provider->vendor_id,
            'provider_id'=>$provider->id,'provider_service_id'=>$service->id,'location_mode'=>$data['location_mode'],'location_details'=>$data['location_details']??null,
            'service_area_id'=>$data['service_area_id']??null,
            'requested_start_at'=>$start,'scheduled_at'=>$start,'scheduled_end_at'=>$end,'quoted_amount_minor'=>$service->requires_quote?null:$service->price_minor,
            'deposit_amount_minor'=>$service->deposit_minor,'currency'=>$service->currency,'payment_status'=>(($service->deposit_minor?:$service->price_minor)>0?'pending':'not_required'),
        ]);
        $booking->events()->create(['actor_type'=>'customer','actor_id'=>$user->id,'from_status'=>null,'to_status'=>'requested']);
        app(\App\Services\UrbanGoodzNotificationService::class)->notifyVendor((int) $provider->vendor_id, 'New service booking request', 'A customer requested '.$service->name.'.', ['type'=>'service_booking','booking_id'=>$booking->id,'status'=>'requested']);
        return response()->json(['message'=>'Service booking requested.','data'=>$booking->load('assignedProvider')],201);
    }

    public function show(Request $request, UrbanGoodzServiceRequest $booking)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404);
        return response()->json($booking->load(['assignedProvider','service','serviceArea','appointments','events','quotes','activeQuote']));
    }

    public function confirm(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404);
        abort_unless($booking->status==='accepted',409,'Provider acceptance is required.');
        $minimum = min((int) $booking->deposit_amount_minor, (int) $booking->quoted_amount_minor);
        abort_unless($booking->payment_status==='not_required' || (int) $booking->amount_paid_minor >= $minimum,409,'Required deposit has not been accepted.');
        return response()->json(['data'=>$workflow->transition($booking,'confirmed','customer',$request->user()->id)]);
    }

    public function acceptQuote(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404);
        abort_unless($booking->status==='quoted' && $booking->quoted_amount_minor!==null,409,'An active provider quote is required.');
        $quote = $booking->activeQuote;
        abort_unless($quote && $quote->status === 'offered' && (!$quote->expires_at || $quote->expires_at->isFuture()), 409, 'The provider quote is no longer active.');
        $accepted = DB::transaction(function () use ($quote, $workflow, $booking, $request) {
            $quote->update(['status' => 'accepted', 'accepted_at' => now()]);
            return $workflow->transition($booking,'accepted','customer',$request->user()->id);
        });
        return response()->json(['data'=>$accepted]);
    }

    public function pay(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingPaymentGateway $gateway)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404);
        abort_unless(in_array($booking->status, ['accepted','confirmed','en_route','started'], true) && in_array($booking->payment_status, ['pending','partially_paid'], true),409,'Payment is not available.');
        $data=$request->validate(['payment_token'=>'required|string|max:500','idempotency_key'=>'required|string|max:100','payment_scope'=>'nullable|in:deposit,balance,full']);
        $existing=UrbanGoodzPaymentTransaction::where('idempotency_key',$data['idempotency_key'])->where('payable_type',UrbanGoodzServiceRequest::class)->where('payable_id',$booking->id)->first();
        if($existing && $existing->internal_status==='succeeded'){ return response()->json(['message'=>'Payment already accepted.','payment_status'=>$booking->fresh()->payment_status]); }
        $total = (int) $booking->quoted_amount_minor;
        $remaining = max(0, $total - (int) $booking->amount_paid_minor);
        $scope = $data['payment_scope'] ?? ((int) $booking->amount_paid_minor === 0 && (int) $booking->deposit_amount_minor > 0 ? 'deposit' : 'balance');
        $amount = $scope === 'deposit'
            ? min($remaining, max(0, (int) $booking->deposit_amount_minor - (int) $booking->amount_paid_minor))
            : $remaining;
        abort_if($amount <= 0, 409, 'No payment balance is due.');
        try { $result=$gateway->charge($booking,$data['payment_token'],$data['idempotency_key'],$amount); }
        catch(Throwable $e){ report(new \RuntimeException('Service booking payment failed: '.$e->getMessage())); return response()->json(['message'=>'Payment was not accepted.','code'=>'payment_failed'],502); }
        $isSandbox = config('service_bookings.payment.sandbox', true);
        DB::transaction(function()use($booking,$data,$result,$isSandbox,$amount,$scope,$total){
            UrbanGoodzPaymentTransaction::updateOrCreate(['idempotency_key'=>$data['idempotency_key']],['payable_type'=>UrbanGoodzServiceRequest::class,'payable_id'=>$booking->id,'provider'=>config('service_bookings.payment.provider','stripe'),'environment'=>$isSandbox?'sandbox':'live','transaction_type'=>$scope,'internal_status'=>'succeeded','provider_status'=>$result['status'],'amount_minor'=>$amount,'currency'=>$booking->currency,'provider_payment_id'=>$result['id'],'processed_at'=>now()]);
            $paid = (int) $booking->amount_paid_minor + $amount;
            $booking->update(['amount_paid_minor'=>$paid,'payment_status'=>$paid >= $total?'paid':'partially_paid']);
        });
        $paidBooking=$booking->fresh();
        return response()->json(['message'=>'Payment accepted.','payment_status'=>$paidBooking->payment_status,'amount_paid_minor'=>$paidBooking->amount_paid_minor]);
    }

    public function cancel(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow, ServiceBookingRefundService $refunds)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404); $data=$request->validate(['reason'=>'required|string|max:1000']);
        abort_if(in_array($booking->status, ['started','completed','canceled','declined'], true), 409, 'This booking can no longer be canceled by the customer.');
        $refundable = max(0, (int) $booking->amount_paid_minor - (int) $booking->refunded_amount_minor);
        if ($refundable > 0) {
            try {
                $refunds->refund($booking, $refundable, 'customer-cancel-'.$booking->id.'-'.$booking->amount_paid_minor);
            } catch (Throwable $e) {
                report(new \RuntimeException('Service booking refund failed: '.$e->getMessage()));
                return response()->json(['message'=>'Cancellation could not be finalized because the refund failed.','code'=>'refund_failed'],502);
            }
        }
        return response()->json(['data'=>$workflow->transition($booking,'canceled','customer',$request->user()->id,['reason'=>$data['reason']])]);
    }

    public function reschedule(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow, ServiceBookingAvailabilityService $availability)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404); $data=$request->validate(['requested_start_at'=>'required|date|after:now']);
        $service = UrbanGoodzProviderService::findOrFail($booking->provider_service_id);
        $start = Carbon::parse($data['requested_start_at']);
        $end = $availability->assertAvailable($booking->assignedProvider, $service, $start, $booking->id);
        $booking->update(['requested_start_at'=>$start,'scheduled_at'=>$start,'scheduled_end_at'=>$end]);
        return response()->json(['data'=>$workflow->transition($booking,'reschedule_requested','customer',$request->user()->id,['requested_start_at'=>$start->toIso8601String()])]);
    }

    public function review(Request $request, UrbanGoodzServiceRequest $booking)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id && $booking->status==='completed',404);
        $data=$request->validate(['rating'=>'required|integer|min:1|max:5','comment'=>'nullable|string|max:2000']);
        $review=UrbanGoodzServiceReview::updateOrCreate(['service_request_id'=>$booking->id],['provider_id'=>$booking->provider_id,'user_id'=>$request->user()->id]+$data);
        $provider=UrbanGoodzServiceProvider::findOrFail($booking->provider_id); $provider->update(['rating'=>round($provider->reviews()->avg('rating'),2),'rating_count'=>$provider->reviews()->count()]);
        return response()->json(['message'=>'Review submitted.','data'=>$review],201);
    }

    /**
     * Customer-initiated refund request. This never moves money on its own:
     * it opens a dispute for admin review so that a refund is always an
     * explicit, audited decision.
     */
    public function requestRefund(Request $request, UrbanGoodzServiceRequest $booking)
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);
        abort_unless(
            in_array($booking->status, ['confirmed', 'en_route', 'started', 'completed', 'canceled'], true),
            409,
            'A refund can only be requested once the booking has been confirmed.'
        );

        $paid = (int) $booking->amount_paid_minor - (int) $booking->refunded_amount_minor;
        abort_if($paid <= 0, 409, 'This booking has no refundable balance.');

        $data = $request->validate([
            'reason' => 'required|in:not_delivered,quality,late,damage,billing,other',
            'details' => 'required|string|max:2000',
            'requested_amount_minor' => 'nullable|integer|min:1|max:'.$paid,
        ]);

        abort_if(
            UrbanGoodzServiceDispute::where('service_request_id', $booking->id)->where('status', 'open')->exists(),
            409,
            'A dispute is already open for this booking.'
        );

        $dispute = UrbanGoodzServiceDispute::create([
            'service_request_id' => $booking->id,
            'provider_id' => $booking->provider_id,
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
            'details' => $data['details'],
            'requested_amount_minor' => $data['requested_amount_minor'] ?? $paid,
            'status' => 'open',
        ]);

        $booking->events()->create([
            'actor_type' => 'customer',
            'actor_id' => $request->user()->id,
            'from_status' => $booking->status,
            'to_status' => $booking->status,
            'metadata' => ['event' => 'refund_requested', 'dispute_id' => $dispute->id, 'reason' => $data['reason']],
        ]);

        app(\App\Services\UrbanGoodzNotificationService::class)->notifyVendor(
            (int) $booking->assigned_vendor_id,
            'Refund requested',
            'A customer opened a refund request for a service booking.',
            ['type' => 'service_booking_dispute', 'booking_id' => $booking->id, 'dispute_id' => $dispute->id]
        );

        return response()->json(['message' => 'Refund request submitted for review.', 'data' => $dispute], 201);
    }

    public function disputes(Request $request)
    {
        return response()->json(
            UrbanGoodzServiceDispute::where('user_id', $request->user()->id)
                ->with('booking:id,status,service_type,scheduled_at')
                ->latest()
                ->paginate(30)
        );
    }
}
