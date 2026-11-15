<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use App\Support\PropertyPermissions;

class PropertyPolicy
{
    public function view(User $user, Property $property): bool { return app(PropertyPermissions::class)->can($user, $property); }
    public function update(User $user, Property $property): bool { return app(PropertyPermissions::class)->can($user, $property, true); }
}
