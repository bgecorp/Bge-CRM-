<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_reps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 60)->nullable()->unique();
            $table->string('unit', 30)->default('pack');
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->enum('category', ['small_sari_sari', 'regular_sari_sari', 'mini_grocery', 'regular_grocery']);
            $table->enum('status', ['prospect', 'active', 'inactive', 'lost'])->default('active');
            $table->foreignId('sales_rep_id')->nullable()->constrained('sales_reps')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('name');
        });

        // One row = packs a store sold over `days_covered` days, ending on `sale_date`.
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->date('sale_date');
            $table->decimal('quantity', 10, 2);
            $table->unsignedSmallInteger('days_covered')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'sale_date']);
            $table->index('sale_date');
        });

        // Observed stock on hand. Predictions only use real counts, never invented stock.
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->date('counted_at');
            $table->decimal('quantity', 10, 2);
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'counted_at']);
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->constrained('sales_reps')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_date');
            $table->enum('type', ['reorder_estimate', 'default_7_day', 'manual']);
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->string('reason')->nullable();
            // Snapshot of the estimate that produced this follow-up.
            $table->decimal('avg_daily_sales', 12, 4)->nullable();
            $table->decimal('stock_quantity', 10, 2)->nullable();
            $table->decimal('estimated_stock_days', 12, 4)->nullable();
            $table->enum('outcome', ['sold', 'ready_to_order', 'not_ready', 'no_response'])->nullable();
            $table->text('outcome_notes')->nullable();
            $table->string('next_action')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sales_reps');
    }
};
