<?php

namespace App\Services\Health;

use App\Models\CycleLog;
use App\Models\CyclePeriod;
use App\Models\CycleSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Predictions for the period and ovulation tracker. Everything is computed from the dates the person entered herself:
 * average cycle length from her own history (settings are used until there are two periods), ovulation about
 * `luteal_length` days before the next period, fertile window = 5 days before ovulation up to the day after.
 * These are estimates, not medical facts, and the screen says so.
 */
class CycleService
{
    public const MIN_CYCLE = 18;

    public const MAX_CYCLE = 60;

    public function settings(User $user): CycleSetting
    {
        return CycleSetting::firstOrCreate(['user_id' => $user->id], ['cycle_length' => 28, 'period_length' => 5, 'luteal_length' => 14]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, Carbon $today): array
    {
        $settings = $this->settings($user);
        $periods = CyclePeriod::where('user_id', $user->id)->orderBy('started_on')->get();
        $stats = $this->stats($periods, $settings);
        $cycle = $stats['avg_cycle'];
        $luteal = (int) $settings->luteal_length;

        $last = $periods->last();
        $lastStart = $last ? Carbon::parse($last->started_on)->startOfDay() : null;
        $ongoing = $last && $last->ended_on === null && $lastStart->lte($today);

        $cycleDay = $lastStart ? (int) $lastStart->diffInDays($today, false) + 1 : null;
        $nextPeriod = $lastStart ? $lastStart->copy()->addDays($cycle) : null;
        // A prediction already in the past stays as it is: the screen then says the period is late by that many days.
        $ovulation = $nextPeriod ? $nextPeriod->copy()->subDays($luteal) : null;
        // The current cycle's ovulation may already be behind us: then show the following one.
        if ($lastStart) {
            $thisOvulation = $lastStart->copy()->addDays($cycle - $luteal);
            $ovulation = $thisOvulation->gte($today) ? $thisOvulation : $thisOvulation->copy()->addDays($cycle);
        }

        $pregnancy = $this->pregnancy($settings, $today);

        return [
            'goal' => $settings->goal ?? 'track',
            'birthControl' => $settings->birth_control,
            'pregnancy' => $pregnancy,
            'onboarded' => $settings->onboarded_at !== null || $periods->isNotEmpty(),
            'fertility' => $lastStart && ! $pregnancy ? $this->fertility($today, $lastStart, $cycle, $luteal) : null,
            'reminders' => $settings->reminderPrefs(),
            'hasPin' => $settings->pin_hash !== null,
            'today' => $today->toDateString(),
            'hasData' => (bool) $last,
            'ongoing' => $ongoing,
            'ongoingId' => $ongoing ? $last->id : null,
            'cycleDay' => $cycleDay && $cycleDay > 0 ? $cycleDay : null,
            'phase' => $lastStart ? $this->phase($cycleDay, $ongoing, (int) $stats['avg_period'], $cycle, $luteal) : null,
            'nextPeriod' => $nextPeriod?->toDateString(),
            'nextPeriodIn' => $nextPeriod ? (int) $today->diffInDays($nextPeriod, false) : null,
            'ovulation' => $ovulation?->toDateString(),
            'ovulationIn' => $ovulation ? (int) $today->diffInDays($ovulation, false) : null,
            'fertileStart' => $ovulation?->copy()->subDays(5)->toDateString(),
            'fertileEnd' => $ovulation?->copy()->addDay()->toDateString(),
            'stats' => $stats,
            'settings' => ['cycle_length' => (int) $settings->cycle_length, 'period_length' => (int) $settings->period_length, 'luteal_length' => $luteal],
        ];
    }

    /** Chance of getting pregnant on a day, from the predicted ovulation of the cycle that day belongs to. */
    public function fertility(Carbon $date, Carbon $lastStart, int $cycle, int $luteal): ?string
    {
        if ($date->lt($lastStart)) {
            return null;
        }
        $n = intdiv((int) $lastStart->diffInDays($date), max(1, $cycle));
        $ovulation = $lastStart->copy()->addDays($n * $cycle + $cycle - $luteal);
        $d = (int) $ovulation->diffInDays($date, false);
        if ($d < -5 || $d > 1) {
            return 'low';
        }

        return $d === 0 || $d === -1 ? 'very_high' : 'high';
    }

    /** @return array<string, mixed>|null */
    public function pregnancy(CycleSetting $settings, Carbon $today): ?array
    {
        if (($settings->goal ?? 'track') !== 'pregnancy' || ! $settings->pregnancy_started_on) {
            return null;
        }
        $lmp = Carbon::parse($settings->pregnancy_started_on)->startOfDay();
        $days = max(0, (int) $lmp->diffInDays($today, false));
        $weeks = intdiv($days, 7);
        $due = $lmp->copy()->addDays(280);

        return [
            'started_on' => $lmp->toDateString(),
            'week' => $weeks,
            'day' => $days % 7,
            'trimester' => $weeks < 13 ? 1 : ($weeks < 27 ? 2 : 3),
            'due' => $due->toDateString(),
            'daysLeft' => (int) $today->diffInDays($due, false),
            'progress' => min(1, $days / 280),
        ];
    }

    /** @param  \Illuminate\Support\Collection<int, CyclePeriod>  $periods */
    private function stats($periods, CycleSetting $settings): array
    {
        $lengths = [];
        for ($i = 1; $i < $periods->count(); $i++) {
            $days = (int) Carbon::parse($periods[$i - 1]->started_on)->diffInDays(Carbon::parse($periods[$i]->started_on));
            if ($days >= self::MIN_CYCLE && $days <= self::MAX_CYCLE) {
                $lengths[] = $days;
            }
        }
        $recent = array_slice($lengths, -6);
        $periodLengths = $periods->filter(fn ($p) => $p->ended_on)->map(fn ($p) => (int) Carbon::parse($p->started_on)->diffInDays(Carbon::parse($p->ended_on)) + 1)->filter(fn ($d) => $d >= 1 && $d <= 14)->values()->all();
        $periodLengths = array_slice($periodLengths, -6);

        $avgCycle = $recent ? (int) round(array_sum($recent) / count($recent)) : (int) $settings->cycle_length;
        $avgPeriod = $periodLengths ? (int) round(array_sum($periodLengths) / count($periodLengths)) : (int) $settings->period_length;

        $regularity = null;
        $spread = null;
        if (count($lengths) >= 3) {
            $spread = max($lengths) - min($lengths);
            $regularity = $spread <= 4 ? 'regular' : ($spread <= 8 ? 'fairly' : 'irregular');
        }

        return [
            'avg_cycle' => $avgCycle,
            'avg_period' => $avgPeriod,
            'shortest' => $lengths ? min($lengths) : null,
            'longest' => $lengths ? max($lengths) : null,
            'spread' => $spread,
            'regularity' => $regularity,
            'cycles_tracked' => count($lengths),
            'periods_tracked' => $periods->count(),
            'learned' => (bool) $recent, // false = still using the numbers from settings
        ];
    }

    private function phase(?int $day, bool $ongoing, int $periodLen, int $cycle, int $luteal): string
    {
        if ($ongoing || ($day !== null && $day >= 1 && $day <= $periodLen)) {
            return 'menstrual';
        }
        $ovulationDay = $cycle - $luteal + 1;
        if ($day !== null && $day >= $ovulationDay - 5 && $day <= $ovulationDay + 1) {
            return 'fertile';
        }

        return $day !== null && $day < $ovulationDay - 5 ? 'follicular' : 'luteal';
    }

    /**
     * What each day of a month is: logged period, predicted period, fertile window, ovulation. Predictions run 3 cycles ahead.
     *
     * @return array<string, list<string>> date => marks
     */
    public function month(User $user, string $month, Carbon $today): array
    {
        $first = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $last = $first->copy()->endOfMonth();
        $summary = $this->summary($user, $today);
        $marks = [];
        $mark = function (Carbon $date, string $kind) use (&$marks, $first, $last) {
            if ($date->betweenIncluded($first, $last)) {
                $marks[$date->toDateString()][] = $kind;
            }
        };

        $settings = $summary['settings'];
        $cycle = $summary['stats']['avg_cycle'];
        $periodLen = $summary['stats']['avg_period'];
        $luteal = $settings['luteal_length'];

        $periods = CyclePeriod::where('user_id', $user->id)->orderBy('started_on')->get();
        foreach ($periods as $p) {
            $start = Carbon::parse($p->started_on)->startOfDay();
            $end = $p->ended_on ? Carbon::parse($p->ended_on)->startOfDay() : ($start->lte($today) ? $today->copy()->min($start->copy()->addDays($periodLen - 1)) : $start);
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $mark($d, 'period');
            }
        }

        if ($periods->isNotEmpty() && ! $summary['pregnancy']) {
            $lastStart = Carbon::parse($periods->last()->started_on)->startOfDay();
            for ($n = 1; $n <= 3; $n++) {
                $nextStart = $lastStart->copy()->addDays($cycle * $n);
                if ($nextStart->gte($today) || $n > 1) {
                    for ($i = 0; $i < $periodLen; $i++) {
                        $d = $nextStart->copy()->addDays($i);
                        if ($d->gt($today)) {
                            $mark($d, 'predicted');
                        }
                    }
                }
            }
            // Ovulation and fertile window of the current and the next 3 cycles (not drawn over days already past).
            for ($n = 0; $n <= 3; $n++) {
                $ov = $lastStart->copy()->addDays($cycle * $n + $cycle - $luteal);
                for ($i = -5; $i <= 1; $i++) {
                    $d = $ov->copy()->addDays($i);
                    if ($d->gte($today)) {
                        $mark($d, $i === 0 ? 'ovulation' : 'fertile');
                    }
                }
            }
        }

        foreach (CycleLog::where('user_id', $user->id)->whereBetween('logged_on', [$first->toDateString(), $last->toDateString()])->pluck('logged_on') as $date) {
            $mark(Carbon::parse($date), 'logged');
        }
        $mark($today->copy(), 'today');

        return $marks;
    }

    /** Past cycles, newest first: start date, length in days (to the next period) and period length. */
    public function history(User $user, int $limit = 12): array
    {
        $periods = CyclePeriod::where('user_id', $user->id)->orderBy('started_on')->get();
        $rows = [];
        foreach ($periods as $i => $p) {
            $start = Carbon::parse($p->started_on);
            $next = $periods[$i + 1] ?? null;
            $rows[] = [
                'id' => $p->id,
                'started_on' => $start->toDateString(),
                'ended_on' => $p->ended_on?->toDateString(),
                'period_days' => $p->ended_on ? (int) $start->diffInDays(Carbon::parse($p->ended_on)) + 1 : null,
                'cycle_days' => $next ? (int) $start->diffInDays(Carbon::parse($next->started_on)) : null,
            ];
        }

        return array_slice(array_reverse($rows), 0, $limit);
    }

    /**
     * Analysis for the "Analisis" tab, all computed from what this person logged.
     *
     * @return array<string, mixed>
     */
    public function insights(User $user, Carbon $today): array
    {
        $settings = $this->settings($user);
        $periods = CyclePeriod::where('user_id', $user->id)->orderBy('started_on')->get();
        $stats = $this->stats($periods, $settings);
        $luteal = (int) $settings->luteal_length;
        $cycle = $stats['avg_cycle'];

        $cycles = [];
        foreach ($periods as $i => $p) {
            $start = Carbon::parse($p->started_on);
            $next = $periods[$i + 1] ?? null;
            $cycles[] = [
                'start' => $start->toDateString(),
                'length' => $next ? (int) $start->diffInDays(Carbon::parse($next->started_on)) : null,
                'period' => $p->ended_on ? (int) $start->diffInDays(Carbon::parse($p->ended_on)) + 1 : null,
            ];
        }
        $cycles = array_slice($cycles, -12);
        $lengths = array_values(array_filter(array_column($cycles, 'length'), fn ($v) => $v !== null && $v >= self::MIN_CYCLE && $v <= self::MAX_CYCLE));
        $trend = null;
        if (count($lengths) >= 4) {
            $recent = array_slice($lengths, -3);
            $before = array_slice($lengths, 0, -3);
            $trend = (int) round(array_sum($recent) / count($recent) - array_sum($before) / count($before));
        }

        $logs = CycleLog::where('user_id', $user->id)->where('logged_on', '>=', $today->copy()->subDays(180)->toDateString())->orderBy('logged_on')->get();
        $starts = $periods->map(fn ($p) => Carbon::parse($p->started_on)->startOfDay());
        $phases = [];
        $overall = ['symptoms' => [], 'moods' => []];
        $byPhase = [];
        foreach (['menstrual', 'follicular', 'fertile', 'luteal'] as $ph) {
            $byPhase[$ph] = ['days' => 0, 'symptoms' => [], 'moods' => [], 'energy' => [], 'sleep' => [], 'cramps' => []];
        }
        foreach ($logs as $log) {
            $date = Carbon::parse($log->logged_on)->startOfDay();
            $start = $starts->filter(fn ($s) => $s->lte($date))->last();
            $moods = $log->moods ?: ($log->mood ? [$log->mood] : []);
            foreach ($log->symptoms ?? [] as $k) {
                $overall['symptoms'][$k] = ($overall['symptoms'][$k] ?? 0) + 1;
            }
            foreach ($moods as $k) {
                $overall['moods'][$k] = ($overall['moods'][$k] ?? 0) + 1;
            }
            if (! $start || $start->diffInDays($date) > self::MAX_CYCLE) {
                continue;
            }
            $day = (int) $start->diffInDays($date) + 1;
            $phase = $this->phase($day, false, $stats['avg_period'], $cycle, $luteal);
            $byPhase[$phase]['days']++;
            foreach ($log->symptoms ?? [] as $k) {
                $byPhase[$phase]['symptoms'][$k] = ($byPhase[$phase]['symptoms'][$k] ?? 0) + 1;
            }
            foreach ($moods as $k) {
                $byPhase[$phase]['moods'][$k] = ($byPhase[$phase]['moods'][$k] ?? 0) + 1;
            }
            foreach (['energy', 'sleep', 'cramps'] as $f) {
                if ($log->{$f} !== null) {
                    $byPhase[$phase][$f][] = (int) $log->{$f};
                }
            }
        }
        $top = fn (array $counts, int $n) => collect($counts)->sortDesc()->take($n)->map(fn ($c, $k) => ['key' => $k, 'count' => $c])->values()->all();
        $avg = fn (array $v) => $v ? round(array_sum($v) / count($v), 1) : null;
        foreach ($byPhase as $ph => $row) {
            $phases[$ph] = ['days' => $row['days'], 'symptoms' => $top($row['symptoms'], 3), 'moods' => $top($row['moods'], 2), 'energy' => $avg($row['energy']), 'sleep' => $avg($row['sleep']), 'cramps' => $avg($row['cramps'])];
        }

        $recent = $logs->filter(fn ($l) => Carbon::parse($l->logged_on)->gte($today->copy()->subDays(29)));
        $series = fn (string $field) => $recent->filter(fn ($l) => $l->{$field} !== null)->map(fn ($l) => ['date' => Carbon::parse($l->logged_on)->toDateString(), 'value' => (float) $l->{$field}])->values()->all();

        $dates = $logs->map(fn ($l) => Carbon::parse($l->logged_on)->toDateString())->flip();
        $streak = 0;
        $cursor = $today->copy();
        if (! isset($dates[$cursor->toDateString()])) {
            $cursor->subDay();
        }
        while (isset($dates[$cursor->toDateString()])) {
            $streak++;
            $cursor->subDay();
        }

        return [
            'cycles' => $cycles,
            'stats' => $stats,
            'trend' => $trend,
            'phases' => $phases,
            'topSymptoms' => $top($overall['symptoms'], 6),
            'topMoods' => $top($overall['moods'], 5),
            'series' => ['weight' => $series('weight_kg'), 'temperature' => $series('temperature_c'), 'water' => $series('water_ml'), 'sleep' => $series('sleep_hours')],
            'streak' => $streak,
            'logCount' => $logs->count(),
        ];
    }
}
