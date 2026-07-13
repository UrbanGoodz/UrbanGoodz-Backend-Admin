<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ServiceBookingPaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzProviderService;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceRequest;
use App\Models\UrbanGoodzServiceReview;
use App\Models\UserNotification;
use App\Services\ServiceBookings\ServiceBookingWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;

class ServiceBookingCustomerController extends Controller
{
    public function providers(Request $request)
    {
        return response()->json(UrbanGoodzServiceProvider::query()->where('approval_status','approved')->where('is_verified',true)->where('is_active',true)
            ->when($request->filled('category'), fn($q)=>$q->whereHas('services', fn($s)=>$s->where('category',$request->string('category'))->where('is_active',true)))
            ->with(['services'=>fn($q)=>$q->where('is_active',true),'availability'=>fn($q)=>$q->where('is_active',true)])
            ->paginate(min((int)$request->input('limit',20),100)));
    }

    public function provider(UrbanGoodzServiceProvider $provider)
    {
        abort_unless($provider->approval_status==='approved' && $provider->is_verified && $provider->is_active,404);
        return response()->json($provider->load(['services'=>fn($q)=>$q->where('is_active',true),'availability'=>fn($q)=>$q->where('is_active',true)]));
    }

    public function index(Request $request)
    {
        return response()->json(UrbanGoodzServiceRequest::where('user_id',$request->user()->id)->with(['assignedProvider','appointments'])->latest()->paginate(30));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider_id'=>'required|integer','service_id'=>'required|integer','requested_start_at'=>'required|date|after:now',
            'location_mode'=>'required|in:mobile,in_person,remote','location_details'=>'nullable|string|max:1000','notes'=>'nullable|string|max:2000',
        ]);
        $provider = UrbanGoodzServiceProvider::whereKey($data['provider_id'])->where('approval_status','approved')->where('is_verified',true)->where('is_active',true)->firstOrFail();
        $service = UrbanGoodzProviderService::whereKey($data['service_id'])->where('provider_id',$provider->id)->where('is_active',true)->firstOrFail();
        abort_unless(in_array($service->category,config('service_bookings.categories'),true),422,'Unsupported service category.');
        abort_unless(in_array($data['location_mode'],$provider->location_modes ?? ['in_person'],true),422,'Location mode is not offered by this provider.');
        $start = Carbon::parse($data['requested_start_at']);
        abort_unless($provider->availability()->where('day_of_week',$start->dayOfWeek)->where('is_active',true)->where('starts_at','<=',$start->format('H:i:s'))->where('ends_at','>',$start->format('H:i:s'))->exists(),422,'Requested time is outside provider availability.');
        abort_if(UrbanGoodzServiceRequest::where('provider_id',$provider->id)->whereIn('status',['accepted','confirmed','en_route','started'])->where('scheduled_at',$start)->exists(),409,'Requested time is no longer available.');

        $user=$request->user();
        $booking=UrbanGoodzServiceRequest::create([
            'user_id'=>$user->id,'customer_name'=>trim($user->f_name.' '.$user->l_name),'customer_email'=>$user->email,'customer_phone'=>$user->phone,
            'service_type'=>$service->category,'description'=>$data['notes']??null,'status'=>'requested','assigned_vendor_id'=>$provider->vendor_id,
            'provider_id'=>$provider->id,'provider_service_id'=>$service->id,'location_mode'=>$data['location_mode'],'location_details'=>$data['location_details']??null,
            'requested_start_at'=>$start,'scheduled_at'=>$start,'quoted_amount_minor'=>$service->requires_quote?null:$service->price_minor,
            'deposit_amount_minor'=>$service->deposit_minor,'currency'=>$service->currency,'payment_status'=>(($service->deposit_minor?:$service->price_minor)>0?'pending':'not_required'),
        ]);
        $booking->events()->create(['actor_type'=>'customer','actor_id'=>$user->id,'from_status'=>null,'to_status'=>'requested']);
        UserNotification::create(['vendor_id'=>$provider->vendor_id,'title'=>'New service booking request','description'=>'A customer requested '.$service->name.'.','data'=>json_encode(['type'=>'service_booking','booking_id'=>$booking->id,'status'=>'requested'])]);
        return response()->json(['message'=>'Service booking requested.','data'=>$booking->load('assignedProvider')],201);
    }

    public function show(Request $request, UrbanGoodzServiceRequest $booking)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404);
        return response()->json($booking->load(['assignedProvider','appointments','events']));
    }

    public function confirm(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404);
        abort_unless($booking->status==='accepted',409,'Provider acceptance is required.');
        abort_unless($booking->payment_status==='paid' || $booking->payment_status==='not_required',409,'Required payment has not been accepted.');
        return response()->json(['data'=>$workflow->transition($booking,'confirmed','customer',$request->user()->id)]);
    }

    public function acceptQuote(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404);
        abort_unless($booking->status==='quoted' && $booking->quoted_amount_minor!==null,409,'An active provider quote is required.');
        return response()->json(['data'=>$workflow->transition($booking,'accepted','customer',$request->user()->id)]);
    }

    public function pay(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingPaymentGateway $gateway)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404);
        abort_unless($booking->status==='accepted' && $booking->payment_status==='pending',409,'Payment is not available.');
        $data=$request->validate(['payment_token'=>'required|string|max:500','idempotency_key'=>'required|string|max:100']);
        $existing=UrbanGoodzPaymentTransaction::where('idempotency_key',$data['idempotency_key'])->where('payable_type',UrbanGoodzServiceRequest::class)->where('payable_id',$booking->id)->first();
        if($existing && $existing->internal_status==='succeeded'){ return response()->json(['message'=>'Payment already accepted.','payment_status'=>'paid']); }
        try { $result=$gateway->charge($booking,$data['payment_token'],$data['idempotency_key']); }
        catch(Throwable $e){ report(new \RuntimeException('Service booking payment failed: '.$e->getMessage())); return response()->json(['message'=>'Payment was not accepted.','code'=>'payment_failed'],502); }
        $isSandbox = config('service_bookings.payment.sandbox', true);
        DB::transaction(function()use($booking,$data,$result,$isSandbox){
            UrbanGoodzPaymentTransaction::updateOrCreate(['idempotency_key'=>$data['idempotency_key']],['payable_type'=>UrbanGoodzServiceRequest::class,'payable_id'=>$booking->id,'provider'=>'stripe','environment'=>$isSandbox?'sandbox':'live','transaction_type'=>'deposit','internal_status'=>'succeeded','provider_status'=>$result['status'],'amount_minor'=>$booking->deposit_amount_minor?:$booking->quoted_amount_minor,'currency'=>$booking->currency,'provider_payment_id'=>$result['id'],'processed_at'=>now()]);
            $booking->update(['payment_status'=>'paid']);
        });
        return response()->json(['message'=>'Payment accepted.','payment_status'=>'paid']);
    }

    public function cancel(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404); $data=$request->validate(['reason'=>'required|string|max:1000']);
        return response()->json(['data'=>$workflow->transition($booking,'canceled','customer',$request->user()->id,['reason'=>$data['reason']])]);
    }

    public function reschedule(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id,404); $data=$request->validate(['requested_start_at'=>'required|date|after:now']);
        return response()->json(['data'=>$workflow->transition($booking,'reschedule_requested','customer',$request->user()->id,['requested_start_at'=>$data['requested_start_at']])]);
    }

    public function review(Request $request, UrbanGoodzServiceRequest $booking)
    {
        abort_unless((int)$booking->user_id===(int)$request->user()->id && $booking->status==='completed',404);
        $data=$request->validate(['rating'=>'required|integer|min:1|max:5','comment'=>'nullable|string|max:2000']);
        $review=UrbanGoodzServiceReview::create(['service_request_id'=>$booking->id,'provider_id'=>$booking->provider_id,'user_id'=>$request->user()->id]+$data);
        $provider=UrbanGoodzServiceProvider::findOrFail($booking->provider_id); $provider->update(['rating'=>round($provider->reviews()->avg('rating'),2),'rating_count'=>$provider->reviews()->count()]);
        return response()->json(['message'=>'Review submitted.','data'=>$review],201);
    }
}
