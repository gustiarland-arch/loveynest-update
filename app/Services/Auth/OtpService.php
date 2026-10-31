<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One-time codes for phone login and for linking a phone number.
 * Codes are stored only as an HMAC, expire after config('usandus.auth.otp.ttl_minutes'), allow a few wrong guesses,
 * and can be asked for again only after a short cooldown. How the code reaches the phone is the "driver":
 *   log      – writes to storage/logs (development; phone login stays hidden in production)
 *   webhook  – POSTs {phone, message} to OTP_WEBHOOK_URL (your own gateway, e.g. a WhatsApp or SMS bridge)
 *   fonnte   – WhatsApp through fonnte.com with OTP_FONNTE_TOKEN (the provider may charge; you opt in by setting the token)
 */
class OtpService
{
    public static function driver(): string
    {
        return (string) config('usandus.auth.otp.driver', 'log');
    }

    /** Phone sign-in is offered only when codes can really reach a phone. */
    public static function configured(): bool
    {
        return match (self::driver()) {
            'webhook' => filled(config('usandus.auth.otp.webhook_url')),
            'fonnte' => filled(config('usandus.auth.otp.fonnte_token')),
            'log' => app()->isLocal() || app()->runningUnitTests(),
            default => false,
        };
    }

    /** @return array{sent: bool, wait: int} wait = seconds before another code may be requested */
    public function send(string $phone, string $purpose, ?User $user, ?string $ip): array
    {
        $cooldown = (int) config('usandus.auth.otp.cooldown_seconds', 60);
        $last = DB::table('otp_codes')->where('phone', $phone)->where('purpose', $purpose)->latest('created_at')->first();
        if ($last && Carbon::parse($last->created_at)->addSeconds($cooldown)->isFuture()) {
            return ['sent' => false, 'wait' => Carbon::parse($last->created_at)->addSeconds($cooldown)->diffInSeconds(now())];
        }
        $hourly = DB::table('otp_codes')->where('phone', $phone)->where('created_at', '>=', now()->subHour())->count();
        if ($hourly >= (int) config('usandus.auth.otp.per_hour', 5)) {
            return ['sent' => false, 'wait' => 600];
        }

        $length = (int) config('usandus.auth.otp.length', 6);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        DB::table('otp_codes')->where('phone', $phone)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        DB::table('otp_codes')->insert([
            'purpose' => $purpose,
            'phone' => $phone,
            'user_id' => $user?->id,
            'code_hash' => $this->hash($phone, $code),
            'expires_at' => now()->addMinutes((int) config('usandus.auth.otp.ttl_minutes', 5)),
            'ip' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deliver($phone, __('auth.otp_message', ['code' => $code, 'minutes' => (int) config('usandus.auth.otp.ttl_minutes', 5)]), $code);

        return ['sent' => true, 'wait' => $cooldown];
    }

    /** True once, for the newest unexpired code of that phone and purpose. */
    public function verify(string $phone, string $purpose, string $code): bool
    {
        $row = DB::table('otp_codes')->where('phone', $phone)->where('purpose', $purpose)->whereNull('consumed_at')->latest('id')->first();
        if (! $row || Carbon::parse($row->expires_at)->isPast()) {
            return false;
        }
        if ($row->attempts >= (int) config('usandus.auth.otp.max_attempts', 5)) {
            return false;
        }
        DB::table('otp_codes')->where('id', $row->id)->increment('attempts');
        if (! hash_equals($row->code_hash, $this->hash($phone, preg_replace('/\D+/', '', $code)))) {
            return false;
        }
        DB::table('otp_codes')->where('id', $row->id)->update(['consumed_at' => now()]);

        return true;
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }

    private function deliver(string $phone, string $message, string $code): void
    {
        try {
            match (self::driver()) {
                'webhook' => Http::timeout(8)->withToken((string) config('usandus.auth.otp.webhook_token'))->post(config('usandus.auth.otp.webhook_url'), ['phone' => $phone, 'message' => $message])->throw(),
                'fonnte' => Http::timeout(8)->withHeaders(['Authorization' => (string) config('usandus.auth.otp.fonnte_token')])->asForm()->post('https://api.fonnte.com/send', ['target' => $phone, 'message' => $message])->throw(),
                default => Log::info('otp.log_driver', ['phone_tail' => substr($phone, -4), 'code' => $code]),
            };
        } catch (\Throwable $e) {
            Log::warning('otp.delivery_failed', ['driver' => self::driver(), 'error' => $e::class]);
        }
    }
}
