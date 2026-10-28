<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - stores.lead_days: days this customer needs to order and receive a delivery
 *   (overrides the default in Settings).
 * - follow_ups.due_date may be empty: "needs more data" follow-ups have no date.
 * - follow_ups.next_order_date, and the customer responses Interested / Ready to
 *   order / Not yet / No response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedTinyInteger('lead_days')->nullable()->after('sales_rep_id');
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->date('due_date')->nullable()->change();
            $table->string('outcome', 30)->nullable()->change();
            $table->date('next_order_date')->nullable()->after('next_action');
        });

        DB::table('follow_ups')->where('outcome', 'not_ready')->update(['outcome' => 'not_yet']);
        DB::table('follow_ups')->where('outcome', 'sold')->update(['outcome' => 'ready_to_order']);
    }

    public function down(): void
    {
        DB::table('follow_ups')->where('outcome', 'not_yet')->update(['outcome' => 'not_ready']);
        DB::table('follow_ups')->where('outcome', 'interested')->update(['outcome' => 'ready_to_order']);

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn('next_order_date');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('lead_days');
        });
    }
};
