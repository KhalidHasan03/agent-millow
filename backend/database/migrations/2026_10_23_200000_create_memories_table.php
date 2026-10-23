<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 120)->nullable()->index();
            $table->string('title', 200);
            $table->text('content');
            $table->string('category', 60)->default('fact');
            $table->string('source', 60)->default('user');
            $table->unsignedTinyInteger('confidence')->default(100);
            $table->boolean('sensitive')->default(false);
            $table->boolean('pinned')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'source']);
            $table->index(['user_id', 'pinned']);
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
