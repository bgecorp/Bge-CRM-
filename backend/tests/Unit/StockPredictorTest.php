<?php

namespace Tests\Unit;

use App\Services\CrmSettings;
use App\Services\StockPredictor;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class StockPredictorTest extends TestCase
{
    private StockPredictor $predictor;

    protected function setUp(): void
    {
        parent::setUp();
        // estimate() is pure; the settings store is never touched.
        $this->predictor = new StockPredictor((new \ReflectionClass(CrmSettings::class))->newInstanceWithoutConstructor());
    }

    private function order(string $date, float $qty): object
    {
        return (object) ['delivered_at' => $date, 'quantity' => $qty];
    }

    private function predict(array $orders, string $asOf = '2026-10-10', array $settings = []): array
    {
        return $this->predictor->estimate(collect($orders), $settings + CrmSettings::DEFAULTS, CarbonImmutable::parse($asOf));
    }

    /** The worked example: Oct 1 = 12, Oct 6 = 6, Oct 10 = 8. */
    private function example(): array
    {
        return [$this->order('2026-10-01', 12), $this->order('2026-10-06', 6), $this->order('2026-10-10', 8)];
    }

    public function test_first_order_is_baseline_only(): void
    {
        $p = $this->predict([$this->order('2026-10-01', 12)]);
        $this->assertSame('baseline', $p['basis']);
        $this->assertSame(1, $p['total_orders']);
        $this->assertNull($p['avg_daily_consumption']);
        $this->assertNull($p['estimated_stock_days']);
        $this->assertNull($p['next_purchase_date']);
        $this->assertSame('new', $p['buying_pattern']);
        $this->assertStringContainsString('starts when they order again', $p['summary']);
    }

    public function test_second_order_gives_first_estimate(): void
    {
        // 12 packs lasted 5 days = 2.4/day; the latest 6 packs last 2.5 days → Oct 6 + 3 = Oct 9.
        $p = $this->predict(array_slice($this->example(), 0, 2), '2026-10-06');
        $this->assertSame('first_estimate', $p['basis']);
        $this->assertEqualsWithDelta(2.4, $p['avg_daily_consumption'], 1e-12);
        $this->assertEqualsWithDelta(2.5, $p['estimated_stock_days'], 1e-12);
        $this->assertSame('2026-10-09', $p['next_purchase_date']);
        $this->assertSame(3, $p['days_until_next_purchase']);
    }

    public function test_third_order_refines_with_all_intervals(): void
    {
        // (12 + 6) ÷ (5 + 4) = 2 packs/day; 8 ÷ 2 = 4 days → Oct 14.
        $p = $this->predict($this->example());
        $this->assertSame('refined', $p['basis']);
        $this->assertSame(3, $p['total_orders']);
        $this->assertSame(26.0, $p['total_packs']);
        $this->assertEqualsWithDelta(4.5, $p['avg_days_between_orders'], 1e-12);
        $this->assertEqualsWithDelta(2.0, $p['avg_daily_consumption'], 1e-12);
        $this->assertSame(8.0, $p['latest_order_qty']);
        $this->assertEqualsWithDelta(4.0, $p['estimated_stock_days'], 1e-12);
        $this->assertSame('2026-10-14', $p['next_purchase_date']);
        $this->assertSame(4, $p['days_until_next_purchase']);
        $this->assertSame('frequent', $p['buying_pattern']);
        $this->assertStringContainsString('12 + 6 packs over 5 + 4 days', $p['summary']);
    }

    public function test_order_of_records_does_not_matter(): void
    {
        $this->assertSame($this->predict($this->example())['next_purchase_date'], $this->predict(array_reverse($this->example()))['next_purchase_date']);
    }

    public function test_partial_days_round_up_for_the_date_but_stay_exact(): void
    {
        // 8 packs at 1.5/day = 5.33 days → next purchase on day 6.
        $p = $this->predict([$this->order('2026-10-01', 6), $this->order('2026-10-05', 8)], '2026-10-05');
        $this->assertEqualsWithDelta(8 / 1.5, $p['estimated_stock_days'], 1e-12);
        $this->assertSame('2026-10-11', $p['next_purchase_date']);
    }

    public function test_same_day_orders_count_as_one(): void
    {
        $p = $this->predict([$this->order('2026-10-01', 7), $this->order('2026-10-01', 5), $this->order('2026-10-06', 6)], '2026-10-06');
        $this->assertSame(2, $p['total_orders']);
        $this->assertEqualsWithDelta(2.4, $p['avg_daily_consumption'], 1e-12);
    }

    public function test_past_due_when_next_purchase_has_passed(): void
    {
        $p = $this->predict($this->example(), '2026-10-20');
        $this->assertSame(-6, $p['days_until_next_purchase']);
        $this->assertSame(10, $p['days_since_latest_order']);
    }

    public function test_buying_patterns(): void
    {
        $every = fn (int $days) => $this->predict([$this->order('2026-09-01', 10), $this->order(CarbonImmutable::parse('2026-09-01')->addDays($days)->toDateString(), 10)], '2026-10-10')['buying_pattern'];
        $this->assertSame('frequent', $every(7));
        $this->assertSame('moderate', $every(8));
        $this->assertSame('moderate', $every(14));
        $this->assertSame('occasional', $every(15));
        $this->assertSame('frequent', $this->predict([$this->order('2026-09-01', 10), $this->order('2026-09-21', 10)], '2026-10-10', ['frequent_max_days' => 30])['buying_pattern']);
    }

    public function test_recent_weighting_follows_the_newer_rate(): void
    {
        // Old interval: 30 packs over 10 days (3/day). Recent: 10 packs over 10 days (1/day).
        $orders = [$this->order('2026-08-01', 30), $this->order('2026-08-11', 10), $this->order('2026-10-01', 10)];
        $plain = $this->predict([$orders[0], $orders[1], $this->order('2026-08-21', 10)], '2026-08-21');
        $this->assertEqualsWithDelta(2.0, $plain['avg_daily_consumption'], 1e-12);

        $weighted = $this->predict([$orders[0], $orders[1], $this->order('2026-08-21', 10)], '2026-10-10', ['recent_weighting' => true, 'weighting_half_life_days' => 5]);
        $this->assertLessThan(2.0, $weighted['avg_daily_consumption']);
        $this->assertTrue($weighted['weighted']);
    }

    public function test_zero_pack_history_cannot_estimate(): void
    {
        $p = $this->predict([$this->order('2026-10-01', 0), $this->order('2026-10-06', 6)], '2026-10-06');
        $this->assertSame('no_usage', $p['basis']);
        $this->assertNull($p['next_purchase_date']);
    }
}
