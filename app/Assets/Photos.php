<?php

namespace App\Assets;

use App\Evidence\Facts;
use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Photos
{
    public function upload(User $u, Asset $asset, array $files, int $revision, ?string $caption): void
    {
        if (count($files) < 1 || count($files) > 3) {
            throw ValidationException::withMessages(['photos' => __('asset.photo_invalid')]);
        }$prepared = [];
        foreach ($files as $f) {
            if (! $f instanceof UploadedFile || ! $f->isValid() || $f->getSize() > 5 * 1048576) {
                throw ValidationException::withMessages(['photos' => __('asset.photo_invalid')]);
            }$bytes = file_get_contents($f->getRealPath());
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            $size = @getimagesizefromstring($bytes);
            if (! in_array($mime, ['image/png', 'image/jpeg'], true) || ! $size || $size['mime'] !== $mime || $size[0] * $size[1] > 24000000) {
                throw ValidationException::withMessages(['photos' => __('asset.photo_invalid')]);
            }$prepared[] = ['bytes' => $bytes, 'mime' => $mime];
        }
        $written = [];
        try {
            DB::transaction(function () use ($u, $asset, $prepared, $revision, $caption, &$written) {
                $r = app(Registry::class);
                $a = $r->lock($asset->property, $asset);
                $r->writable($u, $a);
                $r->revision($a, $revision);
                if ($a->photos()->count() + count($prepared) > 20) {
                    throw ValidationException::withMessages(['photos' => __('asset.photo_limit')]);
                }foreach ($prepared as $file) {
                    $uuid = (string) Str::uuid();
                    $path = 'assets/'.$a->organization_id.'/'.$a->uuid.'/'.$uuid.'/original.bin';
                    $written[] = $path;
                    Storage::disk('evidence')->put($path, $file['bytes']);
                    $photo = AssetPhoto::create(['uuid' => $uuid, 'asset_id' => $a->id, 'path' => $path, 'mime' => $file['mime'], 'sha256' => hash('sha256', $file['bytes']), 'caption' => Facts::clean($caption, 160), 'uploaded_by' => $u->id, 'created_at' => now()]);
                    $r->audit($a, $u, 'photo_added', null, ['photo_uuid' => $photo->uuid, 'sha256' => $photo->sha256]);
                }$a->increment('revision');
            });
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                try {
                    Storage::disk('evidence')->delete($path);
                } catch (\Throwable) {
                    DB::table('evidence_file_deletions')->insertOrIgnore(['path' => $path, 'created_at' => now()]);
                }
            }throw $e;
        }
    }
}
