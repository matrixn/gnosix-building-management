<?php

namespace App\Evidence;

use App\Models\Building;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Retention
{
    public function purge(Building $b): void
    {
        foreach (DB::table('evidence_versions')->where('building_id', $b->id)->get(['path']) as $v) {
            DB::table('evidence_file_deletions')->insertOrIgnore(['path' => $v->path, 'created_at' => now()]);
        }
        DB::table('evidence_references')->whereIn('assertion_id', DB::table('evidence_assertions')->where('building_id', $b->id)->select('id'))->delete();
        foreach (['evidence_conflicts', 'evidence_reviews', 'evidence_assertions', 'evidence_extractions', 'evidence_versions', 'evidence_documents', 'evidence_sources', 'evidence_audits'] as $table) {
            DB::table($table)->where('building_id', $b->id)->delete();
        }
    }

    public function cleanup(): int
    {
        $count = 0;
        foreach (DB::table('evidence_file_deletions')->orderBy('id')->limit(500)->get() as $file) {
            if (! preg_match('~^(?:assets/)?\d+/[a-f0-9-]{36}/[a-f0-9-]{36}/original\.bin$~D', $file->path)) {
                continue;
            }
            if (! Storage::disk('evidence')->exists($file->path) || Storage::disk('evidence')->delete($file->path)) {
                DB::table('evidence_file_deletions')->where('id', $file->id)->delete();
                $count++;
            }
        }

        return $count;
    }
}
