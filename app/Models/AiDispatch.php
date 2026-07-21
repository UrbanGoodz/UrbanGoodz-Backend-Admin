<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class AiDispatch extends Model
{
    use SoftDeletes;

    protected $table = 'ai_dispatches';

    const STATUS_DRAFT = 'draft';
    const STATUS_AWAITING_APPROVAL = 'awaiting_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING_DRIVER = 'pending_driver';
    const STATUS_SENT = 'sent';
    const STATUS_VIEWED = 'viewed';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_DECLINED = 'declined';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EN_ROUTE_TO_PICKUP = 'en_route_to_pickup';
    const STATUS_ARRIVED_AT_PICKUP = 'arrived_at_pickup';
    const STATUS_PICKED_UP = 'picked_up';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_ARRIVED_AT_DELIVERY = 'arrived_at_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_EXCEPTION = 'exception';
    const STATUS_RETURN_REQUIRED = 'return_required';
    const STATUS_RETURNED = 'returned';
    const STATUS_SETTLEMENT_PENDING = 'settlement_pending';
    const STATUS_SETTLED = 'settled';
    const STATUS_CLOSED = 'closed';

    public static $canonicalStatuses = [
        self::STATUS_DRAFT, self::STATUS_AWAITING_APPROVAL, self::STATUS_APPROVED,
        self::STATUS_PENDING_DRIVER, self::STATUS_SENT, self::STATUS_VIEWED,
        self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_EXPIRED,
        self::STATUS_CANCELLED, self::STATUS_EN_ROUTE_TO_PICKUP, self::STATUS_ARRIVED_AT_PICKUP,
        self::STATUS_PICKED_UP, self::STATUS_IN_TRANSIT, self::STATUS_ARRIVED_AT_DELIVERY,
        self::STATUS_DELIVERED, self::STATUS_EXCEPTION, self::STATUS_RETURN_REQUIRED,
        self::STATUS_RETURNED, self::STATUS_SETTLEMENT_PENDING, self::STATUS_SETTLED,
        self::STATUS_CLOSED,
    ];

    public static $activeStatuses = [
        self::STATUS_SENT, self::STATUS_VIEWED, self::STATUS_ACCEPTED,
        self::STATUS_EN_ROUTE_TO_PICKUP, self::STATUS_ARRIVED_AT_PICKUP,
        self::STATUS_PICKED_UP, self::STATUS_IN_TRANSIT, self::STATUS_ARRIVED_AT_DELIVERY,
        self::STATUS_EXCEPTION, self::STATUS_RETURN_REQUIRED,
    ];

    public static $finalStatuses = [
        self::STATUS_DELIVERED, self::STATUS_RETURNED, self::STATUS_SETTLED,
        self::STATUS_CLOSED, self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_DECLINED,
    ];

    protected $fillable = [
        'uuid', 'source_type', 'source_id', 'load_id', 'route_id', 'order_id',
        'business_client_id', 'vendor_id', 'customer_id', 'dispatcher_id',
        'driver_id', 'delivery_man_id', 'created_by_type', 'created_by_id',
        'recommended_by_ai', 'ai_recommendation_id', 'ai_match_score', 'ai_reasoning_summary',
        'status', 'offer_expires_at', 'sent_at', 'viewed_at', 'accepted_at', 'declined_at',
        'expired_at', 'cancelled_at', 'completed_at', 'en_route_at', 'arrived_at_pickup_at',
        'picked_up_at', 'in_transit_at', 'arrived_at_delivery_at', 'delivered_at',
        'settlement_pending_at', 'settled_at', 'exception_state', 'exception_type',
        'exception_description', 'exception_resolved_by', 'decline_reason_code',
        'decline_reason', 'cancellation_reason', 'safety_flags', 'driver_payout_amount',
        'payout_currency', 'fulfillment_type', 'push_sent', 'push_status', 'push_error',
        'in_app_notified', 'metadata',
    ];

    protected $casts = [
        'uuid' => 'string',
        'recommended_by_ai' => 'boolean',
        'ai_match_score' => 'decimal:2',
        'offer_expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at_pickup_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'arrived_at_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'settlement_pending_at' => 'datetime',
        'settled_at' => 'datetime',
        'driver_payout_amount' => 'decimal:2',
        'push_sent' => 'boolean',
        'in_app_notified' => 'boolean',
        'metadata' => 'array',
        'safety_flags' => 'array',
    ];

    public function load($relations = null)
    {
        if ($relations !== null) {
            return parent::load($relations);
        }
        return $this->belongsTo(UrbanGoodzLoadBoardLoad::class, 'load_id');
    }

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'route_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function businessClient()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function dispatcher()
    {
        return $this->belongsTo(Admin::class, 'dispatcher_id');
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryMan::class, 'driver_id');
    }

    public function deliveryMan()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function aiRecommendation()
    {
        return $this->belongsTo(AiCopilotRecommendation::class, 'ai_recommendation_id');
    }

    public function scopeForBusinessClient($query, int $clientId)
    {
        return $query->where('business_client_id', $clientId);
    }

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('delivery_man_id', $driverId);
    }

    public function scopePendingOffers($query)
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_PENDING_DRIVER])
            ->where(function ($q) {
                $q->whereNull('offer_expires_at')->orWhere('offer_expires_at', '>', now());
            });
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::$activeStatuses);
    }

    public function scopeFinal($query)
    {
        return $query->whereIn('status', self::$finalStatuses);
    }

    public function scopeStatus($query, string $s)
    {
        return $query->where('status', $s);
    }

    public function scopeExpiredOffers($query)
    {
        return $query->where('offer_expires_at', '<', now())
            ->whereIn('status', [self::STATUS_PENDING_DRIVER, self::STATUS_SENT]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::$activeStatuses, true);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::$finalStatuses, true);
    }

    public function isPendingOffer(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_PENDING_DRIVER], true);
    }

    public function isExpiredOffer(): bool
    {
        return $this->isPendingOffer()
            && $this->offer_expires_at !== null
            && $this->offer_expires_at->isPast();
    }

    public function canAccept(): bool
    {
        return $this->isPendingOffer() && !$this->isExpiredOffer();
    }

    public function canDecline(): bool
    {
        return $this->isPendingOffer() && !$this->isExpiredOffer();
    }

    public function approve(): self
    {
        $this->update(['status' => self::STATUS_APPROVED]);
        return $this;
    }

    public function sendToDriver(): self
    {
        $this->update(['status' => self::STATUS_SENT, 'sent_at' => now()]);
        return $this;
    }

    public function markViewed(): self
    {
        if ($this->viewed_at === null) {
            $this->update(['status' => self::STATUS_VIEWED, 'viewed_at' => now()]);
        }
        return $this;
    }

    public function acceptDispatch(): self
    {
        if (!$this->canAccept()) {
            throw new \RuntimeException('Dispatch cannot be accepted in its current state.');
        }
        DB::transaction(function () {
            $this->update(['status' => self::STATUS_ACCEPTED, 'accepted_at' => now()]);
            if ($this->load_id) {
                UrbanGoodzLoadBoardLoad::where('id', $this->load_id)->update([
                    'delivery_man_id' => $this->delivery_man_id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);
            }
        });
        return $this;
    }

    public function declineDispatch(?string $reasonCode = null, ?string $reason = null): self
    {
        $this->update([
            'status' => self::STATUS_DECLINED,
            'declined_at' => now(),
            'decline_reason_code' => $reasonCode,
            'decline_reason' => $reason,
        ]);
        return $this;
    }

    public function expireOffer(): self
    {
        $this->update(['status' => self::STATUS_EXPIRED, 'expired_at' => now()]);
        return $this;
    }

    public function cancelDispatch(?string $reason = null): self
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
        return $this;
    }

    public function enRoute(): self
    {
        $this->update(['status' => self::STATUS_EN_ROUTE_TO_PICKUP, 'en_route_at' => now()]);
        return $this;
    }

    public function arriveAtPickup(): self
    {
        $this->update(['status' => self::STATUS_ARRIVED_AT_PICKUP, 'arrived_at_pickup_at' => now()]);
        return $this;
    }

    public function pickUp(): self
    {
        $this->update(['status' => self::STATUS_PICKED_UP, 'picked_up_at' => now()]);
        return $this;
    }

    public function startTransit(): self
    {
        $this->update(['status' => self::STATUS_IN_TRANSIT, 'in_transit_at' => now()]);
        return $this;
    }

    public function arriveAtDelivery(): self
    {
        $this->update(['status' => self::STATUS_ARRIVED_AT_DELIVERY, 'arrived_at_delivery_at' => now()]);
        return $this;
    }

    public function deliver(): self
    {
        DB::transaction(function () {
            $this->update(['status' => self::STATUS_DELIVERED, 'delivered_at' => now()]);
            if ($this->load_id) {
                UrbanGoodzLoadBoardLoad::where('id', $this->load_id)->update(['status' => 'delivered']);
            }
        });
        return $this;
    }

    public function reportException(string $type, string $description): self
    {
        $this->update([
            'status' => self::STATUS_EXCEPTION,
            'exception_state' => 'open',
            'exception_type' => $type,
            'exception_description' => $description,
        ]);
        return $this;
    }

    public function resolveException(string $resolvedBy): self
    {
        $this->update(['exception_state' => 'resolved', 'exception_resolved_by' => $resolvedBy]);
        return $this;
    }

    public function requireReturn(): self
    {
        $this->update(['status' => self::STATUS_RETURN_REQUIRED]);
        return $this;
    }

    public function markReturned(): self
    {
        $this->update(['status' => self::STATUS_RETURNED]);
        return $this;
    }

    public function markSettlementPending(): self
    {
        $this->update(['status' => self::STATUS_SETTLEMENT_PENDING, 'settlement_pending_at' => now()]);
        return $this;
    }

    public function settle(): self
    {
        DB::transaction(function () {
            $this->update(['status' => self::STATUS_SETTLED, 'settled_at' => now()]);
            if ($this->load_id) {
                UrbanGoodzLoadBoardLoad::where('id', $this->load_id)->update(['status' => 'settled']);
            }
        });
        return $this;
    }
}
