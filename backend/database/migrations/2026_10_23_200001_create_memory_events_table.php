<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('memory_id')->nullable()->constrained('memories')->nullOnDelete();
            $table->string('action', 60);
            $table->string('actor_type', 60)->nullable();
            $table->string('request_id', 128)->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
            $table->index(['memory_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_events');
    }
};
