<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('New conversation');
            // Full Claude API message history (wire format), replayed on every turn.
            $table->longText('messages');
            // Tool calls waiting for the user's approval, plus results already computed for the same turn.
            $table->longText('pending')->nullable();
            $table->timestamps();
        });

        // What the assistant has learned about this business and system; injected into every request.
        Schema::create('assistant_notes', function (Blueprint $table) {
            $table->id();
            $table->text('note');
            $table->foreignId('conversation_id')->nullable()->constrained('assistant_conversations')->nullOnDelete();
            $table->timestamps();
        });

        // Audit log of every change the assistant made after approval.
        Schema::create('assistant_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('assistant_conversations')->nullOnDelete();
            $table->string('tool', 40);
            $table->string('summary', 500)->nullable();
            $table->string('target', 500)->nullable();
            $table->string('backup_path', 500)->nullable();
            $table->enum('status', ['applied', 'rejected', 'failed']);
            $table->text('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_changes');
        Schema::dropIfExists('assistant_notes');
        Schema::dropIfExists('assistant_conversations');
    }
};
