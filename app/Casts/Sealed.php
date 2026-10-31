<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Text kept scrambled in the database (AES-256 with the app key), so someone who only looks at the tables, for example
 * in phpMyAdmin or a database backup, sees "lnz1:…" instead of the content. The app opens it when it reads the row.
 *
 * This is protection at rest, different from the end-to-end lock (e2e1:) used for chat and diaries: the server can still
 * open this text, because it has to (reminders, predictions). Rows written before this existed are plain text and are
 * read as they are until `php artisan usandus:segel-data` has scrambled them.
 * KEEP A COPY OF APP_KEY: without it, text scrambled with it cannot be opened again.
 */
class Sealed implements CastsAttributes
{
    public const PREFIX = 'lnz1:';

    public static function lock(?string $plain): ?string
    {
        if ($plain === null || $plain === '' || str_starts_with($plain, self::PREFIX)) {
            return $plain;
        }

        return self::PREFIX.Crypt::encryptString($plain);
    }

    public static function unlock(?string $stored): ?string
    {
        if ($stored === null || ! str_starts_with($stored, self::PREFIX)) {
            return $stored; // empty, or a row from before the lock existed
        }
        try {
            return Crypt::decryptString(substr($stored, strlen(self::PREFIX)));
        } catch (DecryptException) {
            return null;
        }
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::unlock($value === null ? null : (string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::lock($value === null ? null : (string) $value);
    }
}
