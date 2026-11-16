<?php

namespace App\Field;

use App\Maintenance\Workflow;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\FieldInvitation;
use App\Models\Property;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Invitations
{
    public function audit(FieldInvitation $g, User $u, string $action): void
    {
        AssetAudit::create(['organization_id' => $g->organization_id, 'property_id' => $g->property_id, 'asset_id' => $g->asset_id, 'actor_id' => $u->id, 'entity_type' => 'field_invite', 'entity_uuid' => $g->uuid, 'action' => 'field.'.$action, 'after_data' => ['invitation_uuid' => $g->uuid], 'created_at' => now()]);
    }

    public function create(User $u, Property $p, array $input): array
    {
        return DB::transaction(function () use ($u, $p, $input) {
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            app(Workflow::class)->allowed($u, $p, true);
            Validator::make($input, ['work_ids' => 'nullable|array|max:30'])->validate();
            $input['work_ids'] = array_values(array_filter($input['work_ids'] ?? [], fn ($v) => $v !== null && $v !== ''));
            $d = Validator::make($input, ['email' => 'nullable|email:rfc|max:255', 'company_name' => 'nullable|string|max:160', 'expires_at' => 'required|date_format:Y-m-d\\TH:i', 'asset_id' => 'nullable|integer', 'work_ids' => 'array|max:30', 'work_ids.*' => 'integer|distinct', 'allow_costs' => 'required|boolean'])->validate();
            $expiry = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $d['expires_at'], $p->timezone)->utc();
            if (! $expiry->isFuture() || $expiry->gt(now()->addDays(app(Settings::class)->all()['invite_max_days']))) {
                throw ValidationException::withMessages(['expires_at' => __('field.expiry_invalid')]);
            }
            $asset = isset($d['asset_id']) ? Asset::where('property_id', $p->id)->available()->whereNull('archived_at')->findOrFail($d['asset_id']) : null;
            $works = WorkOrder::where('property_id', $p->id)->available()->whereNotIn('state', ['cancelled', 'closed'])->whereIn('id', $d['work_ids'])->get();
            if ($works->count() !== count($d['work_ids']) || (! $asset && $works->isEmpty())) {
                throw ValidationException::withMessages(['work_ids' => __('field.scope_required')]);
            }
            $token = bin2hex(random_bytes(32));
            $g = FieldInvitation::create(['uuid' => (string) Str::uuid(), 'organization_id' => $p->organization_id, 'property_id' => $p->id, 'asset_id' => $asset?->id, 'invited_by' => $u->id, 'token_hash' => hash('sha256', $token), 'email' => isset($d['email']) ? mb_strtolower($d['email']) : null, 'company_name' => $d['company_name'] ?? null, 'allow_costs' => $d['allow_costs'], 'expires_at' => $expiry]);
            $g->workOrders()->sync($works->modelKeys());
            $this->audit($g, $u, 'invited');

            return [$g, $token];
        });
    }

    public function preview(User $u, string $token): FieldInvitation
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token) && $u->hasVerifiedEmail(), 403, __('field.invalid_invitation'));
        $g = FieldInvitation::where('token_hash', hash('sha256', $token))->first();
        abort_unless($g && app(Access::class)->valid($g) && (! $g->email || hash_equals(mb_strtolower($g->email), mb_strtolower($u->email))) && (! $g->redeemed_by || $g->redeemed_by === $u->id), 403, __('field.invalid_invitation'));

        return $g;
    }

    public function redeem(User $u, string $token): FieldInvitation
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token) || ! $u->hasVerifiedEmail()) {
            abort(403, __('field.invalid_invitation'));
        }
        $found = FieldInvitation::where('token_hash', hash('sha256', $token))->first();
        abort_unless($found, 403, __('field.invalid_invitation'));

        return DB::transaction(function () use ($found, $u) {
            Property::whereKey($found->property_id)->lockForUpdate()->firstOrFail();
            $g = FieldInvitation::lockForUpdate()->findOrFail($found->id);
            abort_unless(app(Access::class)->valid($g) && (! $g->email || hash_equals(mb_strtolower($g->email), mb_strtolower($u->email))) && (! $g->redeemed_by || $g->redeemed_by === $u->id), 403, __('field.invalid_invitation'));
            if (! $g->redeemed_by) {
                $g->redeemed_by = $u->id;
                $g->redeemed_at = now();
                $g->save();
                $this->audit($g, $u, 'redeemed');
            }

            return $g;
        });
    }

    public function revoke(User $u, FieldInvitation $g): void
    {
        DB::transaction(function () use ($u, $g) {
            Property::whereKey($g->property_id)->lockForUpdate()->firstOrFail();
            app(Workflow::class)->allowed($u, $g->property, true);
            $g = FieldInvitation::lockForUpdate()->findOrFail($g->id);
            if (! $g->revoked_at) {
                $g->revoked_at = now();
                $g->save();
                $this->audit($g, $u, 'revoked');
            }
        });
    }
}
