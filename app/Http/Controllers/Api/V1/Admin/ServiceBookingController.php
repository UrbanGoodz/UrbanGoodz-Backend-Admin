<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzServiceBookingEvent;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceProviderEarning;
use App\Models\UrbanGoodzServiceRequest;
use App\Models\UserNotification;
use Illuminate\Http\Request;
class ServiceBookingController extends Controller
{
    public function providers(){return response()->json(UrbanGoodzServiceProvider::withCount(['services','serviceRequests'])->latest()->paginate(30));}
    public function providerStatus(Request $request,UrbanGoodzServiceProvider $provider){$data=$request->validate(['status'=>'required|in:approved,pending,suspended,rejected','notes'=>'nullable|string|max:2000']);$approved=$data['status']==='approved';$provider->update(['approval_status'=>$data['status'],'is_verified'=>$approved,'is_active'=>!in_array($data['status'],['suspended','rejected'],true)]);UserNotification::create(['vendor_id'=>$provider->vendor_id,'title'=>'Service provider status updated','description'=>'Your service-provider status is '.$data['status'].'.','data'=>json_encode(['type'=>'service_provider_status','status'=>$data['status']])]);return response()->json(['message'=>'Provider status updated.','data'=>$provider]);}
    public function bookings(Request $request){return response()->json(UrbanGoodzServiceRequest::with(['assignedProvider','appointments'])->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')))->latest()->paginate(30));}
    public function booking(UrbanGoodzServiceRequest $booking){return response()->json($booking->load(['assignedProvider','appointments','events']));}
    public function earnings(){return response()->json(UrbanGoodzServiceProviderEarning::latest()->paginate(50));}
    public function audit(Request $request){return response()->json(UrbanGoodzServiceBookingEvent::when($request->filled('booking_id'),fn($q)=>$q->where('service_request_id',$request->integer('booking_id')))->latest()->paginate(50));}
}
