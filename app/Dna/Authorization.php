<?php

namespace App\Dna;

use App\Models\Property;
use App\Models\User;
use App\Support\CapabilityService;
use Illuminate\Support\Facades\Gate;

class Authorization
{
    public function check(User $u, Property $p, string $ability = 'view'): void
    {
        Gate::forUser($u)->authorize($ability === 'view' ? 'view' : 'update', $p);
        $c = app(CapabilityService::class);
        abort_unless($c->allows($u, $p->organization, 'dna.view') && $c->allows($u, $p->organization, 'hospitality.rooms'), 403);
        abort_unless($c->allows($u, $p->organization, 'dna.'.$ability) && $c->allows($u, $p->organization, 'assets.view'), 403);
        if ($ability !== 'view') {
            abort_unless($c->allows($u, $p->organization, 'assets.manage'), 403);
        }
    }
}
