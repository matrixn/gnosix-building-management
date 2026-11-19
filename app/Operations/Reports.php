<?php

namespace App\Operations;

use App\Jobs\GenerateOperationalReport;
use App\Models\Organization;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Reports
{
    public const TYPES = ['weekly', 'monthly', 'repeated', 'asset_cost', 'preventive', 'quality'];

    public function recent(Scope $scope): Builder
    {
        return DB::table('operational_reports')->where('organization_id', $scope->organization->id)
            ->where('user_id', $scope->user->id)->where('expires_at', '>', now())->latest('created_at')->latest('id');
    }

    public function request(Scope $scope, string $type): string
    {
        $this->authorizeType($scope, $type);
        $hash = $scope->fingerprint();
        $id = DB::transaction(function () use ($scope, $type, $hash) {
            User::whereKey($scope->user->id)->lockForUpdate()->firstOrFail();
            if ($this->recent($scope)->whereIn('state', ['queued', 'processing'])->count() >= 3) {
                throw ValidationException::withMessages(['type' => __('ops.report_limit')]);
            }
            $id = (string) Str::uuid();
            DB::table('operational_reports')->insert([
                'id' => $id, 'organization_id' => $scope->organization->id, 'user_id' => $scope->user->id,
                'type' => $type, 'locale' => app()->getLocale(),
                'filters' => json_encode($scope->filters, JSON_THROW_ON_ERROR),
                'scope_hash' => $hash, 'state' => 'queued', 'expires_at' => now()->addDay(),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $id;
        });
        GenerateOperationalReport::dispatch($id)->afterCommit();

        return $id;
    }

    private function authorizeType(Scope $scope, string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        if ($type === 'quality') {
            abort_unless($scope->capabilities['evidence'], 403);

            return;
        }
        abort_unless($scope->capabilities['maintenance'], 403);
        if ($type === 'asset_cost') {
            abort_unless($scope->capabilities['assets'] && $scope->properties(true)->exists(), 403);
        }
        if ($type === 'repeated') {
            abort_unless($scope->capabilities['assets'], 403);
        }
    }

    private function currentScope(User $user, Organization $organization, object $report): Scope
    {
        $scope = new Scope($user, $organization, json_decode($report->filters, true, flags: JSON_THROW_ON_ERROR));
        $this->authorizeType($scope, $report->type);
        abort_unless(hash_equals($report->scope_hash, $scope->fingerprint()), 403);

        return $scope;
    }

    public function generate(string $id): void
    {
        $row = DB::table('operational_reports')->where('id', $id)->where('expires_at', '>', now());
        if (! (clone $row)->where('state', 'queued')->update(['state' => 'processing', 'updated_at' => now()])) {
            return;
        }
        $report = $row->first();
        if (! $report) {
            return;
        }
        $locale = app()->getLocale();
        try {
            app()->setLocale($report->locale);
            $scope = $this->currentScope(User::findOrFail($report->user_id), Organization::findOrFail($report->organization_id), $report);
            $window = new Window($scope->filters, $scope->organization->timezone);
            $engine = app(Metrics::class);
            $available = $engine->queries($scope, $window);
            $keys = match ($report->type) {
                'weekly' => ['open', 'critical', 'unassigned', 'overdue', 'upcoming', 'completed'],
                'monthly' => ['open', 'critical', 'unassigned', 'overdue', 'upcoming', 'completed', 'repeated', 'observed_spaces'],
                'repeated' => ['repeated', 'reopened', 'shared'],
                'asset_cost' => ['completed'],
                'preventive' => ['upcoming', 'preventive'],
                'quality' => ['documents', 'conflicts', 'warranty', 'disputed'],
            };
            $queries = array_intersect_key($available, array_flip($keys));
            if ($report->type === 'asset_cost') {
                $queries['completed'] = $queries['completed']->whereIn('property_id', $scope->properties(true)->select('properties.id'));
            }
            $counts = $engine->counts($queries);
            $records = [];
            foreach ($queries as $name => $query) {
                $records[$name] = (clone $query)->latest('id')->limit(8)->get()->map(fn ($model) => [
                    'name' => $model->title ?? $model->name ?? $model->original_name ?? $model->type_key ?? (string) $model->getKey(),
                    'reference' => $model->reference ?? $model->uuid ?? (string) $model->getKey(),
                ]);
            }
            $cost = in_array($report->type, ['monthly', 'asset_cost'], true) ? $engine->costs($scope, $window) : null;
            $assetCosts = $report->type === 'asset_cost' ? DB::query()->fromSub(clone $queries['completed'], 'work_orders')
                ->whereNotNull('work_orders.currency')->whereNotNull('work_orders.asset_id')
                ->whereIn('work_orders.asset_id', $scope->assets()->select('assets.id'))
                ->join('assets as cost_asset', 'cost_asset.id', '=', 'work_orders.asset_id')
                ->select('cost_asset.reference', 'work_orders.currency')
                ->selectRaw('SUM(work_orders.labor_cost) AS labor, SUM(work_orders.external_cost) AS external, SUM(work_orders.materials_cost) AS materials')
                ->groupBy('cost_asset.id', 'cost_asset.reference', 'work_orders.currency')->orderBy('cost_asset.id')->orderBy('work_orders.currency')->limit(31)->get() : collect();
            $preventive = $report->type === 'preventive' ? $engine->preventive($queries['preventive']) : null;
            $activity = in_array($report->type, ['weekly', 'monthly'], true) ? $engine->activity($scope, $window) : null;
            $prior = $activity !== null ? $engine->activity($scope, $window, true) : null;
            $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('operations.pdf', compact('scope', 'window', 'report', 'counts', 'records', 'cost', 'preventive', 'activity', 'prior', 'assetCosts'))->render(), 'UTF-8');
            $pdf->setPaper('A4');
            $pdf->render();
            if (! Storage::disk('operations')->put($id.'.pdf', $pdf->output())) {
                throw new \RuntimeException('Operational report write failed.');
            }
            if (! $row->where('state', 'processing')->update(['state' => 'ready', 'updated_at' => now()])) {
                Storage::disk('operations')->delete($id.'.pdf');
            }
        } finally {
            app()->setLocale($locale);
        }
    }

    public function download(User $user, Organization $organization, string $id)
    {
        $report = DB::table('operational_reports')->where('id', $id)->where('organization_id', $organization->id)
            ->where('user_id', $user->id)->where('state', 'ready')->where('expires_at', '>', now())->first();
        abort_unless($report, 404);
        $this->currentScope($user, $organization, $report);
        abort_unless(Storage::disk('operations')->exists($id.'.pdf'), 404);

        return Storage::disk('operations')->download($id.'.pdf', 'GNOSIX-'.$report->type.'-'.$id.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function cleanup(): void
    {
        $expired = DB::table('operational_reports')->where('expires_at', '<=', now())->orderBy('expires_at')->limit(200)->pluck('id');
        foreach ($expired as $id) {
            Storage::disk('operations')->delete($id.'.pdf');
            DB::table('operational_reports')->where('id', $id)->delete();
        }
    }
}
