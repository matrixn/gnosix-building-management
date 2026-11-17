<?php

namespace App\Dna;

use App\Models\DnaRelationType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Types
{
    public function all(Property $p): array
    {
        $types = config('dna.types');
        foreach (DnaRelationType::where('organization_id', $p->organization_id)->get() as $t) {
            $types['custom_'.$t->uuid] = $t->semantics + ['name' => $t->name, 'custom_id' => $t->id];
        }
        foreach ($types as $k => &$v) {
            $v += ['adapter' => false];
            $v['label'] = $v['name'] ?? __('dna.type_'.$k);
        }

        return $types;
    }

    public function create(User $u, Property $p, array $input): DnaRelationType
    {
        return DB::transaction(function () use ($u, $p, $input) {
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            app(Authorization::class)->check($u, $p, 'manage');
            Gate::forUser($u)->authorize('update', $p->organization);
            $kinds = ['property', 'building', 'floor', 'space', 'asset', 'system'];
            $d = Validator::make($input, ['name' => 'required|string|max:100', 'source' => 'required|array|min:1|max:6', 'source.*' => Rule::in($kinds), 'target' => 'required|array|min:1|max:6', 'target.*' => Rule::in($kinds), 'category' => 'required|in:connectivity,dependency,service', 'direction' => 'required|in:forward,reverse,both', 'impact' => 'required|boolean', 'cycles' => 'required|boolean', 'max_source' => 'required|integer|between:0,100', 'max_target' => 'required|integer|between:0,100'])->validate();
            abort_if($d['impact'] && ($d['category'] === 'connectivity' || $d['direction'] === 'both' || $d['cycles']), 422, __('dna.invalid_semantics'));
            abort_if(DnaRelationType::where('organization_id', $p->organization_id)->count() >= 100, 422);
            foreach (['impact', 'cycles'] as $flag) {
                $d[$flag] = (bool) $d[$flag];
            }foreach (['max_source', 'max_target'] as $limit) {
                $d[$limit] = (int) $d[$limit];
            }
            $type = DnaRelationType::create(['uuid' => (string) Str::uuid(), 'organization_id' => $p->organization_id, 'name' => $d['name'], 'semantics' => array_diff_key($d, ['name' => 1]), 'created_by' => $u->id]);
            app(Relations::class)->audit($u, $p, 'type_created', $type->uuid, null, $type->attributesToArray());

            return $type;
        });
    }
}
