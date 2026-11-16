<?php

namespace Tests\Feature;

use App\Models\BuildingDeclarationDraft;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Support\BuildingDeclarationSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuildingMigrationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function migration()
    {
        return require database_path('migrations/2026_11_16_160000_add_building_declarations.php');
    }

    private function property(): Property
    {
        $org = Organization::create(['name' => 'Recovery tenant']);

        return $org->properties()->create(['name' => 'Recovery property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
    }

    public function test_resume_after_forge_index_failure_preserves_identifiers_facts_drafts_and_history(): void
    {
        $p = $this->property();
        $u = User::factory()->create();
        $b = $p->buildings()->create(['name' => 'Existing hotel', 'construction_year' => 1999, 'state' => 'active']);
        $f = $b->floors()->create(['name' => 'Basement', 'position' => -1]);
        $s = $b->spaces()->create(['property_id' => $p->id, 'floor_id' => $f->id, 'name' => 'Plant room', 'space_type' => 'technical', 'area_m2' => 12.5]);
        $draft = BuildingDeclarationDraft::create(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'property_id' => $p->id, 'building_id' => $b->id, 'base_version' => $b->fresh()->declaration_version, 'version' => 7, 'stage' => 'structure', 'state' => app(BuildingDeclarationSchema::class)->empty()]);
        $before = ['building' => $b->fresh()->getAttributes(), 'floor' => $f->fresh()->getAttributes(), 'space' => $s->fresh()->getAttributes(), 'draft' => $draft->fresh()->getAttributes(), 'revisions' => DB::table('building_revisions')->orderBy('id')->get()->toJson(), 'assertions' => DB::table('building_assertions')->orderBy('id')->get()->toJson()];
        Schema::table('building_declaration_drafts', fn (Blueprint $t) => $t->dropIndex('bld_draft_property_user_building_idx'));
        $this->assertFalse(Schema::hasIndex('building_declaration_drafts', ['property_id', 'user_id', 'building_id']));
        $this->migration()->up();
        $this->migration()->up();
        $this->assertTrue(Schema::hasIndex('building_declaration_drafts', ['property_id', 'user_id', 'building_id']));
        $this->assertSame($before['building'], $b->fresh()->getAttributes());
        $this->assertSame($before['floor'], $f->fresh()->getAttributes());
        $this->assertSame($before['space'], $s->fresh()->getAttributes());
        $this->assertSame($before['draft'], $draft->fresh()->getAttributes());
        $this->assertSame($before['revisions'], DB::table('building_revisions')->orderBy('id')->get()->toJson());
        $this->assertSame($before['assertions'], DB::table('building_assertions')->orderBy('id')->get()->toJson());
    }

    public function test_resume_from_partial_columns_repairs_missing_schema_without_regenerating_existing_uuid(): void
    {
        $p = $this->property();
        $this->migration()->down();
        $id = DB::table('buildings')->insertGetId(['property_id' => $p->id, 'name' => 'Legacy building']);
        $fid = DB::table('floors')->insertGetId(['building_id' => $id, 'name' => 'Legacy lower level', 'position' => -1]);
        $sid = DB::table('spaces')->insertGetId(['property_id' => $p->id, 'building_id' => $id, 'floor_id' => $fid, 'name' => 'Existing store', 'space_type' => 'storage', 'area_m2' => 18.25]);
        Schema::table('buildings', function (Blueprint $t) {
            $t->uuid('uuid')->nullable();
            $t->string('reference', 40)->nullable();
        });
        $uuid = (string) Str::uuid();
        DB::table('buildings')->where('id', $id)->update(['uuid' => $uuid]);
        $this->migration()->up();
        $reference = DB::table('buildings')->where('id', $id)->value('reference');
        $spaceUuid = DB::table('spaces')->where('id', $sid)->value('uuid');
        $this->migration()->up();
        $this->assertSame($uuid, DB::table('buildings')->where('id', $id)->value('uuid'));
        $this->assertSame($reference, DB::table('buildings')->where('id', $id)->value('reference'));
        $this->assertSame($spaceUuid, DB::table('spaces')->where('id', $sid)->value('uuid'));
        $this->assertNull(DB::table('buildings')->where('id', $id)->value('construction_year'));
        $this->assertDatabaseHas('spaces', ['id' => $sid, 'name' => 'Existing store', 'area_m2' => 18.25]);
        $this->assertDatabaseCount('building_revisions', 1);
        $this->assertTrue(Schema::hasIndex('buildings', ['reference'], 'unique'));
    }

    public function test_index_names_fit_mysql_limits_and_draft_constraints_are_repaired(): void
    {
        Schema::drop('building_declaration_drafts');
        Schema::create('building_declaration_drafts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedBigInteger('property_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('building_id')->nullable();
            $t->unsignedInteger('base_version')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->json('state');
            $t->string('stage', 20)->default('identity');
            $t->string('source_kind', 20)->default('manual');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        $this->migration()->up();
        foreach (['property_id', 'user_id', 'building_id'] as $column) {
            $this->assertTrue(Schema::hasForeignKey('building_declaration_drafts', [$column]));
        }
        foreach (['buildings', 'floors', 'spaces', 'space_beds', 'accommodation_units', 'accommodation_unit_space', 'building_revisions', 'building_assertions', 'building_declaration_drafts'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen($index['name']), $table.': '.$index['name']);
            }
        }
    }
}
