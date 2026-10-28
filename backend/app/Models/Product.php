<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'sku', 'unit', 'price'];

    protected $casts = ['price' => 'decimal:2'];

    protected $hidden = ['image_path'];

    protected $appends = ['image_url'];

    /** Absolute URL of the picture (served through the API, so it works from phones too), or null. */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? url("/api/products/{$this->id}/image").'?v='.$this->updated_at?->timestamp
            : null;
    }
}
