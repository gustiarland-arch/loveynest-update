<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;

/**
 * Google sign-in with the standard OAuth "authorization code" flow, done on the server (free, no extra package).
 * Needs GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET (Google Cloud Console > Credentials > OAuth client, type "Web application")
 * and the redirect URI https://your-domain/auth/google/callback.
 */
class GoogleAuth
{
    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google.redirect') ?: url('/auth/google/callback');
    }

    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    /** @return array{sub: string, email: string, name: string}|null null when Google refuses or the e-mail is not verified */
    public function profileFromCode(string $code): ?array
    {
        $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        if (! $response->ok() || ! ($idToken = $response->json('id_token'))) {
            return null;
        }
        // The token came straight from Google over TLS, so its signature need not be checked again; its claims are.
        $parts = explode('.', $idToken);
        $claims = count($parts) === 3 ? json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
        if (! is_array($claims)
            || ($claims['aud'] ?? null) !== config('services.google.client_id')
            || ! in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
            || ($claims['exp'] ?? 0) < time()
            || empty($claims['sub']) || empty($claims['email'])
            || ! filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return ['sub' => (string) $claims['sub'], 'email' => strtolower($claims['email']), 'name' => (string) ($claims['name'] ?? '')];
    }
}
