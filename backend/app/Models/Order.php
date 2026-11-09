<?php

namespace App\Models;

use App\Models\Concerns\HasOrderNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasOrderNumber;

    protected $fillable = ['store_id', 'product_id', 'delivered_at', 'quantity', 'unit_price', 'amount', 'notes'];

    protected $casts = [
        'delivered_at' => 'date:Y-m-d',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /** Store orders are numbered from their delivery date. */
    protected function orderNumberDate(): mixed
    {
        return $this->delivered_at;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
