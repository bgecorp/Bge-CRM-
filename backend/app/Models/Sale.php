<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = ['store_id', 'product_id', 'sale_date', 'quantity', 'days_covered', 'unit_price', 'amount', 'notes'];

    protected $casts = [
        'sale_date' => 'date:Y-m-d',
        'quantity' => 'decimal:2',
        'days_covered' => 'integer',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
