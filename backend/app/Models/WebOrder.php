<?php

namespace App\Models;

use App\Models\Concerns\HasOrderNumber;
use Illuminate\Database\Eloquent\Model;

class WebOrder extends Model
{
    use HasOrderNumber;

    public const STATUSES = ['new', 'confirmed', 'shipped', 'delivered', 'cancelled'];

    protected $fillable = [
        'customer_name', 'phone', 'address', 'city', 'notes', 'items', 'total', 'price_to_confirm',
        'payment_method', 'status', 'admin_notes', 'handled_by', 'ip_address',
    ];

    protected $casts = [
        'items' => 'array',
        'total' => 'decimal:2',
        'price_to_confirm' => 'boolean',
    ];

    protected $hidden = ['ip_address'];

    protected $appends = ['reference'];

    /** Older name for the order number, kept so existing callers keep working. */
    public function getReferenceAttribute(): ?string
    {
        return $this->order_number;
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
