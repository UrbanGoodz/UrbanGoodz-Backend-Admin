<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzProviderService;
use App\Models\UrbanGoodzServiceAdminAudit;
use App\Models\UrbanGoodzServiceBookingEvent;
use App\Models\UrbanGoodzServiceDispute;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceProviderEarning;
use App\Models\UrbanGoodzServiceRequest;
use App\Services\ServiceBookings\ServiceBookingRefundService;
use App\Services\ServiceBookings\ServiceEarningSettlementService;
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
    public function providerCommission(Request $request, UrbanGoodzServiceProvider $provider)
    {
        $data = $request->validate([
            // Null clears the override and returns the provider to the platform default.
            'commission_percent' => 'present|nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);
        $previous = $provider->commission_percent;
        $provider->update(['commission_percent' => $data['commission_percent']]);
        UrbanGoodzServiceAdminAudit::record(
            'service_provider',
            (int) $provider->id,
            'commission_updated',
            $request->user()?->id,
            [
                'from' => $previous,
                'to' => $data['commission_percent'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Provider commission updated.',
            'data' => ['provider_id' => $provider->id, 'commission_percent' => $provider->fresh()->commission_percent, 'effective_percent' => $provider->fresh()->commissionPercent()],
        ]);
    }

    public function categories()
    {
        $counts = UrbanGoodzProviderService::selectRaw('category,COUNT(*) total')->where('is_active', true)->groupBy('category')->pluck('total', 'category');

        return response()->json(['data' => collect(config('service_bookings.categories'))->map(fn ($slug) => [
            'slug' => $slug,
            'name' => \Illuminate\Support\Str::headline($slug),
            'active_services' => (int) ($counts[$slug] ?? 0),
        ])->values()]);
    }

    public function disputes(Request $request)
    {
        return response()->json(
            UrbanGoodzServiceDispute::with(['booking:id,status,user_id,provider_id,amount_paid_minor,refunded_amount_minor', 'provider:id,business_name'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(30)
        );
    }

    /**
     * Resolve a dispute. `refunded` issues money through the same idempotent
     * refund service used everywhere else, so a dispute can never create an
     * unreconciled payment record.
     */
    public function resolveDispute(Request $request, UrbanGoodzServiceDispute $dispute, ServiceBookingRefundService $refunds)
    {
        abort_unless($dispute->status === 'open', 409, 'This dispute is already resolved.');
        $data = $request->validate([
            'resolution' => 'required|in:refunded,rejected,resolved_without_refund',
            'resolution_notes' => 'required|string|max:2000',
            'amount_minor' => 'nullable|integer|min:1',
            'idempotency_key' => 'required_if:resolution,refunded|string|max:100',
        ]);

        $booking = $dispute->booking;
        $refunded = 0;
        if ($data['resolution'] === 'refunded') {
            $available = max(0, (int) $booking->amount_paid_minor - (int) $booking->refunded_amount_minor);
            $amount = (int) ($data['amount_minor'] ?? min((int) $dispute->requested_amount_minor, $available));
            abort_if($amount <= 0, 422, 'There is no refundable balance for this dispute.');
            $refunded = $refunds->refund($booking, $amount, $data['idempotency_key']);
        }

        $dispute->update([
            'status' => $data['resolution'],
            'resolution_notes' => $data['resolution_notes'],
            'resolved_amount_minor' => $refunded,
            'resolved_by' => $request->user()?->id,
            'resolved_at' => now(),
        ]);

        $booking->events()->create([
            'actor_type' => 'admin',
            'actor_id' => $request->user()?->id,
            'from_status' => $booking->status,
            'to_status' => $booking->status,
            'metadata' => ['event' => 'dispute_resolved', 'dispute_id' => $dispute->id, 'resolution' => $data['resolution'], 'amount_minor' => $refunded],
        ]);

        app(\App\Services\UrbanGoodzNotificationService::class)->notifyCustomer(
            (int) $dispute->user_id,
            'Dispute resolved',
            'Your service booking dispute has been resolved.',
            ['type' => 'service_booking_dispute_resolved', 'dispute_id' => $dispute->id, 'resolution' => $data['resolution'], 'amount_minor' => $refunded]
        );

        return response()->json(['message' => 'Dispute resolved.', 'data' => $dispute->fresh()]);
    }

    public function adjustEarning(Request $request, UrbanGoodzServiceProviderEarning $earning, ServiceEarningSettlementService $settlement)
    {
        $data = $request->validate([
            'adjustment_minor' => 'required|integer',
            'reason' => 'required|string|max:255',
        ]);

        return response()->json([
            'message' => 'Earning adjusted.',
            'data' => $settlement->adjust($earning, (int) $data['adjustment_minor'], $data['reason']),
        ]);
    }

    public function settleEarning(Request $request, UrbanGoodzServiceProviderEarning $earning, ServiceEarningSettlementService $settlement)
    {
        $data = $request->validate(['status' => 'required|in:approved,settled,void']);

        return response()->json([
            'message' => 'Earning updated.',
            'data' => $settlement->transition($earning, $data['status']),
        ]);
    }

    public function settleBatch(Request $request, ServiceEarningSettlementService $settlement)
    {
        $data = $request->validate([
            'provider_id' => 'nullable|integer',
            'earning_ids' => 'nullable|array|max:500',
            'earning_ids.*' => 'integer',
        ]);

        $earnings = UrbanGoodzServiceProviderEarning::where('status', 'approved')
            ->when(isset($data['provider_id']), fn ($q) => $q->where('provider_id', $data['provider_id']))
            ->when(isset($data['earning_ids']), fn ($q) => $q->whereIn('id', $data['earning_ids']))
            ->get();
        abort_if($earnings->isEmpty(), 422, 'There are no approved earnings to settle.');

        $result = $settlement->settleBatch($earnings);
        UrbanGoodzServiceAdminAudit::record(
            'settlement_batch',
            null,
            'settlement_batch_processed',
            $request->user()?->id,
            $result + ['provider_id' => $data['provider_id'] ?? null]
        );

        return response()->json(['message' => 'Settlement batch processed.', 'data' => $result]);
    }

    public function adminAudit(Request $request)
    {
        return response()->json(
            UrbanGoodzServiceAdminAudit::query()
                ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
                ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')))
                ->latest()
                ->paginate(50)
        );
    }

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
