<?php

namespace App\Libraries;

use App\Models\TenantModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\ScanDock;

/**
 * Stores and serves the customer logo.
 */
class TenantBranding
{
    public const MAX_BYTES = 1048576;

    private const TYPES = [
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(private readonly ScanDock $config)
    {
    }

    /**
     * Validates and stores an uploaded logo, replacing the previous one.
     *
     * @return string|null error message, null on success
     */
    public function store(array $tenant, ?UploadedFile $file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return 'Bitte eine Bilddatei auswählen.';
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return 'Das Logo darf höchstens 1 MB groß sein.';
        }

        $content = file_get_contents($file->getTempName());
        $type    = $this->detectType($content);
        if ($type === null) {
            return 'Bitte ein Logo als PNG, JPG, WebP oder SVG hochladen.';
        }
        if ($type === 'image/svg+xml' && ! self::isSafeSvg($content)) {
            return 'Diese SVG-Datei enthält Skripte oder externe Inhalte und kann nicht verwendet werden.';
        }

        $dir = $this->directory((int) $tenant['id']);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $name = 'logo-' . bin2hex(random_bytes(8)) . '.' . self::TYPES[$type];
        file_put_contents($dir . '/' . $name, $content);

        $this->deleteFile($tenant);
        model(TenantModel::class)->update($tenant['id'], ['logo_file' => $name]);

        return null;
    }

    public function delete(array $tenant): void
    {
        $this->deleteFile($tenant);
        model(TenantModel::class)->update($tenant['id'], ['logo_file' => null]);
    }

    public function path(array $tenant): ?string
    {
        if (empty($tenant['logo_file'])) {
            return null;
        }
        $path = $this->directory((int) $tenant['id']) . '/' . basename($tenant['logo_file']);

        return is_file($path) ? $path : null;
    }

    public function mimeType(string $path): string
    {
        return array_search(pathinfo($path, PATHINFO_EXTENSION), self::TYPES, true) ?: 'application/octet-stream';
    }

    private function deleteFile(array $tenant): void
    {
        $path = $this->path($tenant);
        if ($path !== null) {
            @unlink($path);
        }
    }

    private function directory(int $tenantId): string
    {
        return rtrim($this->config->storagePath, '/') . '/' . $tenantId . '/branding';
    }

    private function detectType(string $content): ?string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        if (isset(self::TYPES[$mime]) && $mime !== 'image/svg+xml') {
            return $mime;
        }
        if (preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg[\s>]/is', $content) === 1) {
            return 'image/svg+xml';
        }

        return null;
    }

    /**
     * Rejects SVGs with scripts, event handlers or external references.
     * Logos are additionally served with a sandboxing CSP.
     */
    private static function isSafeSvg(string $content): bool
    {
        return preg_match('/<script|<foreignObject|\son[a-z]+\s*=|javascript:|<!ENTITY|xlink:href\s*=\s*["\'](?!#)|href\s*=\s*["\'](?!#)/i', $content) !== 1;
    }
}
