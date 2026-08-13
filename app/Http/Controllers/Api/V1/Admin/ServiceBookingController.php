<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzServiceBookingEvent;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceProviderEarning;
use App\Models\UrbanGoodzServiceRequest;
use App\Services\ServiceBookings\ServiceBookingRefundService;
use Illuminate\Http\Request;
class ServiceBookingController extends Controller
{
    public function dashboard()
    {
        return response()->json(['data'=>[
            'providers'=>UrbanGoodzServiceProvider::selectRaw('approval_status,COUNT(*) total')->groupBy('approval_status')->pluck('total','approval_status'),
            'bookings'=>UrbanGoodzServiceRequest::selectRaw('status,COUNT(*) total')->groupBy('status')->pluck('total','status'),
            'gross_amount_minor'=>(int)UrbanGoodzServiceProviderEarning::sum('gross_amount_minor'),
            'provider_amount_minor'=>(int)UrbanGoodzServiceProviderEarning::sum('provider_amount_minor'),
            'categories'=>collect(config('service_bookings.categories'))->map(fn($slug)=>['slug'=>$slug,'name'=>\Illuminate\Support\Str::headline($slug)])->values(),
        ]]);
    }
    public function providers(){return response()->json(UrbanGoodzServiceProvider::withCount(['services','serviceRequests'])->with(['services','availability','areas'])->latest()->paginate(30));}
    public function providerStatus(Request $request,UrbanGoodzServiceProvider $provider){$data=$request->validate(['status'=>'required|in:approved,pending,suspended,rejected','notes'=>'nullable|string|max:2000']);$approved=$data['status']==='approved';if($approved){abort_unless($provider->submitted_at,422,'Provider onboarding has not been submitted.');abort_unless($provider->services()->where('is_active',true)->exists(),422,'Provider has no active services.');abort_unless($provider->availability()->where('is_active',true)->exists(),422,'Provider has no active availability.');if(in_array('mobile',$provider->location_modes??[],true)){abort_unless($provider->areas()->where('is_active',true)->exists(),422,'Mobile provider has no active service area.');}}$provider->update(['approval_status'=>$data['status'],'is_verified'=>$approved,'is_active'=>!in_array($data['status'],['suspended','rejected'],true),'approved_at'=>$approved?now():null]);app(\App\Services\UrbanGoodzNotificationService::class)->notifyVendor((int)$provider->vendor_id,'Service provider status updated','Your service-provider status is '.$data['status'].'.',['type'=>'service_provider_status','status'=>$data['status'],'notes'=>$data['notes']??null]);return response()->json(['message'=>'Provider status updated.','data'=>$provider]);}
    public function bookings(Request $request){return response()->json(UrbanGoodzServiceRequest::with(['assignedProvider','appointments'])->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')))->latest()->paginate(30));}
    public function booking(UrbanGoodzServiceRequest $booking){return response()->json($booking->load(['assignedProvider','service','serviceArea','appointments','events','quotes','activeQuote']));}
    public function earnings(){return response()->json(UrbanGoodzServiceProviderEarning::latest()->paginate(50));}
    public function audit(Request $request){return response()->json(UrbanGoodzServiceBookingEvent::when($request->filled('booking_id'),fn($q)=>$q->where('service_request_id',$request->integer('booking_id')))->latest()->paginate(50));}
    public function refund(Request $request,UrbanGoodzServiceRequest $booking,ServiceBookingRefundService $refunds)
    {
        $available=max(0,(int)$booking->amount_paid_minor-(int)$booking->refunded_amount_minor);
        $data=$request->validate(['amount_minor'=>'nullable|integer|min:1','reason'=>'required|string|max:1000','idempotency_key'=>'required|string|max:100']);
        $amount=(int)($data['amount_minor']??$available);
        $refunded=$refunds->refund($booking,$amount,$data['idempotency_key']);
        $booking->events()->create(['actor_type'=>'admin','actor_id'=>$request->user()?->id,'from_status'=>$booking->status,'to_status'=>$booking->status,'metadata'=>['event'=>'refund','amount_minor'=>$refunded,'reason'=>$data['reason']]]);
        app(\App\Services\UrbanGoodzNotificationService::class)->notifyCustomer((int)$booking->user_id,'Service booking refund issued','A refund was issued for your service booking.',['type'=>'service_booking_refund','booking_id'=>$booking->id,'amount_minor'=>$refunded]);
        return response()->json(['message'=>'Refund issued.','data'=>$booking->fresh()]);
    }
}
