<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    /** Created and re-dated by the system; anything else was set by a person and is left alone. */
    /** Customer response at a follow-up. */
    public const OUTCOMES = ['interested', 'ready_to_order', 'not_yet', 'no_response'];

    public const NEXT_ACTIONS = ['call', 'message', 'confirm_stock', 'process_order'];

    protected $fillable = [
        'store_id', 'sales_rep_id', 'product_id', 'due_date', 'type', 'status', 'reason',
        'avg_daily_sales', 'stock_quantity', 'estimated_stock_days', 'estimated_stockout_date', 'provisional',
        'outcome', 'outcome_notes', 'next_action', 'next_order_date', 'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'avg_daily_sales' => 'float',
        'stock_quantity' => 'float',
        'estimated_stock_days' => 'float',
        'estimated_stockout_date' => 'date:Y-m-d',
        'next_order_date' => 'date:Y-m-d',
        'provisional' => 'boolean',
    ];

    protected $appends = ['days_until_due'];

    public function getDaysUntilDueAttribute(): ?int
    {
        return $this->due_date ? (int) today()->diffInDays($this->due_date, false) : null;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
