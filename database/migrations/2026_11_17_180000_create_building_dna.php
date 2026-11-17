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
        Schema::table('properties', fn (Blueprint $t) => $t->uuid('uuid')->nullable()->unique());
        DB::table('properties')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $r) {
                DB::table('properties')->where('id', $r->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });
        Schema::create('dna_relation_types', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->json('semantics');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('dna_relations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('dna_relation_type_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('type_key', 60);
            $t->string('source', 80);
            $t->string('target', 80);
            $t->string('origin_key', 180)->nullable()->unique();
            $t->string('backing', 16)->default('new');
            $t->string('state', 20)->default('declared');
            $t->text('declaration');
            $t->json('review_basis')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('contradicts_id')->nullable()->constrained('dna_relations')->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['property_id', 'type_key', 'source', 'target'], 'dna_relation_tuple');
            $t->index(['property_id', 'source', 'state'], 'dna_source');
            $t->index(['property_id', 'target', 'state'], 'dna_target');
        });
        Schema::create('dna_relation_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dna_relation_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('version_id')->nullable()->constrained('evidence_versions')->nullOnDelete();
            $t->foreignUuid('assertion_id')->nullable()->constrained('evidence_assertions')->nullOnDelete();
            $t->uuid('source_uuid');
            $t->unsignedInteger('source_revision')->nullable();
            $t->unsignedInteger('page')->nullable();
            $t->timestamp('created_at');
            $t->unique(['dna_relation_id', 'source_uuid'], 'dna_source_unique');
        });
        Schema::create('dna_relation_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dna_relation_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->json('snapshot');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
            $t->unique(['dna_relation_id', 'revision'], 'dna_revision');
        });
        Schema::create('dna_insight_decisions', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('fingerprint', 64);
            $t->json('snapshot');
            $t->string('state', 16);
            $t->text('reason');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['property_id', 'fingerprint'], 'dna_insight_fingerprint');
        });
        Schema::table('asset_served_spaces', fn (Blueprint $t) => $t->index(['space_id', 'asset_id'], 'dna_served_reverse'));
    }

    public function down(): void
    {
        Schema::table('asset_served_spaces', fn (Blueprint $t) => $t->dropIndex('dna_served_reverse'));
        foreach (['dna_insight_decisions', 'dna_relation_revisions', 'dna_relation_sources', 'dna_relations', 'dna_relation_types'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('properties', fn (Blueprint $t) => $t->dropUnique('properties_uuid_unique'));
        Schema::table('properties', fn (Blueprint $t) => $t->dropColumn('uuid'));
    }
};
