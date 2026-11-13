<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_column_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('project_name_snapshot')->nullable();
            $table->string('pic_name_snapshot')->nullable();
            $table->enum('priority', ['high', 'medium', 'low', 'done'])->default('medium');
            $table->string('icon', 10)->default('📌');
            $table->string('color', 20)->default('violet');
            $table->date('due_date')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'board_column_id', 'sort_order']);
            $table->index(['user_id', 'priority', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
