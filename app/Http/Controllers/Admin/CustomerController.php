<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\CustomerIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate(['q' => 'nullable|string|max:120', 'state' => 'nullable|in:all,active,pending,archived']);
        $search = $input['q'] ?? '';
        $state = $input['state'] ?? 'all';
        $customers = User::query()->withCount('memberships');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $customers->where(fn ($q) => $q->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(email) LIKE LOWER(?) ESCAPE '!'", [$pattern]));
        }
        match ($state) {
            'archived' => $customers->whereNotNull('archived_at'),
            'active' => $customers->whereNull('archived_at')->whereNotNull('email_verified_at'),
            'pending' => $customers->whereNull('archived_at')->whereNull('email_verified_at'),
            default => null,
        };
        $customers = $customers->latest('created_at')->latest('id')->paginate(25)->withQueryString();

        return view('admin.customers.index', compact('customers', 'search', 'state'));
    }

    public function show(User $customer)
    {
        $memberships = $customer->memberships()->with('organization.profile')->orderBy('organization_id')->paginate(20);
        $preference = UserPreference::where('user_id', $customer->id)->first();
        $visible = DB::table('properties')->whereExists(function ($query) use ($customer) {
            $query->selectRaw('1')->from('organization_memberships as membership')
                ->whereColumn('membership.organization_id', 'properties.organization_id')
                ->where('membership.user_id', $customer->id)
                ->whereIn('membership.role', OrganizationMembership::ROLES)
                ->where(fn ($access) => $access->where('membership.role', 'owner')->orWhere('membership.access_scope', 'all')
                    ->orWhere(fn ($restricted) => $restricted->where('membership.access_scope', 'properties')
                        ->whereExists(fn ($grant) => $grant->selectRaw('1')->from('property_accesses as grant')
                            ->whereColumn('grant.membership_id', 'membership.id')
                            ->whereColumn('grant.property_id', 'properties.id')->whereIn('grant.permission', ['editor', 'viewer']))));
        })->select('properties.id', 'properties.organization_id');

        $metrics = [];
        $counts = [
            'properties' => [null, null],
            'buildings' => ['buildings', fn ($q) => $q->whereNull('item.deleted_at')->where('item.state', '!=', 'archived')],
            'spaces' => ['spaces', fn ($q) => $q->whereNull('item.archived_at')->where(fn ($b) => $b->whereNull('item.building_id')->orWhereExists(fn ($t) => $t->selectRaw('1')->from('buildings')->whereColumn('buildings.id', 'item.building_id')->whereNull('deleted_at')->where('state', '!=', 'archived')))],
            'assets' => ['assets', fn ($q) => $q->whereNull('item.archived_at')],
            'work_orders' => ['work_orders', null],
        ];
        foreach ($counts as $key => [$table, $filter]) {
            $query = DB::query()->fromSub(clone $visible, 'scope');
            if ($table !== null) {
                $query->join($table.' as item', 'item.property_id', '=', 'scope.id');
            }
            if ($filter !== null) {
                $filter($query);
            }
            $metrics[$key] = $query->groupBy('scope.organization_id')->selectRaw('scope.organization_id, COUNT(*) as total')->pluck('total', 'organization_id');
        }
        $created = DB::query()->fromSub(clone $visible, 'scope')->join('buildings', 'buildings.property_id', '=', 'scope.id')
            ->join('building_revisions as history', 'history.building_id', '=', 'buildings.id')->where('history.actor_id', $customer->id)
            ->whereIn('history.action', ['created', 'declared'])->whereIn('history.entity_type', ['building', 'declaration'])
            ->whereNull('buildings.deleted_at')->where('buildings.state', '!=', 'archived')
            ->groupBy('scope.organization_id')->selectRaw('scope.organization_id, COUNT(DISTINCT buildings.id) as total')->pluck('total', 'organization_id');
        $metrics['created_buildings'] = $created;
        $history = DB::table('customer_admin_events')->where('user_id', $customer->id)
            ->leftJoin('platform_admins', 'platform_admins.id', '=', 'customer_admin_events.platform_admin_id')
            ->latest('customer_admin_events.id')->limit(12)->get(['action', 'customer_admin_events.created_at', 'platform_admins.name as administrator']);

        return view('admin.customers.show', compact('customer', 'memberships', 'preference', 'metrics', 'history'));
    }

    public function create()
    {
        return view('admin.customers.edit', ['customer' => new User]);
    }

    public function edit(User $customer)
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function store(Request $request)
    {
        $data = $this->validateAccount($request);
        $customer = DB::transaction(function () use ($request, $data) {
            $customer = new User(Arr::except($data, ['locale']));
            $customer->locale = $data['locale'];
            $customer->save();
            $this->log($request, $customer, 'created', array_keys($data));

            return $customer;
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Contul de client a fost creat.');
    }

    public function update(Request $request, User $customer)
    {
        DB::transaction(function () use ($request, $customer) {
            $account = User::lockForUpdate()->findOrFail($customer->id);
            $data = $this->validateAccount($request, $account);
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $oldEmail = $account->email;
            $account->fill(Arr::except($data, ['locale']));
            $account->locale = $data['locale'];
            $fields = array_keys($account->getDirty());
            if ($account->isDirty(['email', 'password'])) {
                $this->invalidate($account, $oldEmail);
            }
            if ($account->isDirty('email')) {
                $account->email_verified_at = null;
            }
            if ($fields !== []) {
                $account->save();
                $this->log($request, $account, 'updated', $fields);
            }
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Modificările au fost salvate.');
    }

    public function archive(Request $request, User $customer)
    {
        $request->validate(['confirm_archive' => 'accepted']);

        return $this->setArchive($request, $customer, true);
    }

    public function restore(Request $request, User $customer)
    {
        return $this->setArchive($request, $customer, false);
    }

    private function setArchive(Request $request, User $customer, bool $archive)
    {
        DB::transaction(function () use ($request, $customer, $archive) {
            $account = User::lockForUpdate()->findOrFail($customer->id);
            if ($account->isArchived() === $archive) {
                return;
            }
            $account->archived_at = $archive ? now() : null;
            $this->invalidate($account, $account->email);
            $account->save();
            $this->log($request, $account, $archive ? 'archived' : 'restored', ['archived_at']);
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', $archive ? 'Cont arhivat. Accesul a fost oprit.' : 'Accesul la cont a fost restabilit.');
    }

    private function validateAccount(Request $request, ?User $current = null): array
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower($request->input('email'))]);
        }
        $password = CustomerIdentity::passwordRules();
        if ($current) {
            $password[0] = 'nullable';
        }

        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users')->ignore($current?->id)],
            'locale' => ['required', Rule::in(['ro', 'en'])],
            'password' => $password,
        ]);
    }

    private function invalidate(User $account, string $oldEmail): void
    {
        Password::broker('users')->getRepository()->delete($account);
        if ($account->email !== $oldEmail) {
            Password::broker('users')->getRepository()->delete((new User)->forceFill(['email' => $oldEmail]));
        }
        $account->auth_version++;
        $account->remember_token = Str::random(60);
    }

    private function log(Request $request, User $account, string $action, array $fields): void
    {
        DB::table('customer_admin_events')->insert([
            'user_id' => $account->id,
            'platform_admin_id' => $request->user('platform_admin')->id,
            'action' => $action,
            'fields' => json_encode($fields, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
