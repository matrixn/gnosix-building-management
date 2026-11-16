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
            if (! Schema::hasColumn('buildings', 'uuid')) {
                $t->uuid('uuid')->nullable()->unique();
            }
            if (! Schema::hasColumn('buildings', 'reference')) {
                $t->string('reference', 40)->nullable()->unique();
            }
            if (! Schema::hasColumn('buildings', 'display_name')) {
                $t->string('display_name', 150)->nullable();
            }
            if (! Schema::hasColumn('buildings', 'classification')) {
                $t->string('classification', 40)->nullable();
            }
            if (! Schema::hasColumn('buildings', 'custom_classification')) {
                $t->string('custom_classification', 100)->nullable();
            }
            if (! Schema::hasColumn('buildings', 'address')) {
                $t->string('address', 500)->nullable();
            }
            if (! Schema::hasColumn('buildings', 'country')) {
                $t->string('country', 2)->nullable();
            }
            if (! Schema::hasColumn('buildings', 'timezone')) {
                $t->string('timezone', 64)->nullable();
            }
            if (! Schema::hasColumn('buildings', 'latitude')) {
                $t->decimal('latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('buildings', 'longitude')) {
                $t->decimal('longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('buildings', 'construction_year')) {
                $t->unsignedSmallInteger('construction_year')->nullable();
            }
            if (! Schema::hasColumn('buildings', 'state')) {
                $t->string('state', 20)->default('draft');
            }
            if (! Schema::hasColumn('buildings', 'declaration_version')) {
                $t->unsignedInteger('declaration_version')->default(1);
            }
            if (! Schema::hasColumn('buildings', 'verification_state')) {
                $t->string('verification_state', 30)->default('unverified');
            }
        });
        foreach (['floors', 'spaces'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'uuid')) {
                    $t->uuid('uuid')->nullable()->unique();
                }
                if (! Schema::hasColumn($table, 'archived_at')) {
                    $t->timestamp('archived_at')->nullable()->index();
                }
                if ($table === 'floors') {
                    if (! Schema::hasColumn($table, 'kind')) {
                        $t->string('kind', 20)->nullable();
                    }
                }
            });
        }
        foreach (['buildings', 'floors', 'spaces'] as $table) {
            DB::table($table)->where(function ($q) use ($table) {
                $q->whereNull('uuid');
                if ($table === 'buildings') {
                    $q->orWhereNull('reference');
                }
            })->orderBy('id')->chunkById(250, function ($rows) use ($table) {
                $batch = [];
                foreach ($rows as $r) {
                    $uuid = $r->uuid ?? (string) Str::uuid();
                    // Include existing required columns for portable upserts. Only identity
                    // columns are updated; names, timestamps and physical facts stay intact.
                    $data = (array) $r;
                    $data['uuid'] = $uuid;
                    if ($table === 'buildings') {
                        $data['reference'] = $r->reference ?? 'GNX-'.implode('-', str_split(strtoupper(substr(str_replace('-', '', $uuid), 0, 16)), 4));
                    }
                    $batch[] = $data;
                }
                DB::table($table)->upsert($batch, ['id'], $table === 'buildings' ? ['uuid', 'reference'] : ['uuid']);
            });
        }
        $this->createIfMissing('space_beds', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('space_id')->constrained()->restrictOnDelete();
            $t->string('label', 80);
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        $this->createIfMissing('accommodation_units', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->string('name', 150);
            $t->string('unit_type', 30);
            $t->foreignId('bed_id')->nullable()->constrained('space_beds')->restrictOnDelete();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        $this->createIfMissing('accommodation_unit_space', function (Blueprint $t) {
            $t->foreignId('accommodation_unit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('space_id')->constrained()->restrictOnDelete();
            $t->primary(['accommodation_unit_id', 'space_id']);
        });
        $this->createIfMissing('building_revisions', function (Blueprint $t) {
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
        $this->createIfMissing('building_assertions', function (Blueprint $t) {
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
        $this->createIfMissing('building_declaration_drafts', function (Blueprint $t) {
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
            $t->index(['property_id', 'user_id', 'building_id'], 'bld_draft_property_user_building_idx');
        });
        $this->repairSchemaObjects();

        DB::table('buildings')->whereNotExists(function ($q) {
            $q->selectRaw('1')->from('building_revisions')->whereColumn('building_revisions.building_id', 'buildings.id');
        })->orderBy('id')->chunkById(250, function ($rows) {
            $batch = [];
            foreach ($rows as $r) {
                $batch[] = ['building_id' => $r->id, 'action' => 'identity_assigned', 'source_kind' => 'legacy', 'entity_type' => 'building', 'entity_reference' => $r->uuid, 'after_data' => json_encode(['name' => $r->name, 'uuid' => $r->uuid, 'reference' => $r->reference]), 'created_at' => now()];
            }
            DB::table('building_revisions')->insert($batch);
        });
    }

    private function createIfMissing(string $table, Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    private function repairSchemaObjects(): void
    {
        // CREATE TABLE can succeed before a later ALTER INDEX/FOREIGN KEY fails on MySQL.
        $indexes = [
            ['buildings', ['uuid'], 'buildings_uuid_unique', 'unique'],
            ['buildings', ['reference'], 'buildings_reference_unique', 'unique'],
            ['floors', ['uuid'], 'floors_uuid_unique', 'unique'],
            ['floors', ['archived_at'], 'floors_archived_at_index', 'index'],
            ['spaces', ['uuid'], 'spaces_uuid_unique', 'unique'],
            ['spaces', ['archived_at'], 'spaces_archived_at_index', 'index'],
            ['space_beds', ['uuid'], 'space_beds_uuid_unique', 'unique'],
            ['accommodation_units', ['uuid'], 'accommodation_units_uuid_unique', 'unique'],
            ['accommodation_unit_space', ['accommodation_unit_id', 'space_id'], 'unit_space_primary', 'primary'],
            ['building_revisions', ['building_id', 'id'], 'building_revisions_building_id_id_index', 'index'],
            ['building_assertions', ['building_id', 'field_key'], 'building_assertions_building_id_field_key_index', 'index'],
            ['building_declaration_drafts', ['id'], 'building_drafts_primary', 'primary'],
            ['building_declaration_drafts', ['property_id', 'user_id', 'building_id'], 'bld_draft_property_user_building_idx', 'index'],
        ];
        foreach ($indexes as [$table, $columns, $name, $type]) {
            if (! Schema::hasIndex($table, $columns, $type === 'index' ? null : $type)) {
                Schema::table($table, fn (Blueprint $t) => $t->{$type}($columns, $name));
            }
        }
        $foreignKeys = [
            ['space_beds', 'space_id', 'spaces', 'restrict'],
            ['accommodation_units', 'building_id', 'buildings', 'restrict'],
            ['accommodation_units', 'bed_id', 'space_beds', 'restrict'],
            ['accommodation_unit_space', 'accommodation_unit_id', 'accommodation_units', 'cascade'],
            ['accommodation_unit_space', 'space_id', 'spaces', 'restrict'],
            ['building_revisions', 'building_id', 'buildings', 'restrict'],
            ['building_revisions', 'actor_id', 'users', 'set null'],
            ['building_assertions', 'building_id', 'buildings', 'restrict'],
            ['building_assertions', 'revision_id', 'building_revisions', 'restrict'],
            ['building_declaration_drafts', 'property_id', 'properties', 'cascade'],
            ['building_declaration_drafts', 'user_id', 'users', 'cascade'],
            ['building_declaration_drafts', 'building_id', 'buildings', 'restrict'],
        ];
        foreach ($foreignKeys as [$table, $column, $parent, $onDelete]) {
            if (! Schema::hasForeignKey($table, [$column])) {
                Schema::table($table, fn (Blueprint $t) => $t->foreign($column, $table.'_'.$column.'_foreign')->references('id')->on($parent)->onDelete($onDelete));
            }
        }
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
