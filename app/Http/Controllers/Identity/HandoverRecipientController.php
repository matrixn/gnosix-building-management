<?php

namespace App\Http\Controllers\Identity;

use App\Evidence\Engine;
use App\Http\Controllers\Controller;
use App\Trust\Exports;
use App\Trust\Handover;
use App\Trust\Passport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HandoverRecipientController extends Controller
{
    public function __construct(private Handover $handover) {}

    public function index(Request $request)
    {
        $flows = DB::table('handover_recipients')->where('user_id', $request->user('web')->id)
            ->whereNull('revoked_at')->latest('redeemed_at')->simplePaginate(20, ['handover_id']);
        $packages = [];
        foreach ($flows as $row) {
            try {
                [$flow, , $building] = $this->handover->access($request->user('web'), $row->handover_id);
                $packages[] = [$flow, $building];
            } catch (NotFoundHttpException|ModelNotFoundException) {
                // An expired or withdrawn invitation reveals no building metadata.
            }
        }

        return view('trust.portal', compact('flows', 'packages'));
    }

    public function redeem(Request $request)
    {
        $token = $request->validate(['invitation_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']])['invitation_token'];
        $flow = $this->handover->redeem($request->user('web'), $token);

        return redirect()->route('handover.show', $flow);
    }

    public function show(Request $request, string $flow)
    {
        [$flow, $recipient, $building] = $this->handover->access($request->user('web'), $flow);
        $passport = $this->handover->packet($flow, $building);
        $questions = $this->handover->history($flow->id)->where('action', 'question')->where('actor_id', $request->user('web')->id)->limit(20)->get();

        $replies = $this->handover->history($flow->id)->where('action', 'reply')->whereIn('reply_to', $questions->pluck('id'))->limit(80)->get()->groupBy('reply_to');

        return view('trust.review', compact('flow', 'recipient', 'passport', 'questions', 'replies'));
    }

    public function action(Request $request, string $flow, string $action)
    {
        $this->handover->decide($request->user('web'), $flow, $action, $request->all());

        return redirect()->route($action === 'return' ? 'handover.portal' : 'handover.show', $action === 'return' ? [] : [$flow])
            ->with('status', __('trust.saved'));
    }

    public function export(Request $request, string $flow, string $format, Exports $exports)
    {
        [$record, , $building] = $this->handover->access($request->user('web'), $flow);

        return $exports->download($this->handover->packet($record, $building), $format);
    }

    public function document(Request $request, string $flow, string $version, Passport $passport)
    {
        [$flow, , $building] = $this->handover->access($request->user('web'), $flow);
        $selection = json_decode($flow->selection, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(in_array($version, $selection['versions'], true), 404);
        $file = $passport->documents($building)->findOrFail($version);
        $disk = Storage::disk('evidence');
        $bytes = $disk->get($file->path);
        abort_if($bytes === null, 404);
        abort_unless(hash_equals($file->sha256, hash('sha256', $bytes)), 409);

        $name = Engine::filename($file->original_name);
        $fallback = str_replace('%', '_', Engine::filename(Str::ascii($name)));

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $name, $fallback),
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
