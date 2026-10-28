<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Owner-tunable forecast rules, stored as key/value rows with safe defaults. */
class CrmSettings
{
    public const DEFAULTS = [
        // Give recent order intervals more weight when estimating daily consumption.
        'recent_weighting' => false,
        // With weighting on, an interval this many days old counts half as much as a current one.
        'weighting_half_life_days' => 30,
        // Buying pattern: ordering at least this often (average days between orders) is "frequent"…
        'frequent_max_days' => 7,
        // …at least this often is "moderate"; less often is "occasional".
        'moderate_max_days' => 14,
        // Follow-up reminder this many days after a customer's first order of a product.
        'first_order_follow_up_days' => 5,
    ];

    public const RULES = [
        'recent_weighting' => ['boolean'],
        'weighting_half_life_days' => ['integer', 'min:1', 'max:365'],
        'frequent_max_days' => ['integer', 'min:1', 'max:90'],
        'moderate_max_days' => ['integer', 'min:1', 'max:365'],
        'first_order_follow_up_days' => ['integer', 'min:1', 'max:60'],
    ];
    private ?array $cache = null;

    public function all(): array
    {
        if ($this->cache === null) {
            $stored = DB::table('settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all();
            $this->cache = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        }

        return $this->cache;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key];
    }

    public function update(array $values): array
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            settype($value, gettype(self::DEFAULTS[$key]));
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }
        $this->cache = null;

        return $this->all();
    }
}
