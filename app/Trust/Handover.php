<?php

namespace App\Trust;

use App\Models\Building;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Handover
{
    public function __construct(private Passport $passport) {}

    public function history(string $id)
    {
        return DB::table('handover_events')->where('handover_id', $id)->latest('id');
    }

    private function event(string $id, User $actor, string $action, ?string $message = null, ?string $recipient = null): void
    {
        DB::table('handover_events')->insert(['handover_id' => $id, 'actor_id' => $actor->id, 'recipient_reference' => $recipient, 'action' => $action, 'message' => $message, 'created_at' => now()]);
    }

    public function locate(Organization $org, Building $building, string $id): object
    {
        return DB::table('building_handovers')->where('id', $id)->where('building_id', $building->id)
            ->where('source_organization_id', $org->id)->first() ?? abort(404);
    }

    private function lockBuilding(int $id, int $propertyId): Building
    {
        Property::whereKey($propertyId)->lockForUpdate()->firstOrFail();

        return Building::whereKey($id)->where('property_id', $propertyId)->where('state', '!=', 'archived')->lockForUpdate()->firstOrFail();
    }

    public function draft(User $actor, Organization $org, Property $property, Building $building): string
    {
        return DB::transaction(function () use ($actor, $org, $property, $building) {
            $building = $this->lockBuilding($building->id, $property->id);
            $this->passport->authorize($actor, $org, $property, $building, true);
            $id = (string) Str::uuid();
            DB::table('building_handovers')->insert([
                'id' => $id, 'building_id' => $building->id, 'source_organization_id' => $org->id,
                'created_by' => $actor->id, 'state' => 'draft', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($id, $actor, 'draft');

            return $id;
        }, 3);
    }

    public function sourceAction(User $actor, Organization $org, Property $property, Building $building, string $id, string $action, array $input): ?string
    {
        return DB::transaction(function () use ($actor, $org, $property, $building, $id, $action, $input) {
            $building = $this->lockBuilding($building->id, $property->id);
            $allowed = $this->passport->authorize($actor, $org, $property, $building, true);
            $flow = $this->locate($org, $building, $id);
            $this->revision($flow, $input);

            return match ($action) {
                'prepare' => $this->prepare($actor, $building, $org, $flow, $allowed, $input),
                'invite' => $this->invite($actor, $flow, $building, $allowed, $input),
                'reply' => $this->reply($actor, $flow, $input),
                'complete' => $this->complete($actor, $building, $flow, $allowed, $input),
                'cancel' => $this->cancel($actor, $flow, $input),
                'revoke' => $this->revoke($actor, $flow, $input),
                default => abort(404),
            };
        }, 3);
    }

    private function revision(object $flow, array $input): void
    {
        $revision = Validator::make($input, ['revision' => 'required|integer|min:1'])->validate()['revision'];
        abort_unless((int) $flow->revision === (int) $revision, 409, __('trust.stale'));
    }

    private function advance(object $flow, string $state, array $fields = []): void
    {
        $changed = DB::table('building_handovers')->where('id', $flow->id)->where('revision', $flow->revision)
            ->update($fields + ['state' => $state, 'revision' => $flow->revision + 1, 'updated_at' => now()]);
        abort_unless($changed === 1, 409);
    }

    private function prepare(User $actor, Building $building, Organization $org, object $flow, array $allowed, array $input): ?string
    {
        abort_unless(in_array($flow->state, ['draft', 'prepared'], true), 409);
        $data = Validator::make($input, [
            'sections' => 'nullable|array|max:5', 'sections.*' => ['distinct', Rule::in(Passport::SECTIONS)],
            'versions' => 'nullable|array|max:40', 'versions.*' => 'uuid|distinct',
            'checks' => 'required|array|size:4', 'checks.*' => ['distinct', Rule::in(Passport::CHECKS)],
            'authority' => 'accepted', 'authority_basis' => 'required|string|min:8|max:1500',
            'responsibilities' => 'nullable|string|max:2000', 'recipient_org' => 'nullable|integer|min:1',
            'access_days' => 'required|integer|between:0,365',
        ])->validate();
        $sections = $data['sections'] ?? [];
        $versions = $data['versions'] ?? [];
        foreach ($sections as $section) {
            abort_unless($allowed[$section] ?? false, 403);
        }
        abort_if($versions !== [] && ! in_array('evidence', $sections, true), 422);
        $this->validateVersions($building, $versions);
        $recipient = $data['recipient_org'] ?? null;
        if ($recipient !== null) {
            abort_unless(Organization::whereKey($recipient)->exists(), 422);
        }
        $selection = [
            'sections' => $sections, 'versions' => $versions, 'checks' => $data['checks'],
            'responsibilities' => $data['responsibilities'] ?? null,
        ];
        $snapshot = $this->passport->build($building, $org, $allowed, $selection) + [
            'handover' => ['id' => $flow->id, 'checks' => $selection['checks'], 'responsibilities' => $selection['responsibilities'], 'access_days' => (int) $data['access_days']],
        ];
        $this->advance($flow, 'prepared', [
            'selection' => json_encode($selection, JSON_THROW_ON_ERROR), 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'authority_basis' => $data['authority_basis'], 'recipient_organization_id' => $recipient, 'access_days' => $data['access_days'],
        ]);
        $this->event($flow->id, $actor, 'prepared');

        return null;
    }

    private function validateVersions(Building $building, array $ids): void
    {
        $versions = $this->passport->documents($building)->whereIn('id', $ids)->get(['id', 'path']);
        abort_unless($versions->count() === count($ids) && $versions->every(fn ($v) => Storage::disk('evidence')->exists($v->path)), 422, __('trust.versions_unavailable'));
    }

    private function invite(User $actor, object $flow, Building $building, array $allowed, array $input): string
    {
        abort_unless(in_array($flow->state, ['prepared', 'invited'], true), 409);
        $selection = json_decode($flow->selection, true, flags: JSON_THROW_ON_ERROR);
        abort_if(array_diff($selection['sections'], array_keys(array_filter($allowed))), 403);
        $this->validateVersions($building, $selection['versions']);
        $data = Validator::make($input, ['email' => 'required|email:rfc|max:254', 'days' => 'required|integer|between:1,14'])->validate();
        $email = strtolower($data['email']);
        $list = DB::table('handover_recipients')->where('handover_id', $flow->id);
        abort_if((clone $list)->count() >= 20 || (clone $list)->where('email', $email)->exists(), 422);
        $token = bin2hex(random_bytes(32));
        $recipient = (string) Str::uuid();
        DB::table('handover_recipients')->insert([
            'id' => $recipient, 'handover_id' => $flow->id, 'email' => $email,
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays((int) $data['days']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->advance($flow, 'invited');
        $this->event($flow->id, $actor, 'invited', recipient: $recipient);

        return $token;
    }

    private function reply(User $actor, object $flow, array $input): ?string
    {
        abort_unless(in_array($flow->state, ['under_review', 'accepted'], true), 409);
        $data = Validator::make($input, ['message' => 'required|string|max:2000', 'question' => 'required|integer|min:1'])->validate();
        $question = $this->history($flow->id)->where('id', $data['question'])->where('action', 'question')->first() ?? abort(404);
        DB::table('handover_events')->insert([
            'handover_id' => $flow->id, 'actor_id' => $actor->id, 'action' => 'reply',
            'reply_to' => $question->id, 'recipient_reference' => $question->recipient_reference, 'message' => $data['message'], 'created_at' => now(),
        ]);

        return null;
    }

    private function validReviewer(User $user, object $flow, object $recipient): void
    {
        abort_unless(! $user->isArchived() && $user->hasVerifiedEmail() && strtolower($user->email) === $recipient->email && ! $recipient->revoked_at, 404);
        if ($flow->recipient_organization_id) {
            $org = Organization::find($flow->recipient_organization_id);
            abort_unless($org && Gate::forUser($user)->allows('view', $org), 404);
        }
    }

    private function visibleBuilding(object $flow): Building
    {
        return Building::whereKey($flow->building_id)->where('state', '!=', 'archived')
            ->whereHas('property', fn ($p) => $p->where('organization_id', $flow->source_organization_id))->firstOrFail();
    }

    public function redeem(User $user, string $token): string
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);

        return DB::transaction(function () use ($user, $token) {
            $invite = DB::table('handover_recipients')->where('token_hash', hash('sha256', $token))->first() ?? abort(404);
            $flow = DB::table('building_handovers')->where('id', $invite->handover_id)->first() ?? abort(404);
            $building = $this->visibleBuilding($flow);
            $this->lockBuilding($building->id, $building->property_id);
            $flow = DB::table('building_handovers')->where('id', $flow->id)->lockForUpdate()->first() ?? abort(404);
            $invite = DB::table('handover_recipients')->where('id', $invite->id)->lockForUpdate()->first() ?? abort(404);
            $this->validReviewer($user, $flow, $invite);
            abort_unless(in_array($flow->state, ['invited', 'under_review'], true) && ! $invite->redeemed_at && ! $invite->user_id && now()->lt($invite->expires_at), 404);
            $claimed = DB::table('handover_recipients')->where('id', $invite->id)->whereNull('redeemed_at')->whereNull('revoked_at')
                ->update(['user_id' => $user->id, 'redeemed_at' => now(), 'updated_at' => now()]);
            abort_unless($claimed === 1, 404);
            $this->advance($flow, 'under_review');
            $this->event($flow->id, $user, 'under_review', recipient: $invite->id);

            return $flow->id;
        }, 3);
    }

    public function access(User $user, string $id): array
    {
        $flow = DB::table('building_handovers')->where('id', $id)->first() ?? abort(404);
        $recipient = DB::table('handover_recipients')->where('handover_id', $id)->where('user_id', $user->id)->first() ?? abort(404);
        $this->validReviewer($user, $flow, $recipient);
        $building = $this->visibleBuilding($flow);
        if ($flow->state === 'completed') {
            abort_unless(DB::table('building_stewardships')->where('recipient_id', $recipient->id)->where('user_id', $user->id)
                ->where('building_id', $building->id)->where('scope', 'package_read')->whereNull('revoked_at')
                ->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists(), 404);
        } else {
            abort_unless($recipient->redeemed_at && now()->lt($recipient->expires_at) && in_array($flow->state, ['under_review', 'accepted'], true), 404);
        }

        return [$flow, $recipient, $building];
    }

    public function decide(User $user, string $id, string $action, array $input): void
    {
        [, , $building] = $this->access($user, $id);
        DB::transaction(function () use ($user, $id, $action, $input, $building) {
            $this->lockBuilding($building->id, $building->property_id);
            [$flow, $recipient] = $this->access($user, $id);
            $this->revision($flow, $input);
            abort_unless($flow->state === 'under_review' && ! $recipient->decision, 409);
            $data = Validator::make($input, [
                'message' => [$action === 'accept' ? 'nullable' : 'required', 'string', 'max:2000'],
                'consent' => $action === 'accept' ? 'accepted' : 'nullable',
            ])->validate();
            if ($action === 'question') {
                $this->event($id, $user, 'question', $data['message'], $recipient->id);

                return;
            }
            abort_unless(in_array($action, ['accept', 'return'], true), 404);
            $updated = DB::table('handover_recipients')->where('id', $recipient->id)->whereNull('decision')->update([
                'decision' => $action === 'accept' ? 'accepted' : 'returned', 'decided_at' => now(), 'updated_at' => now(),
            ]);
            abort_unless($updated === 1, 409);
            $state = $action === 'return' ? 'returned' : $this->consensus($id);
            $this->advance($flow, $state);
            $this->event($id, $user, $action === 'return' ? 'returned' : 'accepted', $data['message'] ?? null, $recipient->id);
        }, 3);
    }

    private function consensus(string $id): string
    {
        $active = DB::table('handover_recipients')->where('handover_id', $id)->whereNull('revoked_at');
        $pending = (clone $active)->where(fn ($q) => $q->whereNull('decision')->orWhere('decision', '!=', 'accepted')->orWhere('expires_at', '<=', now()))->exists();

        return ! $pending && $active->exists() ? 'accepted' : 'under_review';
    }

    private function complete(User $actor, Building $building, object $flow, array $allowed, array $input): ?string
    {
        abort_unless($flow->state === 'accepted' && $this->consensus($flow->id) === 'accepted', 409);
        Validator::make($input, ['confirm' => 'accepted'])->validate();
        $selection = json_decode($flow->selection, true, flags: JSON_THROW_ON_ERROR);
        foreach ($selection['sections'] as $section) {
            abort_unless($allowed[$section] ?? false, 403);
        }
        $this->validateVersions($building, $selection['versions']);
        $recipients = DB::table('handover_recipients')->where('handover_id', $flow->id)->whereNull('revoked_at')->get();
        $users = User::whereIn('id', $recipients->pluck('user_id'))->get()->keyBy('id');
        foreach ($recipients as $recipient) {
            $user = $users->get($recipient->user_id) ?? abort(409);
            $this->validReviewer($user, $flow, $recipient);
            if ($flow->access_days > 0) {
                DB::table('building_stewardships')->insert([
                    'recipient_id' => $recipient->id, 'building_id' => $building->id, 'user_id' => $recipient->user_id,
                    'scope' => 'package_read', 'starts_at' => now(), 'ends_at' => now()->addDays((int) $flow->access_days),
                ]);
            }
        }
        $this->advance($flow, 'completed', ['completed_at' => now()]);
        $this->event($flow->id, $actor, 'completed');

        return null;
    }

    private function cancel(User $actor, object $flow, array $input): ?string
    {
        abort_if(in_array($flow->state, ['completed', 'cancelled', 'returned'], true), 409);
        $data = Validator::make($input, ['message' => 'required|string|max:2000'])->validate();
        DB::table('handover_recipients')->where('handover_id', $flow->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->advance($flow, 'cancelled');
        $this->event($flow->id, $actor, 'cancelled', $data['message']);

        return null;
    }

    private function revoke(User $actor, object $flow, array $input): ?string
    {
        abort_unless(in_array($flow->state, ['invited', 'under_review', 'accepted', 'completed'], true), 409);
        $data = Validator::make($input, ['recipient' => 'required|uuid', 'message' => 'required|string|max:2000'])->validate();
        $query = DB::table('handover_recipients')->where('handover_id', $flow->id)->where('id', $data['recipient'])->whereNull('revoked_at');
        $recipient = (clone $query)->first() ?? abort(404);
        $query->update(['revoked_at' => now()]);
        DB::table('building_stewardships')->where('recipient_id', $recipient->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->advance($flow, in_array($flow->state, ['under_review', 'accepted'], true) ? $this->consensus($flow->id) : $flow->state);
        $this->event($flow->id, $actor, 'revoked', $data['message'], $recipient->id);

        return null;
    }

    public function packet(object $flow, Building $building): array
    {
        $packet = json_decode($flow->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $ids = array_column($packet['sections']['documents']['items'] ?? [], 'id');
        $current = $this->passport->documents($building)->whereIn('id', $ids)->get(['id', 'path'])
            ->filter(fn ($version) => Storage::disk('evidence')->exists($version->path))->pluck('id')->all();
        if (isset($packet['sections']['documents'])) {
            $packet['sections']['documents']['items'] = array_values(array_filter($packet['sections']['documents']['items'], fn ($row) => in_array($row['id'], $current, true)));
            $packet['sections']['documents']['unavailable'] = count($ids) - count($current);
        }

        return $packet;
    }
}
