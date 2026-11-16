<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->string('kind', 30);
            $t->string('label', 180);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('evidence_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignId('source_id')->constrained('evidence_sources')->restrictOnDelete();
            $t->string('title', 180);
            $t->string('document_type', 40);
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('evidence_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('document_id')->constrained('evidence_documents')->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->string('original_name', 180);
            $t->string('path', 240);
            $t->string('mime', 40);
            $t->unsignedBigInteger('bytes');
            $t->string('sha256', 64);
            $t->string('classification', 40)->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['building_id', 'sha256'], 'ev_version_building_hash_uq');
            $t->unique(['document_id', 'number'], 'ev_version_number_uq');
        });
        Schema::create('evidence_extractions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('version_id')->constrained('evidence_versions')->restrictOnDelete();
            $t->string('status', 25);
            $t->string('provider', 40);
            $t->string('model', 100);
            $t->unsignedInteger('attempts')->default(0);
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('consented_at');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->string('error_code', 40)->nullable();
            $t->timestamps();
            $t->unique('version_id', 'ev_extraction_version_uq');
        });
        Schema::create('evidence_assertions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignId('space_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('version_id')->nullable()->constrained('evidence_versions')->restrictOnDelete();
            $t->foreignUuid('extraction_id')->nullable()->constrained('evidence_extractions')->restrictOnDelete();
            $t->string('subject_type', 20);
            $t->string('subject_reference', 100);
            $t->string('semantic_key', 80);
            $t->string('value_type', 20);
            $t->json('value');
            $t->text('original_value');
            $t->string('method', 30);
            $t->decimal('ai_confidence', 5, 4)->nullable();
            $t->string('documentary_support', 30);
            $t->string('human_verification', 30)->default('not_reviewed');
            $t->string('professional_verification', 30)->default('not_assessed');
            $t->string('status', 20)->default('proposed');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->date('effective_on')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'semantic_key', 'status'], 'ev_assertion_lookup_idx');
        });
        Schema::create('evidence_references', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('assertion_id')->constrained('evidence_assertions')->restrictOnDelete();
            $t->foreignUuid('version_id')->constrained('evidence_versions')->restrictOnDelete();
            $t->unsignedInteger('page')->nullable();
            $t->json('region')->nullable();
            $t->text('quote')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('evidence_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('assertion_id')->constrained('evidence_assertions')->restrictOnDelete();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 30);
            $t->text('justification')->nullable();
            $t->json('before_data');
            $t->json('after_data');
            $t->timestamp('created_at');
        });
        Schema::create('evidence_conflicts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('assertion_id')->constrained('evidence_assertions')->restrictOnDelete();
            $t->foreignUuid('existing_assertion_id')->nullable()->constrained('evidence_assertions')->restrictOnDelete();
            $t->json('baseline')->nullable();
            $t->string('status', 20)->default('open');
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('justification')->nullable();
            $t->timestamps();
        });
        Schema::create('evidence_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 40);
            $t->string('entity_reference', 100);
            $t->json('details')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('evidence_file_deletions', function (Blueprint $t) {
            $t->id();
            $t->string('path', 240)->unique();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['evidence_file_deletions', 'evidence_audits', 'evidence_conflicts', 'evidence_reviews', 'evidence_references', 'evidence_assertions', 'evidence_extractions', 'evidence_versions', 'evidence_documents', 'evidence_sources'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
