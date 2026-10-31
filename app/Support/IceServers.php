<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Where the two phones meet for calls and Arena voice (WebRTC ICE servers).
 *
 * STUN alone lets two phones find each other on most Wi-Fi, but on mobile data (CGNAT, common in Indonesia) a call then
 * stays on "menyambungkan" forever. A TURN relay fixes that. Three ways to add one, first match wins (see .env.example):
 *  1. Cloudflare TURN (free tier): CLOUDFLARE_TURN_KEY_ID + CLOUDFLARE_TURN_API_TOKEN, short-lived credentials made here.
 *  2. Metered (free tier): METERED_TURN_DOMAIN + METERED_TURN_API_KEY.
 *  3. Any TURN server: TURN_URL (+ TURN_USERNAME, TURN_CREDENTIAL).
 * The voice itself stays encrypted end to end by WebRTC (DTLS-SRTP); a relay only passes the scrambled packets on.
 */
final class IceServers
{
    private const STUN = ['urls' => ['stun:stun.cloudflare.com:3478', 'stun:stun.l.google.com:19302']];

    /** @return list<array{urls: string|list<string>, username?: string, credential?: string}> */
    public static function get(): array
    {
        return Cache::remember('rtc.ice.v1', now()->addHours(6), fn () => [self::STUN, ...self::relay()]);
    }

    public static function hasRelay(): bool
    {
        return count(self::get()) > 1;
    }

    /** @return list<array<string, mixed>> */
    private static function relay(): array
    {
        try {
            if (($id = config('usandus.turn.cloudflare_key_id')) && ($token = config('usandus.turn.cloudflare_token'))) {
                $res = Http::timeout(8)->withToken($token)
                    ->post("https://rtc.live.cloudflare.com/v1/turn/keys/{$id}/credentials/generate-ice-servers", ['ttl' => 86400]);
                if (! $res->ok()) {
                    // older API shape
                    $res = Http::timeout(8)->withToken($token)->post("https://rtc.live.cloudflare.com/v1/turn/keys/{$id}/credentials/generate", ['ttl' => 86400]);
                }
                $servers = $res->json('iceServers');
                $servers = isset($servers['urls']) ? [$servers] : (array) $servers;

                return self::clean($servers);
            }
            if (($domain = config('usandus.turn.metered_domain')) && ($key = config('usandus.turn.metered_key'))) {
                $res = Http::timeout(8)->get('https://'.trim($domain, '/').'/api/v1/turn/credentials', ['apiKey' => $key]);

                return self::clean((array) $res->json());
            }
        } catch (\Throwable $e) {
            Log::warning('rtc.turn_fetch_failed', ['error' => $e::class]);
        }
        $static = config('usandus.turn.url');

        return $static ? [['urls' => array_values(array_filter(array_map('trim', explode(',', (string) $static)))), 'username' => (string) config('usandus.turn.username', ''), 'credential' => (string) config('usandus.turn.credential', '')]] : [];
    }

    /** Keeps only well-formed TURN/STUN entries. @return list<array<string, mixed>> */
    private static function clean(array $servers): array
    {
        $out = [];
        foreach ($servers as $s) {
            if (! is_array($s) || empty($s['urls'])) {
                continue;
            }
            $urls = array_values(array_filter((array) $s['urls'], fn ($u) => is_string($u) && preg_match('/^(stun|turns?):/', $u)));
            if ($urls === []) {
                continue;
            }
            $out[] = array_filter(['urls' => $urls, 'username' => $s['username'] ?? null, 'credential' => $s['credential'] ?? null], fn ($v) => $v !== null);
        }

        return $out;
    }
}
