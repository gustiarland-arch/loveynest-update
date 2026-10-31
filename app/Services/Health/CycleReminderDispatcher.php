<?php

namespace App\Services\Health;

use App\Models\CycleLog;
use App\Models\CycleSetting;
use App\Models\User;
use App\Notifications\CycleReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sends the reminders women switched on in the tracker. Run every 10 minutes by the scheduler: a reminder is sent in the
 * 10-minute window that starts at its time (in the person's own time zone) and at most once per day (a cache key).
 */
class CycleReminderDispatcher
{
    public const WINDOW_MINUTES = 10;

    public function __construct(private CycleService $cycle) {}

    /** @return array{sent: int, skipped: int} */
    public function run(?Carbon $at = null): array
    {
        $sent = 0;
        $skipped = 0;
        $users = User::query()->where('gender', 'perempuan')
            ->whereIn('id', CycleSetting::query()->whereNotNull('reminders')->pluck('user_id'))->get();

        foreach ($users as $user) {
            $settings = CycleSetting::where('user_id', $user->id)->first();
            $prefs = $settings?->reminderPrefs();
            if (! $prefs) {
                continue;
            }
            $local = ($at ? $at->copy() : now())->setTimezone($user->timezone ?: config('usandus.default_timezone'));
            $today = Carbon::parse($local->toDateString(), 'UTC')->startOfDay();
            $summary = $this->cycle->summary($user, $today);
            $pregnant = $summary['pregnancy'] !== null;

            foreach ($this->due($user, $prefs, $summary, $pregnant, $local, $today) as [$kind, $params, $slot]) {
                $key = "cycle-reminder:{$user->id}:{$kind}:{$today->toDateString()}:{$slot}";
                if (! Cache::add($key, 1, now()->addDay())) {
                    $skipped++;

                    continue;
                }
                try {
                    $user->notify(new CycleReminder($kind, (bool) $prefs['discreet'], $params));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('cycle.reminder_failed', ['error' => $e::class]);
                }
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /** Is the local time inside the window that starts at HH:MM? */
    private function at(Carbon $local, string $time): bool
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        $start = $local->copy()->setTime($h, $m);

        return $local->gte($start) && $local->lt($start->copy()->addMinutes(self::WINDOW_MINUTES));
    }

    /** @return list<array{0: string, 1: array, 2: string}> */
    private function due(User $user, array $prefs, array $summary, bool $pregnant, Carbon $local, Carbon $today): array
    {
        $due = [];
        $morning = $this->at($local, '09:00');

        if ($morning && ! $pregnant && $prefs['period']['on'] && $summary['nextPeriodIn'] !== null && $summary['nextPeriodIn'] === (int) $prefs['period']['days_before'] && ! $summary['ongoing']) {
            $due[] = ['period', ['when' => $summary['nextPeriodIn'] === 0 ? 'hari ini' : "dalam {$summary['nextPeriodIn']} hari"], 'd'];
        }
        if ($morning && ! $pregnant && $prefs['ovulation']['on'] && $summary['fertileStart'] === $today->toDateString()) {
            $due[] = ['ovulation', ['when' => Carbon::parse($summary['ovulation'])->locale('id')->translatedFormat('j F')], 'd'];
        }
        if ($prefs['pill']['on'] && $summary['birthControl'] === 'pill' && $this->at($local, $prefs['pill']['time'])) {
            $took = CycleLog::where('user_id', $user->id)->where('logged_on', $today->toDateString())->where('pill_taken', true)->exists();
            if (! $took) {
                $due[] = ['pill', [], 'd'];
            }
        }
        if ($prefs['water']['on']) {
            foreach (['10:00', '14:00', '18:00'] as $time) {
                if ($this->at($local, $time)) {
                    $due[] = ['water', [], $time];
                }
            }
        }
        if ($prefs['log']['on'] && $this->at($local, $prefs['log']['time'])) {
            if (! CycleLog::where('user_id', $user->id)->where('logged_on', $today->toDateString())->exists()) {
                $due[] = ['log', [], 'd'];
            }
        }

        return $due;
    }
}
