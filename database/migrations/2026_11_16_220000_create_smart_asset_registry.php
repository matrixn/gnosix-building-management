<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('key', 60);
            $t->string('name', 100);
            $t->json('attribute_schema');
            $t->unsignedInteger('revision')->default(1);
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'key'], 'asset_type_org_key_uq');
        });
        foreach (config('assets.types') as $key => $type) {
            DB::table('asset_types')->insert(['uuid' => (string) Str::uuid(), 'key' => $key, 'name' => $key, 'attribute_schema' => json_encode($type['attributes']), 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $t->string('reference', 40)->unique();
            $t->string('name', 150);
            $t->string('external_reference', 100)->nullable();
            $t->string('serial_key', 160)->nullable();
            $t->foreignId('building_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('floor_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('space_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('system_id')->nullable()->constrained('assets')->restrictOnDelete();
            $t->boolean('is_system')->default(false);
            $t->string('status', 30)->default('unknown');
            $t->json('manual_data');
            $t->unsignedInteger('revision')->default(1);
            $t->timestamp('archived_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['organization_id', 'archived_at', 'id'], 'asset_org_state_idx');
            $t->index(['property_id', 'building_id', 'space_id'], 'asset_location_idx');
            $t->index(['organization_id', 'serial_key'], 'asset_org_serial_idx');
            $t->index(['property_id', 'external_reference'], 'asset_explicit_ref_idx');
        });
        Schema::create('asset_served_spaces', function (Blueprint $t) {
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->foreignId('space_id')->constrained()->cascadeOnDelete();
            $t->primary(['asset_id', 'space_id']);
        });
        Schema::create('asset_evidence_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('assertion_id')->nullable()->constrained('evidence_assertions')->nullOnDelete();
            $t->uuid('source_uuid');
            $t->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('withdrawn_at')->nullable();
            $t->timestamps();
            $t->unique(['asset_id', 'source_uuid'], 'asset_assertion_link_uq');
        });
        Schema::create('asset_fact_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->foreignId('link_id')->constrained('asset_evidence_links')->restrictOnDelete();
            $t->string('field', 50);
            $t->json('value');
            $t->string('serial_key', 160)->nullable();
            $t->unsignedInteger('source_revision');
            $t->string('status', 20)->default('active');
            $t->text('justification');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
            $t->index(['asset_id', 'field', 'status'], 'asset_fact_current_idx');
            $t->index(['field', 'serial_key', 'status'], 'asset_fact_serial_idx');
        });
        Schema::create('asset_document_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('version_id')->nullable()->constrained('evidence_versions')->nullOnDelete();
            $t->uuid('source_uuid');
            $t->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
            $t->unique(['asset_id', 'source_uuid'], 'asset_doc_link_uq');
        });
        Schema::create('asset_photos', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->string('path', 240);
            $t->string('mime', 30);
            $t->string('sha256', 64);
            $t->string('caption', 160)->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
        });
        Schema::create('asset_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 50);
            $t->json('before_data')->nullable();
            $t->json('after_data')->nullable();
            $t->text('justification')->nullable();
            $t->timestamp('created_at');
            $t->index(['asset_id', 'id'], 'asset_audit_history_idx');
        });
    }

    public function down(): void
    {
        foreach (['asset_audits', 'asset_photos', 'asset_document_links', 'asset_fact_applications', 'asset_evidence_links', 'asset_served_spaces', 'assets', 'asset_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
