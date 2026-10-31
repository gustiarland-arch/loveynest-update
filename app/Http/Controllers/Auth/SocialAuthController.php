<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\GoogleAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Google sign-in and linking. Signing in only ever opens an account that already has this Google identity linked, or
 * creates a new one when the e-mail is not used yet. An e-mail that already belongs to an account is never taken over:
 * the person signs in with the password first and links Google in Settings (that is the verification).
 */
class SocialAuthController extends Controller
{
    public function redirect(Request $request, GoogleAuth $google): RedirectResponse
    {
        abort_unless(GoogleAuth::configured(), 404);
        $state = Str::random(40);
        $request->session()->put('google_state', $state);
        $request->session()->put('google_mode', 'login');

        return redirect()->away($google->authUrl($state));
    }

    /** Signed-in person links Google to the account they are in. */
    public function link(Request $request, GoogleAuth $google): RedirectResponse
    {
        abort_unless(GoogleAuth::configured(), 404);
        $state = Str::random(40);
        $request->session()->put('google_state', $state);
        $request->session()->put('google_mode', 'link');

        return redirect()->away($google->authUrl($state));
    }

    public function callback(Request $request, GoogleAuth $google): RedirectResponse|\Illuminate\Http\Response
    {
        abort_unless(GoogleAuth::configured(), 404);
        $mode = $request->session()->pull('google_mode', 'login');
        $state = $request->session()->pull('google_state');
        $back = $mode === 'link' ? route('settings') : route('login');

        if ($request->filled('error') || ! $state || ! hash_equals($state, (string) $request->query('state')) || ! $request->filled('code')) {
            return redirect($back)->with('error', 'Masuk dengan Google dibatalkan atau tidak valid. Coba lagi.');
        }

        try {
            $profile = $google->profileFromCode((string) $request->query('code'));
        } catch (\Throwable $e) {
            Log::warning('google.callback_failed', ['error' => $e::class]);
            $profile = null;
        }
        if (! $profile) {
            return redirect($back)->with('error', 'Google tidak bisa memverifikasi akunmu. Coba lagi.');
        }

        $identity = UserIdentity::where('provider', 'google')->where('provider_id', $profile['sub'])->first();

        if ($mode === 'link') {
            $user = $request->user();
            abort_unless($user, 403);
            if ($identity && $identity->user_id !== $user->id) {
                return redirect($back)->with('error', 'Akun Google ini sudah tertaut ke akun LoveyNest lain.');
            }
            if ($user->identities()->where('provider', 'google')->exists() && ! $identity) {
                return redirect($back)->with('error', 'Akunmu sudah punya Google yang tertaut. Lepas dulu bila ingin menggantinya.');
            }
            UserIdentity::updateOrCreate(
                ['provider' => 'google', 'provider_id' => $profile['sub']],
                ['user_id' => $user->id, 'label' => $profile['email'], 'verified_at' => now()],
            );

            return redirect($back)->with('success', 'Google berhasil ditautkan.');
        }

        if ($identity) {
            $user = $identity->user;
        } elseif (User::where('email', $profile['email'])->exists()) {
            return redirect($back)->with('error', 'Email ini sudah terdaftar. Masuk dengan kata sandi, lalu tautkan Google di Pengaturan.');
        } else {
            $user = User::create([
                'name' => Str::limit($profile['name'] ?: Str::before($profile['email'], '@'), 60, ''),
                'email' => $profile['email'],
                'password' => Str::random(48),
                'timezone' => config('usandus.default_timezone'),
            ]);
            $user->forceFill(['has_password' => false, 'email_verified_at' => now()])->save();
            $identity = UserIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => $profile['sub'], 'label' => $profile['email'], 'verified_at' => now()]);
        }

        $identity->forceFill(['last_used_at' => now()])->save();
        Auth::login($user, true);
        $request->session()->regenerate();
        Log::channel('audit')->info('login.google', ['user' => $user->id, 'ip' => $request->ip()]);

        // Started from the Android app (Google refuses sign-in inside the app): hand the session back to the app.
        if ($request->session()->pull('google_app')) {
            return \App\Http\Controllers\AppBridgeController::handoff($user);
        }

        $fallback = ! $user->profile_completed_at ? route('profile.setup') : ($user->activeSpaceId() ? route('dashboard') : route('onboarding'));

        return redirect()->intended($fallback);
    }
}
