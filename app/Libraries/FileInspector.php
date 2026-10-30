<?php

namespace App\Libraries;

/**
 * Checks that an uploaded file really is what its extension claims and that
 * it carries no active or hidden content (HTML, scripts, PDF JavaScript,
 * embedded files). Scanners produce plain documents, so anything unusual is
 * rejected.
 */
class FileInspector
{
    /**
     * Allowed extensions mapped to the normalised type.
     */
    public const EXTENSIONS = [
        'pdf'  => 'pdf',
        'jpg'  => 'jpg',
        'jpeg' => 'jpg',
        'png'  => 'png',
        'tif'  => 'tif',
        'tiff' => 'tif',
    ];

    public const LABELS = ['pdf' => 'PDF', 'jpg' => 'JPEG', 'png' => 'PNG', 'tif' => 'TIFF'];

    /**
     * Markup that has no business in a scanned document. Only long, specific
     * tokens are used, so random bytes of compressed image data practically
     * never match (short tags like "<svg" would cause false alarms).
     */
    private const MARKUP = '/<html[\s>]|<script[\s>]|<iframe[\s>]|<object[\s>]|<!doctype\s+html|<meta\s+http-equiv|<\?php\s|javascript:|vbscript:|mime-version:\s*1\.0|content-type:\s*text\/html/i';

    /**
     * PDF features that can execute code, open files or send data.
     */
    private const PDF_ACTIVE = [
        '/JavaScript'   => 'JavaScript',
        '/JS'           => 'JavaScript',
        '/Launch'       => 'Programmstart',
        '/EmbeddedFile' => 'eingebettete Datei',
        '/RichMedia'    => 'Multimedia-Inhalt',
        '/XFA'          => 'XFA-Formular',
        '/SubmitForm'   => 'Formular-Übermittlung',
        '/ImportData'   => 'Datenimport',
        '/GoToE'        => 'Verweis auf eingebettetes Dokument',
    ];

    /**
     * Returns the normalised type for a file name, or null if not allowed.
     */
    public static function typeForName(string $name): ?string
    {
        return self::EXTENSIONS[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * Inspects the file. Returns null if it is fine, otherwise the reason
     * (German, shown to users).
     */
    public function inspect(string $path, string $type): ?string
    {
        $size = filesize($path);
        if ($size === false || $size === 0) {
            return 'Die Datei ist leer.';
        }

        $head     = (string) file_get_contents($path, false, null, 0, 4096);
        $detected = self::detect($head);
        if ($detected !== $type) {
            return sprintf(
                'Der Inhalt ist keine echte %s-Datei (erkannt: %s).',
                self::LABELS[$type],
                self::LABELS[$detected] ?? self::describeUnknown($head)
            );
        }

        if ($this->containsMarkup($path)) {
            return 'Die Datei enthält HTML- oder Skript-Code und wurde aus Sicherheitsgründen gelöscht.';
        }

        return $type === 'pdf' ? $this->inspectPdf($path) : $this->inspectImage($path, $type);
    }

    private function inspectPdf(string $path): ?string
    {
        exec('timeout 30 pdfinfo ' . escapeshellarg($path) . ' >/dev/null 2>&1', $out, $code);
        if ($code !== 0) {
            return 'Die PDF-Datei ist beschädigt oder nicht lesbar.';
        }

        // 1. Object structure as JSON without stream data: only dictionaries
        //    and strings, so binary image data cannot cause false alarms.
        //    qpdf also resolves objects hidden in compressed object streams.
        $json = (string) shell_exec(sprintf(
            'timeout 60 qpdf --json=2 --json-stream-data=none --json-key=qpdf %s - 2>/dev/null',
            escapeshellarg($path)
        ));
        if ($json === '' || json_decode($json) === null) {
            return 'Die PDF-Datei ist beschädigt oder nicht lesbar.';
        }
        foreach (self::PDF_ACTIVE as $name => $label) {
            if (str_contains($json, '"' . $name . '"')) {
                return 'Die PDF-Datei enthält aktive Inhalte (' . $label . ') und wurde aus Sicherheitsgründen gelöscht.';
            }
        }
        if (preg_match(self::MARKUP, $json) === 1) {
            return 'Die PDF-Datei enthält versteckten HTML- oder Skript-Code und wurde aus Sicherheitsgründen gelöscht.';
        }

        // 2. Decompressed stream contents: HTML hidden inside compressed streams.
        // Decompressed copy only in RAM, never on disk.
        $work = FileCrypto::ramDirectory();
        $tmp  = $work . '/pdfscan.qdf';
        exec(sprintf(
            'timeout 60 qpdf --qdf --object-streams=disable --decode-level=generalized %s %s >/dev/null 2>&1',
            escapeshellarg($path),
            escapeshellarg($tmp)
        ), $out, $code);

        try {
            // qpdf: 0 = ok, 3 = repaired with warnings, 2 = error.
            if (! in_array($code, [0, 3], true) || ! is_file($tmp) || filesize($tmp) === 0) {
                return 'Die PDF-Datei ist beschädigt oder nicht lesbar.';
            }
            $markup = $this->scanChunks($tmp, static fn (string $chunk) => preg_match(self::MARKUP, $chunk) === 1 ? true : null);
        } finally {
            FileCrypto::removeDirectory($work);
        }

        return $markup === true
            ? 'Die PDF-Datei enthält versteckten HTML- oder Skript-Code und wurde aus Sicherheitsgründen gelöscht.'
            : null;
    }

    private function inspectImage(string $path, string $type): ?string
    {
        $expected = ['jpg' => [IMAGETYPE_JPEG], 'png' => [IMAGETYPE_PNG], 'tif' => [IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM]][$type];
        $info     = @getimagesize($path);
        if ($info === false || ! in_array($info[2], $expected, true) || $info[0] < 1 || $info[1] < 1) {
            return 'Das Bild ist beschädigt oder nicht lesbar.';
        }

        return null;
    }

    private function containsMarkup(string $path): bool
    {
        return $this->scanChunks($path, static fn (string $chunk) => preg_match(self::MARKUP, $chunk) === 1 ? true : null) === true;
    }

    /**
     * Runs $check over the file in overlapping chunks; returns the first
     * non-null result.
     */
    private function scanChunks(string $path, callable $check): mixed
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $carry = '';
        try {
            while (! feof($handle)) {
                $chunk  = $carry . fread($handle, 1048576);
                $result = $check($chunk);
                if ($result !== null) {
                    return $result;
                }
                $carry = substr($chunk, -128);
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private static function detect(string $head): ?string
    {
        return match (true) {
            str_starts_with($head, '%PDF-')                                          => 'pdf',
            str_starts_with($head, "\xFF\xD8\xFF")                                   => 'jpg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n")                              => 'png',
            str_starts_with($head, "II*\x00"), str_starts_with($head, "MM\x00*")     => 'tif',
            default                                                                  => null,
        };
    }

    private static function describeUnknown(string $head): string
    {
        $text = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $head));

        return match (true) {
            preg_match('/^<(!doctype\s+html|html|head|body|script)/i', $text) === 1 => 'HTML-Dokument',
            str_starts_with($text, '<?xml') || str_starts_with($text, '<svg')      => 'XML/SVG-Dokument',
            str_starts_with($head, "PK\x03\x04")                                     => 'ZIP- oder Office-Datei',
            str_starts_with($head, 'MZ')                                             => 'Windows-Programm',
            str_starts_with($head, "\x7FELF")                                        => 'Linux-Programm',
            str_starts_with($head, '{\\rtf')                                         => 'RTF-Dokument',
            str_starts_with($head, "\xD0\xCF\x11\xE0")                               => 'altes Office-Dokument',
            str_starts_with($head, 'GIF8')                                           => 'GIF-Bild',
            preg_match('/^MIME-Version:/i', $text) === 1                             => 'E-Mail/MHT-Datei',
            default                                                                  => 'unbekannter Inhalt',
        };
    }
}
