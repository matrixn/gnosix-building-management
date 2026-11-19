<?php

namespace App\Trust;

use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

class Exports
{
    public function download(array $passport, string $format)
    {
        $filename = 'GNOSIX-'.$passport['reference'];
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($format === 'json') {
            return response($this->json($passport), 200, $headers + [
                'Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="'.$filename.'.json"',
            ]);
        }

        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('trust.pdf', compact('passport'))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        if ($format === 'pdf') {
            return response($pdf->output(), 200, $headers + [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        $files = ['passport.pdf' => ['application/pdf', $pdf->output()], 'passport.json' => ['application/json', $this->json($passport)]];
        $manifest = [
            'schema' => 'gnosix.trust-manifest.v1', 'reference' => $passport['reference'],
            'captured_at' => $passport['captured_at'], 'algorithm' => 'sha256', 'files' => [],
        ];
        $path = tempnam(sys_get_temp_dir(), 'gnosix-passport-');
        abort_if($path === false, 500);
        try {
            $zip = new ZipArchive;
            abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500);
            foreach ($files as $name => [$type, $bytes]) {
                abort_unless($zip->addFromString($name, $bytes), 500);
                $manifest['files'][$name] = ['media_type' => $type, 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
            }
            abort_unless($zip->addFromString('manifest.json', $this->json($manifest)) && $zip->close(), 500);
        } catch (\Throwable $error) {
            unset($zip);
            @unlink($path);

            throw $error;
        }

        return response()->download($path, $filename.'.zip', $headers + ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
