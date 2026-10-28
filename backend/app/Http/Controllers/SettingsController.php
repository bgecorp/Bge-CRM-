<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\CrmSettings;
use App\Services\FirstOrderFollowUps;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private CrmSettings $settings) {}

    public function show()
    {
        return $this->settings->all();
    }

    public function update(Request $request, FirstOrderFollowUps $firstOrders)
    {
        $rules = collect(CrmSettings::RULES)->map(fn ($r) => array_merge(['sometimes'], $r))->all();
        $settings = $this->settings->update($request->validate($rules));

        // Predictions are calculated on the fly; open first-order reminders are re-dated.
        Store::whereIn('status', ['active', 'prospect'])->each(fn (Store $store) => $firstOrders->sync($store));

        return $settings;
    }
}
