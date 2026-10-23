<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('input_schema');
            $table->json('output_schema')->nullable();
            $table->string('risk_level', 16)->default('low');
            $table->json('required_scopes')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->boolean('approval_required')->default(false);
            $table->json('limits')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['tool_id', 'version']);
            $table->index(['tool_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_versions');
    }
};
