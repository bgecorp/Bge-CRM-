<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCount extends Model
{
    protected $fillable = ['store_id', 'product_id', 'counted_at', 'quantity'];

    protected $casts = [
        'counted_at' => 'date:Y-m-d',
        'quantity' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
