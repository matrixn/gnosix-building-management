<?php
namespace Tests\Feature;

use App\Models\{Organization, User, WorkspaceDraft, Property, Space};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceStudioTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(string $role = 'owner'): array
    {
        $u = User::factory()->create(['locale'=>'en', 'onboarding_completed_at'=>now(), 'profile_type'=>'manager']);
        $o = Organization::create(['name'=>'Atlas '.$u->id]);
        $o->profile()->create(['profile_key'=>'hospitality','organization_kind'=>'company','schema_version'=>1]);
        $o->memberships()->create(['user_id'=>$u->id,'role'=>$role]);
        return [$u,$o,'/app/organizations/'.$o->id];
    }
    private function login(User $u): static
    {
        return $this->actingAs($u,'web')->withSession(['customer_auth_version'=>$u->auth_version,'customer_locale'=>'en']);
    }
    private function property(Organization $o, string $name='Sample'): Property
    {
        return $o->properties()->create(['name'=>$name,'property_type'=>'hotel','country'=>'RO','timezone'=>'Europe/Bucharest']);
    }
    public static function scenarios(): array
    {
        return [
            'cabin'=>['cabin',[],1,1,0,0,0],
            'guesthouse'=>['guesthouse',[],1,1,1,5,0],
            'hostel'=>['hostel',[],1,1,1,2,12],
            'hotel'=>['hotel',[],1,1,4,40,0],
            'resort'=>['resort',['shared_facilities'=>['pool','garden']],1,3,3,32,0],
            'chain'=>['group',['group_name'=>'Atlas Hotels','legal_entity_name'=>'Atlas SRL'],3,3,12,120,0],
        ];
    }
    #[DataProvider('scenarios')]
    public function test_six_scenarios_materialize_and_render(string $scenario,array $extra,int $properties,int $buildings,int $floors,int $spaces,int $beds): void
    {
        [$u,$o,$url]=$this->fixture();
        $this->login($u)->get($url.'/studio')->assertOk()->assertSee('Workspace Studio');
        $payload=['version'=>1,'step'=>'review','guided'=>true,'answers'=>['scenario'=>$scenario,'property_name'=>'Meridian']+$extra];
        $this->postJson($url.'/studio/commit',$payload)->assertOk();
        $this->assertDatabaseCount('properties',$properties); $this->assertDatabaseCount('buildings',$buildings);
        $this->assertDatabaseCount('floors',$floors); $this->assertDatabaseCount('spaces',$spaces);
        $this->assertEquals($beds,Space::sum('bed_count'));
        $this->postJson($url.'/studio/commit',$payload)->assertOk();
        $this->assertDatabaseCount('properties',$properties);
        $this->get($url.'/studio')->assertOk();
        $this->get($url.'/properties')->assertOk()->assertSee('Meridian');
        $this->get($url.'/properties/'.Property::first()->id)->assertOk()->assertSee('data-space-building', false)->assertSee('data-space-floor', false)->assertDontSee('@include', false);
        $this->get($url.'/spaces')->assertOk();
        $this->get($url.'/workspace-settings')->assertOk();
        if ($scenario==='group') {
            $this->assertDatabaseCount('property_group',3); $this->assertDatabaseCount('legal_entities',1);
            $this->assertSame(1,Property::distinct()->count('legal_entity_id'));
        }
    }
    public function test_drafts_resume_preserve_hidden_answers_and_reject_stale_writes(): void
    {
        [$u,$o,$url]=$this->fixture(); $this->login($u)->get($url.'/studio')->assertOk();
        $base=['version'=>1,'step'=>'structure','guided'=>false,'answers'=>['scenario'=>'hotel','property_name'=>'Retained','room_count'=>73,'injected'=>'ignored']];
        $this->postJson($url.'/studio/draft',$base)->assertOk()->assertJsonPath('version',2);
        $this->get($url.'/studio')->assertOk()->assertSee('Retained');
        $this->postJson($url.'/studio/draft',$base)->assertUnprocessable()->assertJsonValidationErrors('draft');
        $this->postJson($url.'/studio/draft',['version'=>2,'step'=>'vision','guided'=>true,'answers'=>['scenario'=>'cabin']])->assertOk();
        $this->assertSame(73,WorkspaceDraft::sole()->state['room_count']);
        $this->assertArrayNotHasKey('injected',WorkspaceDraft::sole()->state);
        $this->postJson($url.'/studio/commit',['version'=>3,'step'=>'review','guided'=>true,'answers'=>[]])->assertOk();
        $this->assertDatabaseCount('spaces',0); $this->assertDatabaseCount('properties',1);
        $this->post($url.'/studio/new')->assertRedirect();
        $this->assertNull(WorkspaceDraft::sole()->completed_at); $this->assertDatabaseCount('properties',1);
    }
    public function test_validation_and_batch_limits_are_atomic(): void
    {
        [$u,$o,$url]=$this->fixture(); $this->login($u)->get($url.'/studio');
        $base=['version'=>1,'step'=>'review','guided'=>true];
        $this->postJson($url.'/studio/commit',$base+['answers'=>['scenario'=>'group']])->assertUnprocessable()->assertJsonValidationErrors(['answers.property_name','answers.group_name']);
        $this->postJson($url.'/studio/commit',$base+['answers'=>['scenario'=>'group','property_name'=>'Huge','group_name'=>'Big','property_count'=>50,'room_count'=>500]])->assertUnprocessable()->assertJsonValidationErrors('answers.room_count');
        $this->postJson($url.'/studio/draft',$base+['answers'=>['room_count'=>-1]])->assertUnprocessable();
        $this->postJson($url.'/studio/draft',$base+['answers'=>['property_name'=>['bad']]])->assertUnprocessable();
        $this->assertDatabaseCount('properties',0); $this->assertSame(1,WorkspaceDraft::sole()->version);
    }
    public function test_roles_and_capabilities_are_enforced_server_side(): void
    {
        [$u,$o,$url]=$this->fixture('member'); $this->login($u)->get($url.'/studio')->assertForbidden();
        $this->get($url.'/workspace-settings')->assertForbidden();
        $o->memberships()->update(['role'=>'admin']); $this->get($url.'/studio')->assertOk();
        $o->capabilityOverrides()->create(['capability'=>'hospitality.workspace','enabled'=>false]);
        $this->get($url.'/studio')->assertForbidden();
        $p=$this->property($o); $o->capabilityOverrides()->create(['capability'=>'hospitality.rooms','enabled'=>false]);
        $this->post($url.'/properties/'.$p->id.'/spaces',['name'=>'X','space_type'=>'room'])->assertForbidden();
        $this->get($url.'/spaces')->assertForbidden();
    }
    public function test_property_grants_filter_lists_and_protect_writes_and_revocation(): void
    {
        [$owner,$o,$url]=$this->fixture(); [$member]=$this->fixture();
        $m=$o->memberships()->create(['user_id'=>$member->id,'role'=>'admin','access_scope'=>'properties']);
        $a=$this->property($o,'Allowed'); $b=$this->property($o,'Hidden');
        $m->propertyAccesses()->create(['property_id'=>$a->id,'permission'=>'viewer']);
        $this->login($member)->get($url.'/properties')->assertOk()->assertSee('Allowed')->assertDontSee('Hidden');
        $this->get($url.'/properties/'.$b->id)->assertForbidden();
        $this->patch($url.'/properties/'.$a->id,['name'=>'Changed','property_type'=>'hotel'])->assertForbidden();
        $m->propertyAccesses()->update(['permission'=>'editor']);
        $this->patch($url.'/properties/'.$a->id,['name'=>'Changed','property_type'=>'apartment'])->assertRedirect();
        $this->patch($url.'/properties/'.$a->id,['name'=>'Changed','property_type'=>'hotel','group_ids'=>[]])->assertForbidden();
        $this->get($url.'/studio')->assertForbidden();
        $this->login($owner)->put($url.'/members/'.$m->id.'/property-access',['access_scope'=>'properties','permission'=>'viewer','property_ids'=>[]])->assertRedirect();
        $this->login($member)->get($url.'/properties/'.$a->id)->assertForbidden();
    }
    public function test_foreign_ids_and_inconsistent_hierarchy_are_rejected(): void
    {
        [$u,$o,$url]=$this->fixture(); [$other,$foreign,$foreignUrl]=$this->fixture();
        $a=$this->property($o); $b=$this->property($foreign);
        $foreignBuilding=$b->buildings()->create(['name'=>'Foreign']);
        $ownBuilding=$a->buildings()->create(['name'=>'Own']);
        $floor=$ownBuilding->floors()->create(['name'=>'Ground','position'=>0]);
        $entity=$foreign->legalEntities()->create(['name'=>'Foreign legal']);
        $this->login($u)->get($foreignUrl.'/properties')->assertForbidden();
        $this->get($url.'/properties/'.$b->id)->assertNotFound();
        $this->patch($url.'/properties/'.$a->id,['name'=>'X','property_type'=>'hotel','legal_entity_id'=>$entity->id])->assertSessionHasErrors('legal_entity_id');
        $this->post($url.'/properties/'.$a->id.'/spaces',['name'=>'Bad','space_type'=>'room','building_id'=>$foreignBuilding->id])->assertSessionHasErrors('building_id');
        $this->post($url.'/properties/'.$a->id.'/spaces',['name'=>'Bad','space_type'=>'room','floor_id'=>$floor->id])->assertSessionHasErrors('floor_id');
        $this->patch($url.'/properties/'.$a->id.'/buildings/'.$foreignBuilding->id,['name'=>'Bad'])->assertNotFound();
        $this->assertDatabaseCount('spaces',0);
    }
    public function test_optional_hierarchy_and_unit_preferences(): void
    {
        [$u,$o,$url]=$this->fixture(); $o->update(['unit_system'=>'imperial']); $p=$this->property($o);
        $this->login($u)->post($url.'/properties/'.$p->id.'/spaces',['name'=>'Garden','space_type'=>'common','area'=>100])->assertRedirect();
        $s=Space::sole(); $this->assertNull($s->building_id); $this->assertNull($s->floor_id);
        $this->assertEquals(9.29,$s->area_m2);
        $this->get($url.'/properties/'.$p->id.'/spaces/'.$s->id.'/edit')->assertOk();
    }
    public function test_organization_preferences_logo_privacy_and_group_management(): void
    {
        Storage::fake('local'); [$u,$o,$url]=$this->fixture(); [$other]=$this->fixture();
        $data=['name'=>'Atlas','legal_name'=>'Atlas SRL','country'=>'ro','timezone'=>'Europe/Bucharest','locale'=>'ro','unit_system'=>'metric','contact_email'=>'team@example.test','contact_phone'=>'+40722123456'];
        $this->login($u)->patch($url.'/workspace-settings',$data+['logo'=>UploadedFile::fake()->image('logo.png',100,100)])->assertRedirect();
        $o->refresh(); Storage::disk('local')->assertExists($o->logo_path);
        $this->get($url.'/logo')->assertOk(); $this->assertSame('RO',$o->country);
        $this->login($other)->get($url.'/logo')->assertForbidden();
        $this->login($u)->patch($url.'/workspace-settings',array_replace($data,['timezone'=>'Invalid','contact_phone'=>'abc']))->assertSessionHasErrors(['timezone','contact_phone']);
        $this->post($url.'/groups',['name'=>'Brand','kind'=>'brand'])->assertRedirect();
        $this->post($url.'/legal-entities',['name'=>'Second SRL','country'=>'RO'])->assertRedirect();
        $this->assertDatabaseCount('workspace_groups',1); $this->assertDatabaseCount('legal_entities',1);
        $path=$o->logo_path;
        $this->patch($url.'/workspace-settings',$data+['remove_logo'=>1])->assertRedirect();
        Storage::disk('local')->assertMissing($path); $this->assertNull($o->fresh()->logo_path);
    }

    public function test_partial_space_update_cannot_leave_a_floor_in_the_wrong_building(): void
    {
        [$u,$o,$url]=$this->fixture(); $p=$this->property($o);
        $a=$p->buildings()->create(['name'=>'A']); $b=$p->buildings()->create(['name'=>'B']);
        $floor=$a->floors()->create(['name'=>'Ground','position'=>0]);
        $space=$p->spaces()->create(['name'=>'101','space_type'=>'room','building_id'=>$a->id,'floor_id'=>$floor->id,'area_m2'=>20]);
        $path=$url.'/properties/'.$p->id.'/spaces/'.$space->id;
        $this->login($u)->patch($path,['name'=>'101','space_type'=>'room','building_id'=>$b->id])->assertSessionHasErrors('floor_id');
        $this->assertSame($a->id,$space->fresh()->building_id);
        $this->patch($path,['name'=>'Renamed','space_type'=>'room'])->assertRedirect();
        $this->assertEquals(20,$space->fresh()->area_m2);
        $this->patch($path,['name'=>'101','space_type'=>'room','building_id'=>$b->id,'floor_id'=>null])->assertRedirect();
        $this->assertSame($b->id,$space->fresh()->building_id); $this->assertNull($space->fresh()->floor_id);
    }
    public function test_owner_cannot_grant_foreign_property_and_admin_cannot_change_membership_scope(): void
    {
        [$u,$o,$url]=$this->fixture(); [$foreignOwner,$foreign]=$this->fixture(); [$admin]=$this->fixture();
        $m=$o->memberships()->create(['user_id'=>$admin->id,'role'=>'admin']); $p=$this->property($foreign);
        $data=['access_scope'=>'properties','permission'=>'editor','property_ids'=>[$p->id]];
        $this->login($u)->putJson($url.'/members/'.$m->id.'/property-access',$data)->assertUnprocessable()->assertJsonValidationErrors('property_ids.0');
        $this->login($admin)->putJson($url.'/members/'.$m->id.'/property-access',$data)->assertForbidden();
        $this->assertDatabaseCount('property_accesses',0);
    }
    public function test_drafts_are_separate_for_each_organization_and_user_and_room_opt_out_blocks_commit(): void
    {
        [$u,$o,$url]=$this->fixture(); [$v]=$this->fixture();
        $o->memberships()->create(['user_id'=>$v->id,'role'=>'admin']);
        $this->login($u)->get($url.'/studio');
        $this->postJson($url.'/studio/draft',['version'=>1,'step'=>'structure','guided'=>true,'answers'=>['property_name'=>'Owner draft','scenario'=>'hotel']])->assertOk();
        $this->login($v)->get($url.'/studio')->assertOk()->assertDontSee('Owner draft');
        $this->assertDatabaseCount('workspace_drafts',2);
        $o->capabilityOverrides()->create(['capability'=>'hospitality.rooms','enabled'=>false]);
        $this->login($u)->postJson($url.'/studio/commit',['version'=>2,'step'=>'review','guided'=>true,'answers'=>[]])->assertUnprocessable()->assertJsonValidationErrors('answers.room_count');
        $this->assertDatabaseCount('properties',0);
        $this->postJson($url.'/studio/commit',['version'=>2,'step'=>'review','guided'=>true,'answers'=>['scenario'=>'cabin']])->assertOk();
        $this->assertDatabaseCount('properties',1);
    }
    public function test_svg_logos_are_rejected_and_studio_mutations_require_csrf(): void
    {
        Storage::fake('local'); [$u,$o,$url]=$this->fixture(); $this->login($u)->get($url.'/studio');
        $this->patchJson($url.'/workspace-settings',['name'=>'Atlas','country'=>'RO','timezone'=>'Europe/Bucharest','locale'=>'ro','unit_system'=>'metric','logo'=>UploadedFile::fake()->createWithContent('logo.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->assertNull($o->fresh()->logo_path);
        $this->app->detectEnvironment(fn ()=>'local');
        $this->postJson($url.'/studio/commit',['version'=>1,'step'=>'review','guided'=>true,'answers'=>['property_name'=>'Cabin']])->assertStatus(419);
        $this->app->detectEnvironment(fn ()=>'testing'); $this->assertDatabaseCount('properties',0);
    }
}
