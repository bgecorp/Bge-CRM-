<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesRep extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
