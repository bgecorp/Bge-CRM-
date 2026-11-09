<?php

namespace App\Models\Concerns;

use App\Support\OrderNumber;
use Illuminate\Support\Carbon;

/**
 * Gives a model a permanent order_number on creation and makes it the key used
 * in URLs, so routes like /orders/{order} take the order number, not the row id.
 */
trait HasOrderNumber
{
    protected static function bootHasOrderNumber(): void
    {
        static::creating(function ($model) {
            if (empty($model->order_number)) {
                $date = $model->orderNumberDate();
                $model->order_number = OrderNumber::generate($date ? Carbon::parse($date) : null);
            }
        });

        // The number is permanent: other records and customers refer to it.
        static::updating(function ($model) {
            if ($model->isDirty('order_number')) {
                $model->order_number = $model->getOriginal('order_number');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /** The date the number starts with; defaults to today. */
    protected function orderNumberDate(): mixed
    {
        return null;
    }
}
