<?php

namespace App\Console\Commands;

use App\Models\Abogado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateAbogadosAdjuntos extends Command
{
    protected $signature = 'abogados:migrar-adjuntos {--force : Reemplaza archivos existentes}';

    protected $description = 'Descarga PDF/foto de enlaces de origen y los guarda en storage local';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $total = 0;
        $okPdf = 0;
        $okFoto = 0;
        $fail = 0;

        Abogado::orderBy('id')->chunk(50, function ($abogados) use ($force, &$total, &$okPdf, &$okFoto, &$fail) {
            foreach ($abogados as $abogado) {
                $total++;
                $obs = (string) ($abogado->observacion ?? '');
                $pdfUrl = $this->extractLabeledUrl($obs, 'PDF origen:');
                $fotoUrl = $this->extractLabeledUrl($obs, 'FOTO origen:');

                if ($pdfUrl && ($force || empty($abogado->pdf_cc))) {
                    $saved = $this->downloadAndStore($pdfUrl, 'abogados/cc', $abogado->cc ?: (string) $abogado->id, 'pdf');
                    if ($saved) {
                        $abogado->pdf_cc = $saved;
                        $okPdf++;
                    } else {
                        $fail++;
                    }
                }

                if ($fotoUrl && ($force || empty($abogado->foto))) {
                    $saved = $this->downloadAndStore($fotoUrl, 'abogados/fotos', $abogado->cc ?: (string) $abogado->id, 'img');
                    if ($saved) {
                        $abogado->foto = $saved;
                        $okFoto++;
                    } else {
                        $fail++;
                    }
                }

                $abogado->save();
            }
        });

        $this->info("Registros procesados: {$total}");
        $this->info("PDF guardados: {$okPdf}");
        $this->info("Fotos guardadas: {$okFoto}");
        $this->info("Fallos: {$fail}");

        return self::SUCCESS;
    }

    private function extractLabeledUrl(string $text, string $label): ?string
    {
        $pos = stripos($text, $label);
        if ($pos === false) {
            return null;
        }
        $slice = trim(substr($text, $pos + strlen($label)));
        $slice = explode('|', $slice)[0] ?? $slice;
        $url = trim($slice);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function downloadAndStore(string $url, string $dir, string $baseName, string $kind): ?string
    {
        $downloadUrl = $this->normalizeGoogleDriveUrl($url);
        try {
            $resp = Http::timeout(60)->withHeaders([
                'User-Agent' => 'Mozilla/5.0',
            ])->get($downloadUrl);
        } catch (\Throwable $e) {
            $this->warn("Error descargando {$url}: {$e->getMessage()}");
            return null;
        }

        // Algunos enlaces de Drive devuelven HTML de confirmación; intentamos resolver descarga real.
        if ($resp->ok() && str_contains(strtolower((string) $resp->header('Content-Type')), 'text/html')) {
            $resolved = $this->resolveGoogleDriveConfirmUrl((string) $resp->body());
            if ($resolved) {
                try {
                    $resp = Http::timeout(60)->withHeaders([
                        'User-Agent' => 'Mozilla/5.0',
                    ])->get($resolved);
                } catch (\Throwable $e) {
                    $this->warn("Error en confirmación Drive {$url}: {$e->getMessage()}");
                    return null;
                }
            }
        }

        if (!$resp->ok()) {
            $this->warn("No se pudo descargar {$url} (HTTP {$resp->status()})");
            return null;
        }

        $bytes = $resp->body();
        if ($bytes === '') {
            $this->warn("Descarga vacia en {$url}");
            return null;
        }

        // Evita guardar HTML como .pdf/.jpg (causa archivos "en blanco").
        if ($this->looksLikeHtml($bytes)) {
            $this->warn("Contenido HTML recibido en lugar de archivo binario para {$url}");
            return null;
        }

        $mime = (string) $resp->header('Content-Type');
        $ext = $this->detectExtension($mime, $bytes, $kind);

        if ($kind === 'pdf' && !$this->looksLikePdf($bytes)) {
            $this->warn("El adjunto no parece PDF válido: {$url}");
            return null;
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $file = "{$dir}/{$safe}_" . Str::random(8) . ".{$ext}";

        Storage::disk('public')->put($file, $bytes);
        return $file;
    }

    private function normalizeGoogleDriveUrl(string $url): string
    {
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $m)) {
            return 'https://drive.google.com/uc?export=download&id=' . $m[1];
        }
        if (preg_match('~/d/([a-zA-Z0-9_-]+)~', $url, $m)) {
            return 'https://drive.google.com/uc?export=download&id=' . $m[1];
        }
        return $url;
    }

    private function detectExtension(string $mime, string $bytes, string $kind): string
    {
        $mime = strtolower($mime);
        if (str_contains($mime, 'pdf')) {
            return 'pdf';
        }
        if (str_contains($mime, 'png')) {
            return 'png';
        }
        if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
            return 'jpg';
        }
        if (str_contains($mime, 'webp')) {
            return 'webp';
        }

        if ($kind === 'pdf' || str_starts_with($bytes, '%PDF')) {
            return 'pdf';
        }
        if (str_starts_with($bytes, "\x89PNG")) {
            return 'png';
        }
        if (str_starts_with($bytes, "\xFF\xD8")) {
            return 'jpg';
        }
        return $kind === 'img' ? 'jpg' : 'pdf';
    }

    private function looksLikePdf(string $bytes): bool
    {
        return str_starts_with($bytes, '%PDF');
    }

    private function looksLikeHtml(string $bytes): bool
    {
        $head = mb_strtolower(substr(trim($bytes), 0, 300));
        return str_contains($head, '<!doctype html')
            || str_contains($head, '<html')
            || str_contains($head, '<body');
    }

    private function resolveGoogleDriveConfirmUrl(string $html): ?string
    {
        if (preg_match('/href="(\/uc\?export=download[^"]+)"/i', $html, $m)) {
            return 'https://drive.google.com' . str_replace('&amp;', '&', $m[1]);
        }
        if (preg_match('/action="(https:\/\/drive\.google\.com\/uc\?export=download[^"]*)"/i', $html, $m)) {
            return str_replace('&amp;', '&', $m[1]);
        }
        return null;
    }
}
