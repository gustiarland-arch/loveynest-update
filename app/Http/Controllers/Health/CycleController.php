<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Models\CycleLog;
use App\Models\CyclePeriod;
use App\Models\CycleSetting;
use App\Services\Health\CycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Period, ovulation and pregnancy tracker. Only for people whose profile says perempuan. Every query is scoped to the
 * signed-in user's own id, so nobody else (not even the partner in the same space) can read or change this data.
 * An optional PIN locks the whole tracker: while it is locked the server answers nothing but the PIN screen.
 */
class CycleController extends Controller
{
    public const MOODS = ['senang', 'tenang', 'energik', 'percaya_diri', 'sensitif', 'sedih', 'cemas', 'tertekan', 'marah', 'tersinggung', 'bingung', 'apatis'];

    public const SKIN = ['normal', 'berminyak', 'kering', 'berjerawat'];

    public const SYMPTOMS = ['sakit_kepala', 'kembung', 'mual', 'nyeri_payudara', 'sakit_punggung', 'pusing', 'ngidam', 'diare', 'sembelit', 'insomnia', 'keputihan', 'lelah', 'jerawat', 'nyeri_perut', 'gatal_vagina', 'vagina_kering', 'keringat_malam'];

    public const SEX = ['tidak_ada', 'aman', 'tanpa_pengaman', 'masturbasi', 'gairah_tinggi', 'gairah_rendah'];

    public const DISCHARGE = ['kering', 'lengket', 'krim', 'encer', 'putih_telur', 'bercak', 'tidak_biasa'];

    public const DIGESTION = ['normal', 'mual', 'kembung', 'sembelit', 'diare'];

    public const PREGNANCY_TEST = ['belum', 'positif', 'negatif', 'samar'];

    public const OVULATION_TEST = ['belum', 'positif', 'negatif'];

    public const ACTIVITY = ['tidak_olahraga', 'yoga', 'gym', 'aerobik', 'renang', 'olahraga_tim', 'lari', 'bersepeda', 'jalan', 'menari'];

    public const OTHER = ['perjalanan', 'stres', 'sakit_cedera', 'alkohol'];

    public const BIRTH_CONTROL = ['none', 'pill', 'iud', 'implant', 'injection', 'condom'];

    private const IDLE_LOCK_SECONDS = 600;

    public function __construct(private CycleService $cycle) {}

    // ---------------------------------------------------------------- access

    private function guard(Request $request): void
    {
        abort_unless($request->user()->gender === 'perempuan', 404);
    }

    private function isLocked(Request $request): bool
    {
        $settings = CycleSetting::where('user_id', $request->user()->id)->first();
        if (! $settings || ! $settings->pin_hash) {
            return false;
        }
        $at = (int) $request->session()->get('cycle_unlocked_at', 0);

        return time() - $at > self::IDLE_LOCK_SECONDS;
    }

    /** Women only, and not locked: every route that touches data starts here. */
    private function open(Request $request): void
    {
        $this->guard($request);
        abort_if($this->isLocked($request), 423, 'Terkunci.');
        if ($request->session()->has('cycle_unlocked_at')) {
            $request->session()->put('cycle_unlocked_at', time()); // activity keeps it open
        }
    }

    /** Today's date where the person lives, as midnight UTC so it compares cleanly with stored dates. */
    private function today(Request $request): Carbon
    {
        $zone = $request->user()->timezone ?: config('usandus.default_timezone');

        return Carbon::parse(now($zone)->toDateString(), 'UTC')->startOfDay();
    }

    private function options(): array
    {
        return [
            'moods' => self::MOODS, 'skin' => self::SKIN, 'symptoms' => self::SYMPTOMS, 'sex' => self::SEX, 'discharge' => self::DISCHARGE,
            'digestion' => self::DIGESTION, 'pregnancyTest' => self::PREGNANCY_TEST, 'ovulationTest' => self::OVULATION_TEST,
            'activity' => self::ACTIVITY, 'other' => self::OTHER, 'birthControl' => self::BIRTH_CONTROL,
        ];
    }

    // ---------------------------------------------------------------- pages

    public function show(Request $request): Response
    {
        $this->guard($request);
        if ($this->isLocked($request)) {
            return Inertia::render('Health/Cycle', ['locked' => true, 'options' => $this->options()]);
        }
        $this->open($request);
        $user = $request->user();
        $today = $this->today($request);
        $month = $today->format('Y-m');

        return Inertia::render('Health/Cycle', [
            'locked' => false,
            'summary' => $this->cycle->summary($user, $today),
            'month' => $month,
            'marks' => $this->cycle->month($user, $month, $today),
            'history' => $this->cycle->history($user),
            'options' => $this->options(),
        ]);
    }

    /** Calendar of another month. */
    public function calendar(Request $request): JsonResponse
    {
        $this->open($request);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        return response()->json(['month' => $data['month'], 'marks' => $this->cycle->month($request->user(), $data['month'], $this->today($request))]);
    }

    public function insights(Request $request): JsonResponse
    {
        $this->open($request);

        return response()->json($this->cycle->insights($request->user(), $this->today($request)));
    }

    /** The AI analysis kept from an earlier tap for the current data, without calling the AI (see CycleAnalyst). */
    public function aiCached(Request $request, \App\Services\Ai\CycleAnalyst $analyst): JsonResponse
    {
        $this->open($request);
        $user = $request->user();
        $today = $this->today($request);

        return response()->json(['result' => $analyst->cached($user, $this->cycle->insights($user, $today), $this->cycle->summary($user, $today)), 'available' => app(\App\Services\Ai\QuizWriter::class)->enabled()]);
    }

    /** Writes the analysis: AI under the app's rules, or the rule-based text when AI is off, fails or data is too thin. */
    public function aiRun(Request $request, \App\Services\Ai\CycleAnalyst $analyst): JsonResponse
    {
        $this->open($request);
        $user = $request->user();
        $today = $this->today($request);

        return response()->json(['result' => $analyst->analyse($user, $this->cycle->insights($user, $today), $this->cycle->summary($user, $today))]);
    }

    /** "Tanya AI": one question about menstruation, answered under the rules in CycleAsk. Nothing is stored. */
    public function ask(Request $request, \App\Services\Ai\CycleAsk $asker): JsonResponse
    {
        $this->open($request);
        $data = $request->validate(['question' => ['required', 'string', 'min:4', 'max:400']], ['question.min' => 'Tulis pertanyaanmu dulu.', 'question.max' => 'Pertanyaan maksimal 400 karakter.']);
        $user = $request->user();
        $summary = $this->cycle->summary($user, $this->today($request));
        $phases = ['menstrual' => 'haid', 'follicular' => 'folikular', 'fertile' => 'masa subur', 'luteal' => 'luteal'];
        $context = array_filter([
            'rata_siklus_hari' => $summary['stats']['avg_cycle'] ?? null,
            'rata_haid_hari' => $summary['stats']['avg_period'] ?? null,
            'keteraturan' => $summary['stats']['regularity'] ?? null,
            'hari_siklus_sekarang' => $summary['cycleDay'] ?? null,
            'fase_sekarang' => $phases[$summary['phase'] ?? ''] ?? null,
            'haid_berikutnya_dalam_hari' => $summary['nextPeriodIn'] ?? null,
            'mode_hamil' => $summary['pregnancy'] ? true : null,
        ], fn ($v) => $v !== null);

        return response()->json($asker->ask($user, $data['question'], $context));
    }

    public function day(Request $request): JsonResponse
    {
        $this->open($request);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $log = CycleLog::where('user_id', $request->user()->id)->where('logged_on', $data['date'])->first();

        return response()->json(['log' => $log ? $this->logPayload($log) : null]);
    }

    private function logPayload(CycleLog $log): array
    {
        return [
            'flow' => $log->flow, 'moods' => $log->moods ?: ($log->mood ? [$log->mood] : []), 'cramps' => $log->cramps, 'skin' => $log->skin,
            'energy' => $log->energy, 'sleep' => $log->sleep, 'symptoms' => $log->symptoms ?? [], 'note' => $log->note,
            'sex' => $log->sex ?? [], 'discharge' => $log->discharge, 'digestion' => $log->digestion ?? [], 'pregnancy_test' => $log->pregnancy_test,
            'ovulation_test' => $log->ovulation_test, 'activity' => $log->activity ?? [], 'other' => $log->other ?? [], 'pill_taken' => $log->pill_taken,
            'water_ml' => $log->water_ml, 'weight_kg' => $log->weight_kg, 'temperature_c' => $log->temperature_c, 'sleep_hours' => $log->sleep_hours,
        ];
    }

    // ---------------------------------------------------------------- periods

    /** First run: last period, usual lengths and goal. */
    public function setup(Request $request): RedirectResponse
    {
        $this->open($request);
        $user = $request->user();
        $today = $this->today($request)->toDateString();
        $data = $request->validate([
            'last_start' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today, 'after:2000-01-01'],
            'period_length' => ['required', 'integer', 'between:1,12'],
            'cycle_length' => ['required', 'integer', 'between:'.CycleService::MIN_CYCLE.','.CycleService::MAX_CYCLE],
            'goal' => ['required', 'in:track,conceive'],
        ]);

        DB::transaction(function () use ($user, $data) {
            $settings = $this->cycle->settings($user);
            $settings->update(['cycle_length' => $data['cycle_length'], 'period_length' => $data['period_length'], 'goal' => $data['goal'], 'onboarded_at' => now()]);
            if (! empty($data['last_start']) && ! CyclePeriod::where('user_id', $user->id)->where('started_on', $data['last_start'])->exists()) {
                $end = Carbon::parse($data['last_start'])->addDays($data['period_length'] - 1);
                CyclePeriod::create(['user_id' => $user->id, 'started_on' => $data['last_start'], 'ended_on' => $end->lte($this->today(request())) ? $end->toDateString() : null]);
            }
        });

        return back()->with('success', 'Siklusmu siap dilacak.');
    }

    public function startPeriod(Request $request): RedirectResponse
    {
        $this->open($request);
        $user = $request->user();
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->today($request)->toDateString(), 'after:2000-01-01']]);

        if (CyclePeriod::where('user_id', $user->id)->whereNull('ended_on')->exists()) {
            throw ValidationException::withMessages(['date' => 'Menstruasi sebelumnya belum ditandai selesai.']);
        }
        $this->assertNoOverlap($user->id, $data['date'], null);
        CyclePeriod::create(['user_id' => $user->id, 'started_on' => $data['date']]);
        // A new period means the pregnancy mode (if it was on) is over.
        $settings = $this->cycle->settings($user);
        $settings->update(['onboarded_at' => now()] + ($settings->goal === 'pregnancy' ? ['goal' => 'track', 'pregnancy_started_on' => null] : []));

        return back()->with('success', 'Awal menstruasi dicatat.');
    }

    public function endPeriod(Request $request): RedirectResponse
    {
        $this->open($request);
        $user = $request->user();
        $period = CyclePeriod::where('user_id', $user->id)->whereNull('ended_on')->latest('started_on')->first();
        if (! $period) {
            throw ValidationException::withMessages(['date' => 'Tidak ada menstruasi yang sedang berjalan.']);
        }
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$period->started_on->toDateString(), 'before_or_equal:'.$this->today($request)->toDateString()]]);
        if (Carbon::parse($data['date'])->diffInDays($period->started_on) > 14) {
            throw ValidationException::withMessages(['date' => 'Menstruasi lebih dari 14 hari. Periksa tanggalnya.']);
        }
        $period->update(['ended_on' => $data['date']]);

        return back()->with('success', 'Akhir menstruasi dicatat.');
    }

    public function updatePeriod(Request $request, CyclePeriod $period): RedirectResponse
    {
        $this->open($request);
        abort_unless($period->user_id === $request->user()->id, 404);
        $today = $this->today($request)->toDateString();
        $data = $request->validate([
            'started_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today, 'after:2000-01-01'],
            'ended_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:started_on', 'before_or_equal:'.$today],
        ]);
        if ($data['ended_on'] && Carbon::parse($data['ended_on'])->diffInDays($data['started_on']) > 14) {
            throw ValidationException::withMessages(['ended_on' => 'Menstruasi lebih dari 14 hari. Periksa tanggalnya.']);
        }
        $this->assertNoOverlap($request->user()->id, $data['started_on'], $period->id);
        $period->update($data);

        return back()->with('success', 'Riwayat diperbarui.');
    }

    public function destroyPeriod(Request $request, CyclePeriod $period): RedirectResponse
    {
        $this->open($request);
        abort_unless($period->user_id === $request->user()->id, 404);
        $period->delete();

        return back()->with('success', 'Riwayat dihapus.');
    }

    // ---------------------------------------------------------------- daily log

    public function saveLog(Request $request): RedirectResponse
    {
        $this->open($request);
        $in = fn (array $list) => ['in:'.implode(',', $list)];
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->today($request)->toDateString(), 'after:2000-01-01'],
            'flow' => ['nullable', 'integer', 'between:0,3'],
            'moods' => ['nullable', 'array', 'max:6'], 'moods.*' => ['string', ...$in(self::MOODS)],
            'cramps' => ['nullable', 'integer', 'between:0,3'],
            'skin' => ['nullable', ...$in(self::SKIN)],
            'energy' => ['nullable', 'integer', 'between:1,5'],
            'sleep' => ['nullable', 'integer', 'between:1,5'],
            'symptoms' => ['nullable', 'array', 'max:20'], 'symptoms.*' => ['string', ...$in(self::SYMPTOMS)],
            'sex' => ['nullable', 'array', 'max:6'], 'sex.*' => ['string', ...$in(self::SEX)],
            'discharge' => ['nullable', ...$in(self::DISCHARGE)],
            'digestion' => ['nullable', 'array', 'max:5'], 'digestion.*' => ['string', ...$in(self::DIGESTION)],
            'pregnancy_test' => ['nullable', ...$in(self::PREGNANCY_TEST)],
            'ovulation_test' => ['nullable', ...$in(self::OVULATION_TEST)],
            'activity' => ['nullable', 'array', 'max:10'], 'activity.*' => ['string', ...$in(self::ACTIVITY)],
            'other' => ['nullable', 'array', 'max:4'], 'other.*' => ['string', ...$in(self::OTHER)],
            'pill_taken' => ['nullable', 'boolean'],
            'water_ml' => ['nullable', 'integer', 'between:0,10000'],
            'weight_kg' => ['nullable', 'numeric', 'between:20,300'],
            'temperature_c' => ['nullable', 'numeric', 'between:34,42'],
            'sleep_hours' => ['nullable', 'numeric', 'between:0,24'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $date = $data['date'];
        unset($data['date']);
        foreach (['moods', 'symptoms', 'sex', 'digestion', 'activity', 'other'] as $list) {
            $data[$list] = array_values(array_unique($data[$list] ?? []));
        }
        $data['mood'] = $data['moods'][0] ?? null; // kept for the older single-mood column
        foreach (['weight_kg', 'temperature_c', 'sleep_hours'] as $number) {
            if (isset($data[$number])) {
                $data[$number] = round((float) $data[$number], 1);
            }
        }

        $lists = ['moods', 'symptoms', 'sex', 'digestion', 'activity', 'other'];
        $filled = collect($data)->except([...$lists, 'mood'])->filter(fn ($v) => $v !== null && $v !== '' && $v !== false)->isNotEmpty()
            || collect($lists)->contains(fn ($l) => $data[$l] !== []);
        if (! $filled && ($data['pill_taken'] ?? null) !== false) {
            CycleLog::where('user_id', $request->user()->id)->where('logged_on', $date)->delete();

            return back()->with('success', 'Catatan hari itu dikosongkan.');
        }
        CycleLog::updateOrCreate(['user_id' => $request->user()->id, 'logged_on' => $date], $data);

        return back()->with('success', 'Catatan tersimpan.');
    }

    // ---------------------------------------------------------------- settings, reminders, pregnancy

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->open($request);
        $data = $request->validate([
            'cycle_length' => ['required', 'integer', 'between:'.CycleService::MIN_CYCLE.','.CycleService::MAX_CYCLE],
            'period_length' => ['required', 'integer', 'between:1,12'],
            'luteal_length' => ['required', 'integer', 'between:8,18'],
            'birth_control' => ['nullable', 'in:'.implode(',', self::BIRTH_CONTROL)],
            'goal' => ['sometimes', 'in:track,conceive'],
        ]);
        $settings = $this->cycle->settings($request->user());
        if ($settings->goal === 'pregnancy') {
            unset($data['goal']); // pregnancy mode is ended on its own button
        }
        $settings->update($data);

        return back()->with('success', 'Pengaturan siklus tersimpan.');
    }

    public function updateReminders(Request $request): RedirectResponse
    {
        $this->open($request);
        $time = ['required', 'date_format:H:i'];
        $data = $request->validate([
            'period.on' => ['required', 'boolean'], 'period.days_before' => ['required', 'integer', 'between:0,7'],
            'ovulation.on' => ['required', 'boolean'],
            'pill.on' => ['required', 'boolean'], 'pill.time' => $time,
            'water.on' => ['required', 'boolean'],
            'log.on' => ['required', 'boolean'], 'log.time' => $time,
            'discreet' => ['required', 'boolean'],
        ]);
        $this->cycle->settings($request->user())->update(['reminders' => $data]);

        return back()->with('success', 'Pengingat tersimpan.');
    }

    public function startPregnancy(Request $request): RedirectResponse
    {
        $this->open($request);
        $today = $this->today($request);
        $data = $request->validate(['lmp' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString(), 'after:'.$today->copy()->subDays(300)->toDateString()]]);
        $this->cycle->settings($request->user())->update(['goal' => 'pregnancy', 'pregnancy_started_on' => $data['lmp'], 'onboarded_at' => now()]);

        return back()->with('success', 'Mode kehamilan aktif.');
    }

    public function endPregnancy(Request $request): RedirectResponse
    {
        $this->open($request);
        $this->cycle->settings($request->user())->update(['goal' => 'track', 'pregnancy_started_on' => null]);

        return back()->with('success', 'Mode kehamilan dimatikan. Prediksi siklus kembali dihitung.');
    }

    // ---------------------------------------------------------------- PIN

    public function setPin(Request $request): RedirectResponse
    {
        $this->open($request);
        $settings = $this->cycle->settings($request->user());
        $data = $request->validate(['pin' => ['required', 'digits_between:4,6', 'confirmed'], 'current_pin' => [$settings->pin_hash ? 'required' : 'nullable', 'string']]);
        if ($settings->pin_hash && ! Hash::check($data['current_pin'] ?? '', $settings->pin_hash)) {
            throw ValidationException::withMessages(['current_pin' => 'PIN saat ini salah.']);
        }
        $settings->update(['pin_hash' => Hash::make($data['pin'])]);
        $request->session()->put('cycle_unlocked_at', time());

        return back()->with('success', 'PIN dipasang. Bagian ini terkunci otomatis setelah 10 menit tidak dipakai.');
    }

    public function removePin(Request $request): RedirectResponse
    {
        $this->open($request);
        $settings = $this->cycle->settings($request->user());
        $data = $request->validate(['pin' => ['required', 'string']]);
        if (! $settings->pin_hash || ! Hash::check($data['pin'], $settings->pin_hash)) {
            throw ValidationException::withMessages(['pin' => 'PIN salah.']);
        }
        $settings->update(['pin_hash' => null]);
        $request->session()->forget('cycle_unlocked_at');

        return back()->with('success', 'PIN dilepas.');
    }

    /** Wrong guesses are limited: 5 per 5 minutes. */
    public function unlock(Request $request): RedirectResponse
    {
        $this->guard($request);
        $key = 'cycle-pin:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['pin' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        $settings = $this->cycle->settings($request->user());
        $data = $request->validate(['pin' => ['required', 'string']]);
        if (! $settings->pin_hash || ! Hash::check($data['pin'], $settings->pin_hash)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['pin' => 'PIN salah.']);
        }
        RateLimiter::clear($key);
        $request->session()->put('cycle_unlocked_at', time());

        return redirect()->route('cycle.show');
    }

    /** Forgot the PIN: the account password opens it (and removes the PIN); the data stays. */
    public function resetPin(Request $request): RedirectResponse
    {
        $this->guard($request);
        $user = $request->user();
        abort_unless($user->has_password, 422, 'Akun ini belum punya kata sandi. Buat dulu di Pengaturan.');
        $key = 'cycle-pin-reset:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => 'Terlalu banyak percobaan. Coba lagi nanti.']);
        }
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['password' => 'Kata sandi salah.']);
        }
        RateLimiter::clear($key);
        $this->cycle->settings($user)->update(['pin_hash' => null]);
        $request->session()->forget('cycle_unlocked_at');

        return redirect()->route('cycle.show')->with('success', 'PIN dilepas.');
    }

    // ---------------------------------------------------------------- export and wipe

    public function export(Request $request): StreamedResponse
    {
        $this->open($request);
        $id = $request->user()->id;
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'settings' => collect($this->cycle->settings($request->user())->toArray())->except(['id', 'user_id', 'created_at', 'updated_at'])->all(),
            'periods' => CyclePeriod::where('user_id', $id)->orderBy('started_on')->get(['started_on', 'ended_on'])->toArray(),
            'logs' => CycleLog::where('user_id', $id)->orderBy('logged_on')->get()->map(fn ($l) => ['date' => $l->logged_on->toDateString(), ...$this->logPayload($l)])->all(),
        ];

        return response()->streamDownload(fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'siklus-loveynest.json', ['Content-Type' => 'application/json']);
    }

    /** The person's own control over her data: everything here is deleted for good (PIN included). */
    public function wipe(Request $request): RedirectResponse
    {
        $this->open($request);
        $id = $request->user()->id;
        DB::transaction(function () use ($id) {
            CycleLog::where('user_id', $id)->delete();
            CyclePeriod::where('user_id', $id)->delete();
            CycleSetting::where('user_id', $id)->delete();
        });
        $request->session()->forget('cycle_unlocked_at');

        return back()->with('success', 'Semua data siklusmu dihapus.');
    }

    private function assertNoOverlap(int $userId, string $start, ?int $ignore): void
    {
        $query = CyclePeriod::where('user_id', $userId)->where('started_on', $start);
        if ($ignore) {
            $query->where('id', '!=', $ignore);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['date' => 'Tanggal itu sudah tercatat sebagai awal menstruasi.', 'started_on' => 'Tanggal itu sudah tercatat.']);
        }
    }
}
