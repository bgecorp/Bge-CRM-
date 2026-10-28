<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Orders placed by the public on the landing page (cash on delivery). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('phone', 30);
            $table->string('address', 500);
            $table->string('city', 120);
            $table->string('notes', 1000)->nullable();
            // [{offer, name, deal, packs, price, quantity, subtotal}]
            $table->json('items');
            $table->decimal('total', 12, 2)->default(0);
            // Some items have no fixed price yet; they are confirmed by phone.
            $table->boolean('price_to_confirm')->default(false);
            $table->string('payment_method', 20)->default('cod');
            // new | confirmed | shipped | delivered | cancelled
            $table->string('status', 20)->default('new');
            $table->string('admin_notes', 1000)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_orders');
    }
};
