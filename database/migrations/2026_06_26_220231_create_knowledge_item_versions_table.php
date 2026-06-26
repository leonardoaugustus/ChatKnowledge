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
        Schema::create('knowledge_item_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('type');
            $table->string('title');
            $table->longText('content');
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['knowledge_item_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_item_versions');
    }
};
