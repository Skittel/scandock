<?php

namespace App\Libraries;

use App\Models\ScanModel;

/**
 * Normalises file names delivered via FTP, so no manipulated name (control
 * characters, markup, path tricks, very long names) reaches the database,
 * the web interface, the journal or downloads.
 *
 * Rules: German umlauts and ß are transliterated (ä → ae, ß → ss); every
 * other character than a-z, A-Z, 0-9 becomes "_"; the name (without
 * extension) has at most 30 characters – longer names are cut to 26
 * characters plus a 4-digit counter starting at 0000; the extension is kept
 * (sanitised the same way) and may have at most 4 characters.
 */
class ScanFileName
{
    public const MAX_BASE    = 30;
    public const CUT_BASE    = 26;
    public const MAX_EXT     = 4;
    private const FALLBACK   = 'scan';

    private const TRANSLITERATION = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss', 'ẞ' => 'SS',
    ];

    /**
     * Splits and sanitises a name. "ext" keeps its full (sanitised) length,
     * so callers can reject extensions longer than MAX_EXT.
     *
     * @return array{base: string, ext: string}
     */
    public static function split(string $name): array
    {
        $dot  = strrpos($name, '.');
        $base = $dot === false || $dot === 0 ? $name : substr($name, 0, $dot);
        $ext  = $dot === false || $dot === 0 ? '' : substr($name, $dot + 1);

        return ['base' => self::clean($base) ?: self::FALLBACK, 'ext' => self::clean($ext)];
    }

    /**
     * Final name for a received scan of an FTP account (adds the counter if
     * the name has to be shortened).
     */
    public static function forScan(string $name, int $ftpAccountId): string
    {
        ['base' => $base, 'ext' => $ext] = self::split($name);
        $suffix = $ext === '' ? '' : '.' . substr($ext, 0, self::MAX_EXT);
        if (strlen($base) <= self::MAX_BASE) {
            return $base . $suffix;
        }

        $prefix = substr($base, 0, self::CUT_BASE);

        return $prefix . sprintf('%04d', self::nextCounter($prefix, $suffix, $ftpAccountId)) . $suffix;
    }

    /**
     * Shortened name for display only (rejected files, uploads in progress):
     * same rules, the counter is shown as "0000".
     */
    public static function forDisplay(string $name): string
    {
        ['base' => $base, 'ext' => $ext] = self::split($name);
        if (strlen($base) > self::MAX_BASE) {
            $base = substr($base, 0, self::CUT_BASE) . '0000';
        }

        return $base . ($ext === '' ? '' : '.' . substr($ext, 0, self::MAX_EXT));
    }

    private static function clean(string $value): string
    {
        // Umlauts in both composed and decomposed form (e.g. from macOS).
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }
        $value = strtr($value, self::TRANSLITERATION);

        // One underscore per remaining character (also for multi-byte characters).
        return (string) preg_replace('/[^a-zA-Z0-9]/u', '_', $value);
    }

    private static function nextCounter(string $prefix, string $suffix, int $ftpAccountId): int
    {
        $names = model(ScanModel::class)->withDeleted()
            ->select('original_name')
            ->where('ftp_account_id', $ftpAccountId)
            ->like('original_name', $prefix, 'after')
            ->findColumn('original_name') ?? [];

        $max = -1;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{4})' . preg_quote($suffix, '/') . '$/';
        foreach ($names as $existing) {
            if (preg_match($pattern, $existing, $m) === 1) {
                $max = max($max, (int) $m[1]);
            }
        }

        return ($max + 1) % 10000;
    }
}
