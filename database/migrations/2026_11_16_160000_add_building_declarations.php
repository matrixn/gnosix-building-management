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
        Schema::table('buildings', function (Blueprint $t) {
            $t->uuid('uuid')->nullable()->unique();
            $t->string('reference', 40)->nullable()->unique();
            $t->string('display_name', 150)->nullable();
            $t->string('classification', 40)->nullable();
            $t->string('custom_classification', 100)->nullable();
            $t->string('address', 500)->nullable();
            $t->string('country', 2)->nullable();
            $t->string('timezone', 64)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->unsignedSmallInteger('construction_year')->nullable();
            $t->string('state', 20)->default('draft');
            $t->unsignedInteger('declaration_version')->default(1);
            $t->string('verification_state', 30)->default('unverified');
        });
        foreach (['floors', 'spaces'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->uuid('uuid')->nullable()->unique();
                $t->timestamp('archived_at')->nullable()->index();
                if ($table === 'floors') {
                    $t->string('kind', 20)->nullable();
                }
            });
        }
        foreach (['buildings', 'floors', 'spaces'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(250, function ($rows) use ($table) {
                $batch = [];
                foreach ($rows as $r) {
                    $uuid = (string) Str::uuid();
                    // Include existing required columns for portable upserts. Only identity
                    // columns are updated; names, timestamps and physical facts stay intact.
                    $data = (array) $r;
                    $data['uuid'] = $uuid;
                    if ($table === 'buildings') {
                        $data['reference'] = 'GNX-'.implode('-', str_split(strtoupper(substr(str_replace('-', '', $uuid), 0, 16)), 4));
                    }
                    $batch[] = $data;
                }
                DB::table($table)->upsert($batch, ['id'], $table === 'buildings' ? ['uuid', 'reference'] : ['uuid']);
            });
        }
        Schema::create('space_beds', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('space_id')->constrained()->restrictOnDelete();
            $t->string('label', 80);
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('accommodation_units', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->string('name', 150);
            $t->string('unit_type', 30);
            $t->foreignId('bed_id')->nullable()->constrained('space_beds')->restrictOnDelete();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('accommodation_unit_space', function (Blueprint $t) {
            $t->foreignId('accommodation_unit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('space_id')->constrained()->restrictOnDelete();
            $t->primary(['accommodation_unit_id', 'space_id']);
        });
        Schema::create('building_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 30);
            $t->string('source_kind', 20);
            $t->string('entity_type', 30);
            $t->string('entity_reference', 80)->nullable();
            $t->json('before_data')->nullable();
            $t->json('after_data')->nullable();
            $t->timestamp('created_at');
            $t->index(['building_id', 'id']);
        });
        Schema::create('building_assertions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignId('revision_id')->nullable()->constrained('building_revisions')->restrictOnDelete();
            $t->string('field_key', 80);
            $t->json('value')->nullable();
            $t->string('source_kind', 20);
            $t->text('source_reference')->nullable();
            $t->string('verification_state', 30)->default('unverified');
            $t->timestamp('created_at');
            $t->index(['building_id', 'field_key']);
        });
        Schema::create('building_declaration_drafts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('building_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('base_version')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->json('state');
            $t->string('stage', 20)->default('identity');
            $t->string('source_kind', 20)->default('manual');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['property_id', 'user_id', 'building_id']);
        });
        DB::table('buildings')->orderBy('id')->chunkById(250, function ($rows) {
            $batch = [];
            foreach ($rows as $r) {
                $batch[] = ['building_id' => $r->id, 'action' => 'identity_assigned', 'source_kind' => 'legacy', 'entity_type' => 'building', 'entity_reference' => $r->uuid, 'after_data' => json_encode(['name' => $r->name, 'uuid' => $r->uuid, 'reference' => $r->reference]), 'created_at' => now()];
            }
            DB::table('building_revisions')->insert($batch);
        });
    }

    public function down(): void
    {
        foreach (['building_declaration_drafts', 'building_assertions', 'building_revisions', 'accommodation_unit_space', 'accommodation_units', 'space_beds'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['floors', 'spaces'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique(['uuid']);
                $t->dropIndex(['archived_at']);
                $t->dropColumn(['uuid', 'archived_at']);
                if ($table === 'floors') {
                    $t->dropColumn('kind');
                }
            });
        }
        Schema::table('buildings', function (Blueprint $t) {
            $t->dropUnique(['uuid']);
            $t->dropUnique(['reference']);
            $t->dropColumn(['uuid', 'reference', 'display_name', 'classification', 'custom_classification', 'address', 'country', 'timezone', 'latitude', 'longitude', 'construction_year', 'state', 'declaration_version', 'verification_state']);
        });
    }
};
