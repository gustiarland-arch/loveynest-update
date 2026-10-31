<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\GoogleAuth;
use App\Services\Auth\OtpService;
use App\Services\Auth\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Settings > linked accounts: add a phone number (confirmed by OTP), see what is linked, remove one when another way to sign in remains. */
class IdentityController extends Controller
{
    /** @return array<string, mixed> shared with the Settings page */
    public static function forUser(User $user): array
    {
        return [
            'items' => $user->identities()->orderBy('provider')->get()->map(fn (UserIdentity $i) => [
                'id' => $i->id,
                'provider' => $i->provider,
                'label' => $i->maskedLabel(),
                'verifiedAt' => $i->verified_at?->toIso8601String(),
                'lastUsedAt' => $i->last_used_at?->toIso8601String(),
            ])->all(),
            'google' => GoogleAuth::configured(),
            'phone' => OtpService::configured(),
            'hasPassword' => (bool) $user->has_password,
        ];
    }

    public function sendPhoneCode(Request $request, OtpService $otp): JsonResponse
    {
        abort_unless(OtpService::configured(), 404);
        $phone = Phone::normalize($request->input('phone'));
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Nomor telepon tidak valid.']);
        }
        $user = $request->user();
        if ($user->identities()->where('provider', 'phone')->exists()) {
            throw ValidationException::withMessages(['phone' => 'Akunmu sudah punya nomor yang tertaut. Lepas dulu bila ingin menggantinya.']);
        }
        if (UserIdentity::where('provider', 'phone')->where('provider_id', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Nomor ini sudah tertaut ke akun lain.']);
        }
        $result = $otp->send($phone, 'link', $user, $request->ip());
        if (! $result['sent']) {
            throw ValidationException::withMessages(['phone' => "Tunggu {$result['wait']} detik sebelum meminta kode lagi."]);
        }

        return response()->json(['ok' => true, 'wait' => $result['wait']]);
    }

    public function verifyPhone(Request $request, OtpService $otp): RedirectResponse
    {
        abort_unless(OtpService::configured(), 404);
        $data = $request->validate(['phone' => ['required', 'string', 'max:32'], 'code' => ['required', 'string', 'max:12']]);
        $phone = Phone::normalize($data['phone']);
        if (! $phone || ! $otp->verify($phone, 'link', $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Kode salah atau sudah kedaluwarsa.']);
        }
        if (UserIdentity::where('provider', 'phone')->where('provider_id', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Nomor ini sudah tertaut ke akun lain.']);
        }
        $request->user()->identities()->create(['provider' => 'phone', 'provider_id' => $phone, 'verified_at' => now()]);

        return back()->with('success', 'Nomor telepon berhasil ditautkan.');
    }

    public function destroy(Request $request, UserIdentity $identity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($identity->user_id === $user->id, 404);
        // Never leave an account with no way in.
        if (! $user->has_password && $user->identities()->count() <= 1) {
            return back()->with('error', 'Ini satu-satunya cara masuk akunmu. Tautkan cara lain dulu sebelum melepasnya.');
        }
        $identity->delete();

        return back()->with('success', 'Identitas dilepas.');
    }
}
