<?php

namespace App\Libraries;

use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use Config\ScanKai;
use RuntimeException;

/**
 * Creates and maintains FTP accounts including their upload directories.
 */
class FtpAccountManager
{
    /**
     * Characters for generated credentials. Ambiguous characters are left out
     * because the values are typed in on copier control panels.
     */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly ScanKai $config)
    {
    }

    public function create(array $tenant, string $label, string $visibility = 'private'): array
    {
        $model = model(FtpAccountModel::class);

        do {
            $username = $tenant['slug'] . '-' . strtolower(self::randomString(6, 'abcdefghjkmnpqrstuvwxyz23456789'));
        } while ($model->where('username', $username)->countAllResults() > 0);

        $password = self::randomString(16);
        $id       = $model->insert([
            'tenant_id'          => $tenant['id'],
            'label'              => $label,
            'visibility'         => $visibility === 'public' ? 'public' : 'private',
            'username'           => $username,
            'password_hash'      => self::hashPassword($password),
            'password_encrypted' => base64_encode(service('encrypter')->encrypt($password)),
            'active'             => 1,
        ]);

        $this->createIncomingDirectory((int) $id);

        return $model->find($id);
    }

    public function regeneratePassword(array $account): void
    {
        $password = self::randomString(16);
        model(FtpAccountModel::class)->update($account['id'], [
            'password_hash'      => self::hashPassword($password),
            'password_encrypted' => base64_encode(service('encrypter')->encrypt($password)),
        ]);
    }

    /**
     * Deletes the account, all of its scans and its upload directory.
     */
    public function delete(array $account): void
    {
        $store = service('scanStore');
        foreach (model(ScanModel::class)->where('ftp_account_id', $account['id'])->findAll() as $scan) {
            $store->delete($scan);
        }

        $dir = $this->incomingDirectory((int) $account['id']);
        if (is_dir($dir)) {
            array_map('unlink', array_filter(glob($dir . '/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
            @rmdir($dir);
        }

        model(FtpAccountModel::class)->delete($account['id']);
    }

    public function incomingDirectory(int $accountId): string
    {
        return rtrim($this->config->incomingPath, '/') . '/' . $accountId;
    }

    private function createIncomingDirectory(int $accountId): void
    {
        $dir = $this->incomingDirectory($accountId);
        if (! is_dir($dir) && ! mkdir($dir, 0770) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create upload directory ' . $dir);
        }
        // mkdir() is subject to the umask, so set the mode explicitly.
        chmod($dir, 0770);
    }

    /**
     * SHA-512 crypt hash as understood by ProFTPD (SQLAuthTypes Crypt).
     */
    public static function hashPassword(string $password): string
    {
        $salt = self::randomString(16, './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz');

        return crypt($password, '$6$rounds=50000$' . $salt . '$');
    }

    public static function randomString(int $length, string $alphabet = self::ALPHABET): string
    {
        $max    = strlen($alphabet) - 1;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $max)];
        }

        return $result;
    }
}
