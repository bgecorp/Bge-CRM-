<?php

use App\Support\OrderNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every order gets a unique, permanent order number (see App\Support\OrderNumber).
 * Existing orders are numbered from their own date, then the column becomes required.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = ['orders' => 'delivered_at', 'web_orders' => 'created_at'];

        // 1. Add the column everywhere first: numbers are checked for uniqueness across all tables.
        foreach (array_keys($tables) as $table) {
            if (! Schema::hasColumn($table, 'order_number')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('order_number', 20)->nullable()->after('id');
                });
            }
        }

        // 2. Number the existing orders from their own date.
        foreach ($tables as $table => $dateColumn) {
            DB::table($table)->whereNull('order_number')->orderBy('id')
                ->select('id', $dateColumn.' as order_date', 'created_at')
                ->each(function ($row) use ($table) {
                    $date = $row->order_date ?? $row->created_at;
                    DB::table($table)->where('id', $row->id)->update([
                        'order_number' => OrderNumber::generate($date ? Carbon::parse($date) : null),
                    ]);
                });
        }

        // 3. Make the number required and unique.
        foreach (array_keys($tables) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('order_number', 20)->nullable(false)->change();
                $t->unique('order_number');
            });
        }
    }

    public function down(): void
    {
        foreach (['orders', 'web_orders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['order_number']);
                $t->dropColumn('order_number');
            });
        }
    }
};
