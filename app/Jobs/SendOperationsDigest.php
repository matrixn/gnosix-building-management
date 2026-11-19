<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\OperationsDigest;
use App\Operations\Metrics;
use App\Operations\Scope;
use App\Operations\Window;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SendOperationsDigest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $organization, public int $user, public string $week)
    {
        $this->onQueue('maintenance');
    }

    public function handle(Metrics $metrics): void
    {
        $user = User::find($this->user);
        $organization = Organization::find($this->organization);
        $currentWeek = CarbonImmutable::now('Europe/Bucharest')->startOfWeek()->toDateString();
        if (! $user || ! $organization || $this->week !== $currentWeek) {
            return;
        }
        try {
            $scope = new Scope($user, $organization, []);
        } catch (AuthorizationException|HttpException) {
            return;
        }
        if (! $scope->capabilities['maintenance'] || ! $scope->properties(true)->exists()) {
            return;
        }

        $claimed = DB::table('operations_preferences')->where(['organization_id' => $organization->id, 'user_id' => $user->id, 'weekly_digest' => true])
            ->where(fn ($q) => $q->whereNull('last_digest_week')->orWhere('last_digest_week', '<', $this->week))
            ->update(['last_digest_week' => $this->week]);
        if (! $claimed) {
            return;
        }
        $window = new Window([], $organization->timezone);
        $counts = $metrics->counts(array_intersect_key($metrics->queries($scope, $window), array_flip(['critical', 'unassigned', 'overdue', 'upcoming'])));
        $user->notify(new OperationsDigest($organization, $counts));
    }
}
