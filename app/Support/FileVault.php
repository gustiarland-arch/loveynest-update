<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Small files that the server itself must be able to show (profile photo, backdrop) are kept scrambled on disk:
 * "LNV1" + AES-256 with the app key. Photos, chat pictures and voice notes of the couple are different: they are
 * locked end to end on the phone (see E2E) and never reach the server in a readable form.
 */
final class FileVault
{
    public const MAGIC = 'LNV1';

    /** Scrambles a file that is already stored (does nothing when it is already scrambled or missing). */
    public static function seal(string $path): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return;
        }
        $raw = (string) $disk->get($path);
        if ($raw === '' || str_starts_with($raw, self::MAGIC)) {
            return;
        }
        $disk->put($path, self::MAGIC.Crypt::encryptString($raw));
    }

    /** The readable bytes of a stored file, whether it is scrambled or an older plain file. */
    public static function read(string $path): ?string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return null;
        }
        $raw = (string) $disk->get($path);
        if (! str_starts_with($raw, self::MAGIC)) {
            return $raw;
        }
        try {
            return Crypt::decryptString(substr($raw, strlen(self::MAGIC)));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function response(string $path, string $mime, string $cache): Response
    {
        $bytes = self::read($path);
        abort_if($bytes === null, 404);

        return response($bytes, 200, ['Content-Type' => $mime, 'Cache-Control' => $cache, 'Content-Length' => (string) strlen($bytes)]);
    }
}
