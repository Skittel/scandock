<?php

namespace App\Libraries;

/**
 * Random identifiers for URLs, so database IDs (and thereby the number of
 * uploads or accounts of other customers) are never exposed.
 */
class PublicId
{
    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';
    public const LENGTH    = 16;
    public const PATTERN   = '[a-z0-9]{16}';

    public static function generate(): string
    {
        $id = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $id .= self::ALPHABET[random_int(0, 31)];
        }

        return $id;
    }

    /**
     * Model callback: adds a public ID to inserted rows that have none.
     */
    public static function assign(array $data): array
    {
        if (empty($data['data']['public_id'])) {
            $data['data']['public_id'] = self::generate();
        }

        return $data;
    }
}
