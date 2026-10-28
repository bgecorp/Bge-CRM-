<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebOrder extends Model
{
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

    /** Short order number to read out on the phone, e.g. BGE-00042. */
    public function getReferenceAttribute(): string
    {
        return 'BGE-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
