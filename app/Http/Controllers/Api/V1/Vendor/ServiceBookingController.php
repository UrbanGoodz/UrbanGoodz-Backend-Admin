<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzProviderAvailability;
use App\Models\UrbanGoodzProviderService;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceProviderEarning;
use App\Models\UrbanGoodzServiceRequest;
use App\Services\ServiceBookings\ServiceBookingWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceBookingController extends Controller
{
    private function provider(Request $request, bool $approved = false): UrbanGoodzServiceProvider
    {
        $provider=UrbanGoodzServiceProvider::where('vendor_id',$request['vendor']->id)->firstOrFail();
        if($approved){ abort_unless($provider->approval_status==='approved' && $provider->is_verified && $provider->is_active,403,'Approved active service-provider status is required.'); }
        return $provider;
    }

    public function profile(Request $request)
    {
        $vendor=$request['vendor'];
        $profile=UrbanGoodzServiceProvider::firstOrCreate(['vendor_id'=>$vendor->id],['business_name'=>$vendor->stores[0]->name,'slug'=>'provider-'.$vendor->id.'-'.Str::lower(Str::random(6)),'contact_name'=>trim($vendor->f_name.' '.$vendor->l_name),'email'=>$vendor->email,'phone'=>$vendor->phone,'approval_status'=>'pending','is_verified'=>false,'is_active'=>true]);
        return response()->json($profile->load(['services','availability']));
    }

    public function updateProfile(Request $request)
    {
        $data=$request->validate(['business_name'=>'required|string|max:255','description'=>'nullable|string|max:3000','service_areas'=>'nullable|array|max:50','service_areas.*'=>'string|max:120','location_modes'=>'required|array|min:1','location_modes.*'=>'in:mobile,in_person,remote']);
        $provider=UrbanGoodzServiceProvider::firstOrCreate(['vendor_id'=>$request['vendor']->id],['business_name'=>$data['business_name'],'slug'=>'provider-'.$request['vendor']->id.'-'.Str::lower(Str::random(6)),'approval_status'=>'pending']);
        $provider->update($data); return response()->json(['message'=>'Provider profile saved.','data'=>$provider]);
    }

    public function services(Request $request){ return response()->json($this->provider($request)->services()->latest()->get()); }

    public function storeService(Request $request)
    {
        $provider=$this->provider($request,true); $data=$this->serviceData($request); $service=$provider->services()->create($data);
        return response()->json(['message'=>'Service created.','data'=>$service],201);
    }

    public function updateService(Request $request, UrbanGoodzProviderService $service)
    {
        $provider=$this->provider($request,true); abort_unless((int)$service->provider_id===(int)$provider->id,404); $service->update($this->serviceData($request));
        return response()->json(['message'=>'Service updated.','data'=>$service]);
    }

    public function deleteService(Request $request, UrbanGoodzProviderService $service)
    {
        $provider=$this->provider($request,true); abort_unless((int)$service->provider_id===(int)$provider->id,404);
        abort_if(UrbanGoodzServiceRequest::where('provider_service_id',$service->id)->whereIn('status',['requested','quoted','accepted','confirmed','en_route','started'])->exists(),409,'Service has active bookings.');
        $service->delete(); return response()->json(['message'=>'Service deleted.']);
    }

    public function availability(Request $request)
    {
        $provider=$this->provider($request,true); $data=$request->validate(['slots'=>'required|array|max:40','slots.*.day_of_week'=>'required|integer|min:0|max:6','slots.*.starts_at'=>'required|date_format:H:i','slots.*.ends_at'=>'required|date_format:H:i|after:slots.*.starts_at','slots.*.timezone'=>'nullable|timezone']);
        DB::transaction(function()use($provider,$data){ $provider->availability()->delete(); foreach($data['slots'] as $slot){$provider->availability()->create($slot+['timezone'=>$slot['timezone']??'America/Chicago','is_active'=>true]);}});
        return response()->json(['message'=>'Availability replaced.','data'=>$provider->availability]);
    }

    public function bookings(Request $request)
    {
        $provider=$this->provider($request,true); return response()->json($provider->serviceRequests()->with(['appointments','events'])->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')))->latest()->paginate(30));
    }

    public function booking(Request $request, UrbanGoodzServiceRequest $booking)
    {
        $provider=$this->provider($request,true); abort_unless((int)$booking->provider_id===(int)$provider->id,404); return response()->json($booking->load(['appointments','events']));
    }

    public function quote(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow)
    {
        $provider=$this->provider($request,true); abort_unless((int)$booking->provider_id===(int)$provider->id,404);
        abort_unless($booking->status==='requested',409,'Only a requested booking may be quoted.');
        $data=$request->validate(['amount_minor'=>'required|integer|min:1|max:100000000','deposit_minor'=>'nullable|integer|min:0|lte:amount_minor','notes'=>'nullable|string|max:2000','scheduled_at'=>'required|date|after:now']);
        $booking->update(['quoted_amount_minor'=>$data['amount_minor'],'deposit_amount_minor'=>$data['deposit_minor']??0,'provider_notes'=>$data['notes']??null,'scheduled_at'=>$data['scheduled_at'],'payment_status'=>'pending']);
        return response()->json(['data'=>$workflow->transition($booking,'quoted','provider',$request['vendor']->id)]);
    }

    public function transition(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow)
    {
        $provider=$this->provider($request,true); abort_unless((int)$booking->provider_id===(int)$provider->id,404);
        $data=$request->validate(['status'=>'required|in:accepted,declined,en_route,started,completed','notes'=>'nullable|string|max:2000','scheduled_at'=>'nullable|date|after:now']);
        if($data['status']==='accepted'){ abort_unless(in_array($booking->status,['requested','reschedule_requested'],true),409,'The customer must accept a submitted quote.'); abort_unless($booking->quoted_amount_minor!==null,409,'A price or quote is required.'); $booking->update(['scheduled_at'=>$data['scheduled_at']??$booking->scheduled_at,'provider_notes'=>$data['notes']??$booking->provider_notes]); }
        if($data['status']==='completed'){ abort_unless($booking->payment_status==='paid'||$booking->payment_status==='not_required',409,'Required payment has not been accepted.'); }
        return response()->json(['data'=>$workflow->transition($booking,$data['status'],'provider',$request['vendor']->id,['notes'=>$data['notes']??null])]);
    }

    public function earnings(Request $request)
    {
        $provider=$this->provider($request,true); return response()->json(['summary'=>UrbanGoodzServiceProviderEarning::where('provider_id',$provider->id)->selectRaw('status,SUM(provider_amount_minor) amount_minor')->groupBy('status')->get(),'records'=>UrbanGoodzServiceProviderEarning::where('provider_id',$provider->id)->latest()->paginate(30)]);
    }

    private function serviceData(Request $request): array
    {
        return $request->validate(['category'=>'required|in:'.implode(',',config('service_bookings.categories')),'name'=>'required|string|max:255','description'=>'nullable|string|max:3000','duration_minutes'=>'required|integer|min:15|max:1440','price_minor'=>'nullable|required_unless:requires_quote,1|integer|min:0|max:100000000','deposit_minor'=>'nullable|integer|min:0|max:100000000','currency'=>'nullable|string|size:3','requires_quote'=>'required|boolean','is_active'=>'required|boolean']);
    }
}
