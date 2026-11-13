<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['ai_claude', 'ai_openai', 'ai_custom', 'telegram', 'gmail', 'whatsapp']);
            $table->string('command_name');
            $table->string('recipient')->nullable();
            $table->text('message');
            $table->longText('response_payload')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->enum('status', ['sent', 'simulated', 'failed'])->default('simulated');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'channel', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
    }
};
