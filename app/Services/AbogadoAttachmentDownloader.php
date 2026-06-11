<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AbogadoAttachmentDownloader
{
    public function download(string $url, string $kind, string $cedula): array
    {
        $response = $this->request($this->normalizeGoogleDriveUrl($url));
        if (!$response['ok']) {
            return $response;
        }

        $bytes = $response['bytes'];
        if ($this->looksLikeHtml($bytes)) {
            $confirmUrl = $this->resolveGoogleDriveConfirmUrl($bytes);
            if (!$confirmUrl) {
                return ['ok' => false, 'message' => 'Google Drive solicitó permisos o devolvió una página en lugar del archivo.'];
            }

            $response = $this->request($confirmUrl);
            if (!$response['ok']) {
                return $response;
            }
            $bytes = $response['bytes'];
        }

        $validation = $this->validateBytes($bytes, $kind);
        if (!$validation['ok']) {
            return $validation;
        }

        $directory = $kind === 'pdf' ? 'abogados/cc' : 'abogados/fotos';
        $safeCedula = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cedula) ?: 'abogado';
        $path = $directory . '/' . $safeCedula . '_' . Str::random(10) . '.' . $validation['extension'];

        if (!Storage::disk('public')->put($path, $bytes)) {
            return ['ok' => false, 'message' => 'No fue posible guardar el archivo en el servidor.'];
        }

        return ['ok' => true, 'path' => $path, 'message' => 'Archivo guardado correctamente.'];
    }

    public function storedFileIsValid(?string $path, string $kind): bool
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return false;
        }

        try {
            return $this->validateBytes(Storage::disk('public')->get($path), $kind)['ok'];
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function request(string $url): array
    {
        try {
            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 Testiapp'])
                ->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No fue posible conectar con el archivo de origen.'];
        }

        if (!$response->ok()) {
            return ['ok' => false, 'message' => 'El archivo respondió HTTP ' . $response->status() . '.'];
        }

        $bytes = (string) $response->body();
        if ($bytes === '') {
            return ['ok' => false, 'message' => 'El archivo descargado está vacío.'];
        }

        return ['ok' => true, 'bytes' => $bytes];
    }

    private function validateBytes(string $bytes, string $kind): array
    {
        if ($this->looksLikeHtml($bytes)) {
            return ['ok' => false, 'message' => 'Se recibió una página web en lugar del archivo.'];
        }

        if ($kind === 'pdf') {
            if (!str_starts_with(ltrim($bytes), '%PDF-')) {
                return ['ok' => false, 'message' => 'El contenido descargado no es un PDF válido.'];
            }
            return ['ok' => true, 'extension' => 'pdf'];
        }

        $image = @getimagesizefromstring($bytes);
        $mime = strtolower((string) ($image['mime'] ?? ''));
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!$image || !isset($extensions[$mime])) {
            return ['ok' => false, 'message' => 'El contenido descargado no es una imagen válida (JPG, PNG o WEBP).'];
        }

        return ['ok' => true, 'extension' => $extensions[$mime]];
    }

    private function normalizeGoogleDriveUrl(string $url): string
    {
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)
            || preg_match('~/d/([a-zA-Z0-9_-]+)~', $url, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }

        return $url;
    }

    private function looksLikeHtml(string $bytes): bool
    {
        $head = mb_strtolower(substr(trim($bytes), 0, 500));
        return str_contains($head, '<!doctype html')
            || str_contains($head, '<html')
            || str_contains($head, '<body');
    }

    private function resolveGoogleDriveConfirmUrl(string $html): ?string
    {
        if (preg_match('/href="(\/uc\?export=download[^"]+)"/i', $html, $matches)) {
            return 'https://drive.google.com' . html_entity_decode($matches[1]);
        }
        if (preg_match('/action="(https:\/\/drive\.google\.com\/uc\?export=download[^"]*)"/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        return null;
    }
}
