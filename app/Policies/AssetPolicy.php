<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\Property;
use App\Models\User;
use App\Support\CapabilityService;
use Illuminate\Support\Facades\Gate;

class AssetPolicy
{
    public function create(User $u, Property $p): bool
    {
        return Gate::forUser($u)->allows('update', $p) && app(CapabilityService::class)->allows($u, $p->organization, 'assets.manage') && app(CapabilityService::class)->allows($u, $p->organization, 'assets.view');
    }

    public function view(User $u, Asset $a): bool
    {
        return $a->organization_id === $a->property->organization_id && (! $a->building_id || $a->building()->exists()) && Gate::forUser($u)->allows('view', $a->property) && app(CapabilityService::class)->allows($u, $a->property->organization, 'assets.view');
    }

    public function update(User $u, Asset $a): bool
    {
        return $this->view($u, $a) && $this->create($u, $a->property);
    }

    public function review(User $u, Asset $a): bool
    {
        return $this->view($u, $a) && Gate::forUser($u)->allows('update', $a->property) && app(CapabilityService::class)->allows($u, $a->property->organization, 'assets.review') && app(CapabilityService::class)->allows($u, $a->property->organization, 'evidence.review');
    }
}
