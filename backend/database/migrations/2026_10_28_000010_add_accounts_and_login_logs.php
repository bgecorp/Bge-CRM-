<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts: a super admin (full access, creates accounts, sees the login log)
 * and representatives (products read-only; stock prediction, orders and
 * follow-ups). Every sign-in, sign-out and failed attempt is logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('representative')->after('password');
            $table->foreignId('sales_rep_id')->nullable()->after('role')->constrained('sales_reps')->nullOnDelete();
            $table->boolean('active')->default(true)->after('sales_rep_id');
            $table->timestamp('last_login_at')->nullable()->after('active');
        });

        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            // login | logout | failed
            $table->string('event', 10);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_rep_id');
            $table->dropColumn(['role', 'active', 'last_login_at']);
        });
    }
};
