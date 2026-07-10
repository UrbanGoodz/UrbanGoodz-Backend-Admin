<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzClientInvoice extends Model
{
    use SoftDeletes;

    const INVOICE_TYPES = ['route', 'batch', 'summary', 'custom'];
    const STATUSES = ['draft', 'sent', 'paid', 'overdue', 'canceled'];

    protected $fillable = [
        'invoice_number', 'business_client_id', 'dedicated_route_id',
        'invoice_type', 'subtotal', 'tax', 'total', 'currency',
        'status', 'notes', 'sent_at', 'paid_at', 'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public static function nextInvoiceNumber(): string
    {
        return 'UGI-' . now()->format('Ymd') . '-' . str_pad((self::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    }
}
