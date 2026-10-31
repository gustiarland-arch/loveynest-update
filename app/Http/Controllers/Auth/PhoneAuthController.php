<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserIdentity;
use App\Services\Auth\OtpService;
use App\Services\Auth\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** Sign in with a phone number that was linked in Settings, confirmed by a one-time code. */
class PhoneAuthController extends Controller
{
    public function send(Request $request, OtpService $otp): JsonResponse
    {
        abort_unless(OtpService::configured(), 404);
        $phone = Phone::normalize($request->input('phone'));
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Nomor telepon tidak valid.']);
        }
        $identity = UserIdentity::where('provider', 'phone')->where('provider_id', $phone)->first();
        // Same answer whether or not the number is linked, so nobody can probe which numbers have accounts.
        $wait = (int) config('usandus.auth.otp.cooldown_seconds', 60);
        if ($identity) {
            $result = $otp->send($phone, 'login', $identity->user, $request->ip());
            $wait = $result['wait'];
        }

        return response()->json(['ok' => true, 'wait' => $wait]);
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        abort_unless(OtpService::configured(), 404);
        $data = $request->validate(['phone' => ['required', 'string', 'max:32'], 'code' => ['required', 'string', 'max:12']]);
        $phone = Phone::normalize($data['phone']);
        $identity = $phone ? UserIdentity::where('provider', 'phone')->where('provider_id', $phone)->first() : null;

        if (! $identity || ! $otp->verify($phone, 'login', $data['code'])) {
            Log::channel('audit')->info('login.otp_failed', ['ip' => $request->ip()]);
            throw ValidationException::withMessages(['code' => 'Kode salah atau sudah kedaluwarsa.']);
        }

        $identity->forceFill(['last_used_at' => now()])->save();
        $user = $identity->user;
        Auth::login($user, true);
        $request->session()->regenerate();
        Log::channel('audit')->info('login.phone', ['user' => $user->id, 'ip' => $request->ip()]);

        $fallback = ! $user->profile_completed_at ? route('profile.setup') : ($user->activeSpaceId() ? route('dashboard') : route('onboarding'));

        return redirect()->intended($fallback);
    }
}
