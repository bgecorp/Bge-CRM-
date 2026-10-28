<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders delivered to a store. Each one adds to the store's stock on hand.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->date('delivered_at');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'delivered_at']);
        });

        // Forecast rules the owner can tune (buffer days, rolling period, weighting...).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamps();
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            // String instead of enum so new follow-up kinds need no schema change.
            $table->string('type', 30)->change();
            $table->date('estimated_stockout_date')->nullable()->after('estimated_stock_days');
            $table->boolean('provisional')->default(false)->after('estimated_stockout_date');
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn(['estimated_stockout_date', 'provisional']);
        });
        Schema::dropIfExists('settings');
        Schema::dropIfExists('orders');
    }
};
