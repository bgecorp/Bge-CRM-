<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    public const CATEGORIES = ['small_sari_sari', 'regular_sari_sari', 'mini_grocery', 'regular_grocery'];

    public const STATUSES = ['prospect', 'active', 'inactive', 'lost'];

    protected $fillable = ['name', 'contact_person', 'phone', 'address', 'facebook_url', 'messenger_url', 'category', 'status', 'sales_rep_id', 'lead_days', 'notes'];

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }
}
