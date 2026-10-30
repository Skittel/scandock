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

    /**
     * Encrypts $source into $target. The data is written to "$target.part"
     * first and renamed only after every write succeeded, so a full disk or
     * an abort never leaves a truncated file under the final name.
     *
     * @throws RuntimeException on any error (no partial file remains)
     */
    public static function encryptFile(string $source, string $target, string $key): void
    {
        $part = $target . '.part';
        $in   = fopen($source, 'rb');
        $out  = fopen($part, 'wb');
        if ($in === false || $out === false) {
            if ($in !== false) {
                fclose($in);
            }
            throw new RuntimeException('Cannot open file for encryption.');
        }

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            self::write($out, self::MAGIC . $header);
            do {
                $chunk = fread($in, self::CHUNK);
                if ($chunk === false) {
                    throw new RuntimeException('Cannot read source file.');
                }
                $tag = feof($in) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                self::write($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
            } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);

            if (! fflush($out) || ! fsync($out)) {
                throw new RuntimeException('Cannot flush encrypted file.');
            }
        } catch (\Throwable $e) {
            fclose($in);
            fclose($out);
            @unlink($part);

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        fclose($in);
        if (! fclose($out) || ! rename($part, $target)) {
            @unlink($part);

            throw new RuntimeException('Cannot finish encrypted file.');
        }
    }

    /**
     * SHA-256 of the decrypted content, computed while streaming (no copy is
     * written). Used to verify an encrypted file before the original is removed.
     *
     * @throws RuntimeException if the file is damaged or the key is wrong
     */
    public static function plaintextSha256(string $source, string $key): string
    {
        $hash = hash_init('sha256');
        self::pull($source, $key, static function (string $plain) use ($hash): void {
            hash_update($hash, $plain);
        });

        return hash_final($hash);
    }

    /**
     * Decrypts into $target, or returns the content as string if $target is null.
     */
    public static function decryptFile(string $source, string $key, ?string $target = null): ?string
    {
        if ($target === null) {
            $result = '';
            self::pull($source, $key, static function (string $plain) use (&$result): void {
                $result .= $plain;
            });

            return $result;
        }

        $out = fopen($target, 'wb');
        if ($out === false) {
            throw new RuntimeException('Cannot open target file.');
        }
        try {
            self::pull($source, $key, static function (string $plain) use ($out): void {
                self::write($out, $plain);
            });
        } finally {
            fclose($out);
        }

        return null;
    }

    /**
     * Decrypts $source chunk by chunk and passes the plaintext to $sink.
     */
    private static function pull(string $source, string $key, callable $sink): void
    {
        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException('Cannot open encrypted file.');
        }

        try {
            if (fread($in, 4) !== self::MAGIC) {
                throw new RuntimeException('Not an encrypted ScanDock file.');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
                (string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),
                $key
            );
            $tag = null;
            do {
                $chunk  = (string) fread($in, self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
                $pulled = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
                if ($pulled === false) {
                    throw new RuntimeException('Encrypted file is damaged or the key is wrong.');
                }
                [$plain, $tag] = $pulled;
                $sink($plain);
            } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL && ! feof($in));

            if ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                throw new RuntimeException('Encrypted file is truncated.');
            }
        } finally {
            fclose($in);
        }
    }

    /**
     * fwrite() that fails loudly on short writes (e.g. disk full).
     *
     * @param resource $handle
     */
    private static function write($handle, string $data): void
    {
        $length = strlen($data);
        for ($written = 0; $written < $length; $written += $bytes) {
            $bytes = fwrite($handle, substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Write failed (disk full?).');
            }
        }
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
