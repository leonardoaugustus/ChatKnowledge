<?php

use App\Enums\CurationStatus;
use App\Enums\PublicationStatus;
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
        Schema::create('knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->string('type');
            $table->string('title');
            $table->longText('content');
            $table->text('summary')->nullable();
            $table->text('source_excerpt')->nullable();
            $table->decimal('confidence_score', 4, 3)->nullable();

            $table->string('curation_status')->default(CurationStatus::Pending->value);
            $table->string('publication_status')->default(PublicationStatus::Unpublished->value);

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->string('vector_file_id')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'agent_id', 'curation_status']);
            $table->index(['agent_id', 'publication_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_items');
    }
};
