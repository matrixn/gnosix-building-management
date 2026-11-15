<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\WorkspaceQuestions;
use App\Support\WorkspaceStudio;
use Illuminate\Http\Request;

class WorkspaceStudioController extends Controller
{
    public function show(Request $request, Organization $organization, WorkspaceStudio $studio, WorkspaceQuestions $questions)
    {
        $draft = $studio->resume($request->user('web'), $organization);
        $old = old('answers', []);
        $old = is_array($old) ? array_filter($old, fn ($value, $key) => is_scalar($value) || $value === null || (($questions->schema()['fields'][$key]['type'] ?? '') === 'checks' && is_array($value)), ARRAY_FILTER_USE_BOTH) : [];
        $state = array_replace($draft->state, $old);
        return view('studio.index', ['organization' => $organization, 'draft' => $draft, 'schema' => $questions->schema(), 'resolved' => $questions->resolve($state), 'questions' => $questions, 'state' => $state]);
    }

    public function save(Request $request, Organization $organization, WorkspaceStudio $studio, WorkspaceQuestions $questions)
    {
        $studio->authorize($request->user('web'), $organization);
        $data = $questions->validateInput($request->all());
        $draft = $studio->save($request->user('web'), $organization, $data, $request->routeIs('app.studio.commit'));
        if ($request->expectsJson()) return response()->json(['version' => $draft->version, 'plan' => $questions->plan($questions->resolve($draft->state)), 'redirect' => $draft->completed_at ? route('app.studio', $organization) : null]);
        return redirect()->route('app.studio', $organization)->with('status', __($draft->completed_at ? 'studio.created' : 'workspace.draft_saved'));
    }

    public function restart(Request $request, Organization $organization, WorkspaceStudio $studio)
    {
        $studio->restart($request->user('web'), $organization);
        return redirect()->route('app.studio', $organization);
    }
}
