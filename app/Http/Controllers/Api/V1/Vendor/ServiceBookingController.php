<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzProviderPortfolioItem;
use App\Models\UrbanGoodzProviderService;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceProviderEarning;
use App\Models\UrbanGoodzServiceQuote;
use App\Models\UrbanGoodzServiceRequest;
use App\Services\ServiceBookings\ServiceBookingAvailabilityService;
use App\Services\ServiceBookings\ServiceBookingRefundService;
use App\Services\ServiceBookings\ServiceBookingWorkflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

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
        $businessName=$vendor->stores?->first()?->name ?: trim($vendor->f_name.' '.$vendor->l_name) ?: 'Service Provider';
        $profile=UrbanGoodzServiceProvider::firstOrCreate(['vendor_id'=>$vendor->id],['business_name'=>$businessName,'slug'=>'provider-'.$vendor->id.'-'.Str::lower(Str::random(6)),'contact_name'=>trim($vendor->f_name.' '.$vendor->l_name),'email'=>$vendor->email,'phone'=>$vendor->phone,'approval_status'=>'pending','is_verified'=>false,'is_active'=>true]);
        return response()->json($profile->load(['services','availability','areas']));
    }

    public function updateProfile(Request $request)
    {
        $data=$request->validate(['business_name'=>'required|string|max:255','contact_name'=>'nullable|string|max:255','email'=>'nullable|email|max:255','phone'=>'nullable|string|max:50','description'=>'nullable|string|max:3000','service_areas'=>'nullable|array|max:50','service_areas.*'=>'string|max:120','location_modes'=>'required|array|min:1','location_modes.*'=>'in:mobile,in_person,remote','onboarding_data'=>'nullable|array']);
        $provider=UrbanGoodzServiceProvider::firstOrCreate(['vendor_id'=>$request['vendor']->id],['business_name'=>$data['business_name'],'slug'=>'provider-'.$request['vendor']->id.'-'.Str::lower(Str::random(6)),'approval_status'=>'pending']);
        $provider->update($data); return response()->json(['message'=>'Provider profile saved.','data'=>$provider]);
    }

    public function submitOnboarding(Request $request)
    {
        $provider = $this->provider($request);
        abort_unless($provider->services()->where('is_active', true)->exists(), 422, 'At least one active service is required.');
        abort_unless($provider->availability()->where('is_active', true)->exists(), 422, 'Availability is required.');
        if (in_array('mobile', $provider->location_modes ?? [], true)) {
            abort_unless($provider->areas()->where('is_active', true)->exists(), 422, 'A service area is required for mobile services.');
        }
        $provider->update(['approval_status'=>'pending','is_verified'=>false,'submitted_at'=>now()]);
        return response()->json(['message'=>'Provider onboarding submitted for review.','data'=>$provider->fresh(['services','availability','areas'])]);
    }

    public function serviceAreas(Request $request)
    {
        return response()->json($this->provider($request)->areas()->orderBy('name')->get());
    }

    public function replaceServiceAreas(Request $request)
    {
        $provider=$this->provider($request);
        $data=$request->validate([
            'areas'=>'required|array|max:50',
            'areas.*.id'=>'nullable|integer',
            'areas.*.name'=>'required|string|max:255',
            'areas.*.area_type'=>'required|in:city,postal_code,radius,statewide,remote',
            'areas.*.country_code'=>'nullable|string|size:2',
            'areas.*.region_code'=>'nullable|string|max:16',
            'areas.*.city'=>'nullable|string|max:120',
            'areas.*.postal_code'=>'nullable|string|max:24',
            'areas.*.latitude'=>'nullable|numeric|between:-90,90',
            'areas.*.longitude'=>'nullable|numeric|between:-180,180',
            'areas.*.radius_miles'=>'nullable|integer|min:1|max:500',
            'areas.*.is_active'=>'nullable|boolean',
        ]);
        foreach($data['areas'] as $area){
            abort_if($area['area_type']==='postal_code' && empty($area['postal_code']),422,'Postal code is required for postal-code service areas.');
            abort_if($area['area_type']==='city' && empty($area['city']),422,'City is required for city service areas.');
            abort_if($area['area_type']==='radius' && (!isset($area['latitude'],$area['longitude'],$area['radius_miles'])),422,'Coordinates and radius are required for radius service areas.');
        }
        DB::transaction(function()use($provider,$data){
            $provider->areas()->update(['is_active'=>false]);
            foreach($data['areas'] as $area){
                $id=$area['id']??null;
                unset($area['id']);
                $area['country_code']=strtoupper($area['country_code']??'US');
                $area['is_active']=$area['is_active']??true;
                $values=$area;
                if($id){
                    $owned=$provider->areas()->whereKey($id)->firstOrFail();
                    $owned->update($values);
                }else{
                    $provider->areas()->create($values);
                }
            }
            $provider->update(['service_areas'=>collect($data['areas'])->pluck('name')->values()->all()]);
        });
        return response()->json(['message'=>'Service areas replaced.','data'=>$provider->areas()->orderBy('name')->get()]);
    }

    public function services(Request $request){ return response()->json($this->provider($request)->services()->latest()->get()); }

    public function portfolio(Request $request)
    {
        return response()->json(
            $this->provider($request)->portfolioItems()->orderBy('sort_order')->orderByDesc('id')->get()
        );
    }

    public function storePortfolioItem(Request $request)
    {
        $provider = $this->provider($request);
        abort_if(
            $provider->portfolioItems()->where('is_active', true)->count() >= 60,
            422,
            'A provider portfolio is limited to 60 active items.'
        );
        $data = $this->portfolioData($request, $provider);
        $item = $provider->portfolioItems()->create($data);

        return response()->json(['message' => 'Portfolio item added.', 'data' => $item], 201);
    }

    public function updatePortfolioItem(Request $request, UrbanGoodzProviderPortfolioItem $item)
    {
        $provider = $this->provider($request);
        abort_unless((int) $item->provider_id === (int) $provider->id, 404);
        $item->update($this->portfolioData($request, $provider, $item));

        return response()->json(['message' => 'Portfolio item updated.', 'data' => $item->fresh()]);
    }

    public function deletePortfolioItem(Request $request, UrbanGoodzProviderPortfolioItem $item)
    {
        $provider = $this->provider($request);
        abort_unless((int) $item->provider_id === (int) $provider->id, 404);
        // Retired rather than deleted so that bookings which referenced this
        // work keep a readable history, matching the service/area convention.
        $item->update(['is_active' => false]);

        return response()->json(['message' => 'Portfolio item retired.']);
    }

    private function portfolioData(Request $request, UrbanGoodzServiceProvider $provider, ?UrbanGoodzProviderPortfolioItem $existing = null): array
    {
        $data = $request->validate([
            'title' => ($existing ? 'sometimes|' : '').'required|string|max:255',
            'caption' => 'nullable|string|max:1000',
            'provider_service_id' => 'nullable|integer',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4|max:20480',
            'media_path' => 'nullable|string|max:2048',
            'media_type' => 'nullable|in:image,video',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($data['provider_service_id'])) {
            abort_unless(
                $provider->services()->whereKey($data['provider_service_id'])->exists(),
                422,
                'The portfolio item must reference one of your own services.'
            );
        }

        if ($request->hasFile('media')) {
            $stored = $request->file('media')->store('urban-goodz/service-portfolio', 'public');
            $data['media_path'] = $stored;
            $data['media_type'] = $request->file('media')->getClientOriginalExtension() === 'mp4' ? 'video' : 'image';
        }
        unset($data['media']);

        abort_if(
            $existing === null && empty($data['media_path']),
            422,
            'A portfolio item requires an uploaded file or a media path.'
        );

        return $data;
    }

    public function storeService(Request $request)
    {
        $provider=$this->provider($request); $data=$this->serviceData($request); $service=$provider->services()->create($data);
        return response()->json(['message'=>'Service created.','data'=>$service],201);
    }

    public function updateService(Request $request, UrbanGoodzProviderService $service)
    {
        $provider=$this->provider($request); abort_unless((int)$service->provider_id===(int)$provider->id,404); $service->update($this->serviceData($request));
        return response()->json(['message'=>'Service updated.','data'=>$service]);
    }

    public function deleteService(Request $request, UrbanGoodzProviderService $service)
    {
        $provider=$this->provider($request); abort_unless((int)$service->provider_id===(int)$provider->id,404);
        abort_if(UrbanGoodzServiceRequest::where('provider_service_id',$service->id)->whereIn('status',['requested','quoted','accepted','confirmed','en_route','started'])->exists(),409,'Service has active bookings.');
        $service->update(['is_active'=>false]); return response()->json(['message'=>'Service retired.']);
    }

    public function availability(Request $request)
    {
        $provider=$this->provider($request); $data=$request->validate(['slots'=>'required|array|max:40','slots.*.day_of_week'=>'required|integer|min:0|max:6','slots.*.starts_at'=>'required|date_format:H:i','slots.*.ends_at'=>'required|date_format:H:i','slots.*.timezone'=>'nullable|timezone']);
        foreach($data['slots'] as $slot){abort_unless($slot['ends_at']>$slot['starts_at'],422,'Availability end time must be after start time.');}
        DB::transaction(function()use($provider,$data){ $provider->availability()->delete(); foreach($data['slots'] as $slot){$provider->availability()->create($slot+['timezone'=>$slot['timezone']??'America/Chicago','is_active'=>true]);}});
        return response()->json(['message'=>'Availability replaced.','data'=>$provider->availability]);
    }

    public function bookings(Request $request)
    {
        $provider=$this->provider($request,true); return response()->json($provider->serviceRequests()->with(['service','serviceArea','appointments','events','quotes','activeQuote'])->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')))->latest()->paginate(30));
    }

    public function booking(Request $request, UrbanGoodzServiceRequest $booking)
    {
        $provider=$this->provider($request,true); abort_unless((int)$booking->provider_id===(int)$provider->id,404); return response()->json($booking->load(['service','serviceArea','appointments','events','quotes','activeQuote']));
    }

    public function quote(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow, ServiceBookingAvailabilityService $availability)
    {
        $provider=$this->provider($request,true); abort_unless((int)$booking->provider_id===(int)$provider->id,404);
        abort_unless($booking->status==='requested',409,'Only a requested booking may be quoted.');
        $data=$request->validate(['amount_minor'=>'required|integer|min:1|max:100000000','deposit_minor'=>'nullable|integer|min:0|lte:amount_minor','notes'=>'nullable|string|max:2000','scheduled_at'=>'required|date|after:now','expires_at'=>'nullable|date|after:now']);
        $service=UrbanGoodzProviderService::findOrFail($booking->provider_service_id);
        $start=Carbon::parse($data['scheduled_at']);
        $end=$availability->assertAvailable($provider,$service,$start,$booking->id);
        $quoted=DB::transaction(function()use($booking,$provider,$data,$start,$end,$workflow,$request){
            UrbanGoodzServiceQuote::where('service_request_id',$booking->id)->where('status','offered')->update(['status'=>'superseded','declined_at'=>now()]);
            $quote=UrbanGoodzServiceQuote::create(['service_request_id'=>$booking->id,'provider_id'=>$provider->id,'amount_minor'=>$data['amount_minor'],'deposit_minor'=>$data['deposit_minor']??0,'currency'=>$booking->currency,'notes'=>$data['notes']??null,'scheduled_at'=>$start,'expires_at'=>$data['expires_at']??now()->addDays(2),'status'=>'offered']);
            $booking->update(['active_quote_id'=>$quote->id,'quoted_amount_minor'=>$data['amount_minor'],'deposit_amount_minor'=>$data['deposit_minor']??0,'provider_notes'=>$data['notes']??null,'scheduled_at'=>$start,'scheduled_end_at'=>$end,'payment_status'=>'pending']);
            return $workflow->transition($booking,'quoted','provider',$request['vendor']->id);
        });
        return response()->json(['data'=>$quoted]);
    }

    public function transition(Request $request, UrbanGoodzServiceRequest $booking, ServiceBookingWorkflow $workflow, ServiceBookingAvailabilityService $availability, ServiceBookingRefundService $refunds)
    {
        $provider=$this->provider($request,true); abort_unless((int)$booking->provider_id===(int)$provider->id,404);
        $data=$request->validate(['status'=>'required|in:accepted,declined,en_route,started,completed,canceled','notes'=>'nullable|string|max:2000','scheduled_at'=>'nullable|date|after:now']);
        if($data['status']==='accepted'){
            abort_unless(in_array($booking->status,['requested','reschedule_requested'],true),409,'Only a requested or rescheduled booking may be accepted.');
            abort_unless($booking->quoted_amount_minor!==null,409,'A price or quote is required.');
            $service=UrbanGoodzProviderService::findOrFail($booking->provider_service_id);
            $start=Carbon::parse($data['scheduled_at']??$booking->scheduled_at);
            $end=$availability->assertAvailable($provider,$service,$start,$booking->id);
            $booking->update(['scheduled_at'=>$start,'scheduled_end_at'=>$end,'provider_notes'=>$data['notes']??$booking->provider_notes]);
        }
        if($data['status']==='completed'){ abort_unless($booking->payment_status==='paid'||$booking->payment_status==='not_required',409,'Required payment has not been accepted.'); }
        if($data['status']==='canceled'){
            abort_if(in_array($booking->status,['started','completed','canceled','declined'],true),409,'This booking can no longer be canceled.');
            $refundable=max(0,(int)$booking->amount_paid_minor-(int)$booking->refunded_amount_minor);
            if($refundable>0){
                try{$refunds->refund($booking,$refundable,'provider-cancel-'.$booking->id.'-'.$booking->amount_paid_minor);}
                catch(Throwable $e){report(new \RuntimeException('Service booking refund failed: '.$e->getMessage()));return response()->json(['message'=>'Cancellation could not be finalized because the refund failed.','code'=>'refund_failed'],502);}
            }
        }
        return response()->json(['data'=>$workflow->transition($booking,$data['status'],'provider',$request['vendor']->id,['notes'=>$data['notes']??null,'reason'=>$data['notes']??'Canceled by provider.'])]);
    }

    public function earnings(Request $request)
    {
        $provider=$this->provider($request,true); return response()->json(['summary'=>UrbanGoodzServiceProviderEarning::where('provider_id',$provider->id)->selectRaw('status,SUM(provider_amount_minor) amount_minor')->groupBy('status')->get(),'records'=>UrbanGoodzServiceProviderEarning::where('provider_id',$provider->id)->latest()->paginate(30)]);
    }

    private function serviceData(Request $request): array
    {
        $data=$request->validate(['category'=>'required|in:'.implode(',',config('service_bookings.categories')),'name'=>'required|string|max:255','description'=>'nullable|string|max:3000','duration_minutes'=>'required|integer|min:15|max:1440','price_minor'=>'nullable|required_unless:requires_quote,1|integer|min:0|max:100000000','deposit_minor'=>'nullable|integer|min:0|max:100000000','currency'=>['nullable','string','size:3','regex:/^[A-Za-z]{3}$/'],'requires_quote'=>'required|boolean','is_active'=>'required|boolean']);
        if(!$data['requires_quote']){
            abort_if((int)($data['deposit_minor']??0)>(int)$data['price_minor'],422,'Deposit cannot exceed the service price.');
        }
        $data['currency']=strtoupper($data['currency']??'USD');
        return $data;
    }
}
