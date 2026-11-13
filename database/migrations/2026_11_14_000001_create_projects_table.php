<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 10)->default('📁');
            $table->string('color', 20)->default('violet');
            $table->enum('status', ['active', 'on_hold', 'completed', 'archived'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
