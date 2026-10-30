<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Encrypts files at rest with libsodium secretstream (XChaCha20-Poly1305) in
 * 64 KiB chunks, so large scans never have to fit into memory.
 *
 * File format: "SKE1" + secretstream header + encrypted chunks.
 */
class FileCrypto
{
    private const MAGIC = 'SKE1';
    private const CHUNK = 65536;

    public static function generateKey(): string
    {
        return sodium_crypto_secretstream_xchacha20poly1305_keygen();
    }

    public static function encryptFile(string $source, string $target, string $key): void
    {
        $in  = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Cannot open file for encryption.');
        }

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            fwrite($out, self::MAGIC . $header);
            do {
                $chunk = (string) fread($in, self::CHUNK);
                $tag   = feof($in) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
            } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * Decrypts into $target, or returns the content as string if $target is null.
     */
    public static function decryptFile(string $source, string $key, ?string $target = null): ?string
    {
        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException('Cannot open encrypted file.');
        }
        $out    = $target === null ? null : fopen($target, 'wb');
        $result = '';

        try {
            if (fread($in, 4) !== self::MAGIC) {
                throw new RuntimeException('Not an encrypted ScanDock file.');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
                (string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),
                $key
            );
            do {
                $chunk  = (string) fread($in, self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
                $pulled = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
                if ($pulled === false) {
                    throw new RuntimeException('Encrypted file is damaged or the key is wrong.');
                }
                [$plain, $tag] = $pulled;
                $out === null ? $result .= $plain : fwrite($out, $plain);
            } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL && ! feof($in));

            if ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                throw new RuntimeException('Encrypted file is truncated.');
            }
        } finally {
            fclose($in);
            if ($out !== null) {
                fclose($out);
            }
        }

        return $target === null ? $result : null;
    }

    /**
     * Creates a private working directory in RAM (/dev/shm), so plaintext
     * intermediate files never touch the disk.
     */
    public static function ramDirectory(): string
    {
        $base = is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm' : sys_get_temp_dir();
        $dir  = $base . '/scandock-' . bin2hex(random_bytes(8));
        if (! mkdir($dir, 0700)) {
            throw new RuntimeException('Cannot create working directory.');
        }

        return $dir;
    }

    public static function removeDirectory(string $dir): void
    {
        foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
}
