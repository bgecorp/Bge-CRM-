<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Store;
use Carbon\CarbonImmutable;

/**
 * The only automatic follow-up: a customer with just one order of a product
 * gets a reminder a few days later (first_order_follow_up_days, default 5) to
 * check how it is selling. Once a second order is recorded the prediction takes
 * over, so a reminder still open for that product is closed.
 */
class FirstOrderFollowUps
{
    public const TYPE = 'first_order';

    public function __construct(private StockPredictor $predictor, private CrmSettings $settings) {}

    public function sync(Store $store): void
    {
        $open = $store->followUps()->where('status', 'pending')->where('type', self::TYPE)->get()->keyBy('product_id');

        if (! in_array($store->status, ['active', 'prospect'], true)) {
            $open->each->update(['status' => 'cancelled', 'outcome_notes' => 'Customer is no longer active.']);

            return;
        }

        $days = (int) $this->settings->get('first_order_follow_up_days');
        $firstOnly = $this->predictor->forStores([$store->id])->where('basis', 'baseline')->keyBy('product_id');

        foreach ($firstOnly as $productId => $p) {
            $order = CarbonImmutable::parse($p['latest_order_date']);
            $attributes = [
                'sales_rep_id' => $store->sales_rep_id,
                'due_date' => $order->addDays($days)->toDateString(),
                'reason' => mb_substr("First order: {$this->fmt($p['latest_order_qty'])} packs of {$p['product_name']} on {$order->format('M j')}. Ask how it is selling and whether they will reorder.", 0, 255),
                'next_action' => 'call',
            ];

            if ($existing = $open->get($productId)) {
                $existing->update($attributes);
            } elseif (! $this->alreadyHandled($store, $productId, $order)) {
                $store->followUps()->create($attributes + ['product_id' => $productId, 'type' => self::TYPE, 'status' => 'pending']);
            }
        }

        // Products that now have a second order (or no orders at all) no longer need the reminder.
        $open->except($firstOnly->keys()->all())->each->update([
            'status' => 'cancelled',
            'outcome_notes' => 'Closed automatically: a second order was recorded, so the prediction now applies.',
        ]);
    }

    /** Don't recreate a reminder someone already completed or cancelled for this same first order. */
    private function alreadyHandled(Store $store, int $productId, CarbonImmutable $order): bool
    {
        return $store->followUps()
            ->where('type', self::TYPE)
            ->where('product_id', $productId)
            ->where('status', '!=', 'pending')
            ->where('created_at', '>=', $order->startOfDay())
            ->exists();
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
