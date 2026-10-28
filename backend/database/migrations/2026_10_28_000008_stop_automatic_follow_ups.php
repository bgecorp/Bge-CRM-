<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Follow-ups are no longer created automatically; predictions come from order
 * history instead. Open automatic follow-ups are cancelled; ones set by a
 * person are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('follow_ups')
            ->where('status', 'pending')
            ->whereIn('type', ['reorder_estimate', 'needs_data', 'default_7_day', 'manual_review', 'unconfirmed_check'])
            ->update(['status' => 'cancelled', 'outcome_notes' => 'Automatic follow-ups were turned off.', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Nothing to restore: cancelled follow-ups stay in the history.
    }
};
