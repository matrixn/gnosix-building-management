<?php

namespace App\Field;

use App\Models\FieldInvitation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\MaintenancePolicy;
use Illuminate\Database\Eloquent\Builder;

class Access
{
    public function internal(User $u, WorkOrder $w): bool
    {
        $p = app(MaintenancePolicy::class);

        return $p->report($u, $w->property) && ($p->manage($u, $w->property) || ($w->assigned_to === $u->id && $w->organization->memberships()->where('user_id', $u->id)->value('role') !== 'reporter'));
    }

    public function valid(FieldInvitation $g, ?User $u = null): bool
    {
        return ! $g->revoked_at && $g->expires_at->isFuture() && (! $u || ($u->hasVerifiedEmail() && $g->redeemed_by === $u->id)) && $g->inviter && app(MaintenancePolicy::class)->manage($g->inviter, $g->property) && (! $g->asset_id || ($g->asset && ! $g->asset->archived_at && (! $g->asset->building_id || $g->asset->building()->where('state', '!=', 'archived')->exists())));
    }

    public function grants(User $u): Builder
    {
        return FieldInvitation::where('redeemed_by', $u->id)->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function grant(User $u, WorkOrder $w): ?FieldInvitation
    {
        if (! $u->hasVerifiedEmail() || $w->state === 'cancelled' || ($w->building_id && ! $w->building()->where('state', '!=', 'archived')->exists())) {
            return null;
        }

        return $this->grants($u)->where('organization_id', $w->organization_id)->where('property_id', $w->property_id)->where(fn ($q) => $q->whereHas('workOrders', fn ($q) => $q->where('work_orders.id', $w->id))->when($w->asset_id, fn ($q) => $q->orWhere('asset_id', $w->asset_id)))->orderByDesc('allow_costs')->cursor()->first(fn ($g) => $this->valid($g, $u));
    }

    public function context(User $u, WorkOrder $w): ?array
    {
        if (! $u->hasVerifiedEmail() || $w->state === 'cancelled' || ($w->building_id && ! $w->building()->where('state', '!=', 'archived')->exists())) {
            return null;
        }
        if ($this->internal($u, $w)) {
            return ['external' => false, 'costs' => true, 'grant' => null];
        }
        $g = $this->grant($u, $w);

        return $g ? ['external' => true, 'costs' => $g->allow_costs, 'grant' => $g] : null;
    }

    public function authorize(User $u, WorkOrder $w, bool $write = false): array
    {
        $ctx = $this->context($u, $w);
        abort_unless($ctx, 403);
        if ($write) {
            abort_if(in_array($w->state, ['verified', 'closed', 'cancelled']), 403);
        }

        return $ctx;
    }

    public function candidates(User $u): Builder
    {
        return WorkOrder::available()->where('state', '!=', 'cancelled')->where(function ($q) use ($u) {
            $q->where('assigned_to', $u->id)->orWhereHas('fieldInvitations', fn ($g) => $g->where('redeemed_by', $u->id)->whereNull('revoked_at')->where('expires_at', '>', now()))->orWhereExists(function ($g) use ($u) {
                $g->selectRaw('1')->from('field_invitations')->whereColumn('field_invitations.asset_id', 'work_orders.asset_id')->whereColumn('field_invitations.property_id', 'work_orders.property_id')->where('redeemed_by', $u->id)->whereNull('revoked_at')->where('expires_at', '>', now());
            });
        });
    }
}
