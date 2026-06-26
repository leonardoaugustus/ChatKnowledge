<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();

            // OpenClaw-style markdown sections (rendered as Markdown in the UI).
            $table->text('identity')->nullable();
            $table->text('soul')->nullable();
            $table->text('user')->nullable();
            $table->text('bootstrap')->nullable();
            $table->text('heartbeat')->nullable();
            $table->text('tools')->nullable();

            // Auto-generated from the composition of all sections (debug/audit).
            $table->longText('compiled_system_prompt')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_configs');
    }
};
