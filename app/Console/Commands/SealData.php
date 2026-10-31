<?php

namespace App\Console\Commands;

use App\Casts\Sealed;
use App\Support\FileVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scrambles private content that older versions stored as plain text (see App\Casts\Sealed and App\Support\FileVault):
 * health notes, notification texts, profile photos and backdrops. Safe to run again: what is already scrambled is skipped.
 */
class SealData extends Command
{
    protected $signature = 'usandus:segel-data';

    protected $description = 'Mengunci (enkripsi at-rest) catatan siklus, teks notifikasi, foto profil, dan foto latar yang masih tersimpan polos.';

    /** table => columns */
    private const COLUMNS = [
        'cycle_logs' => ['mood', 'skin', 'symptoms', 'note', 'moods', 'sex', 'digestion', 'activity', 'other', 'discharge', 'pregnancy_test', 'ovulation_test'],
        'cycle_settings' => ['goal', 'birth_control', 'reminders'],
    ];

    public function handle(): int
    {
        $rows = 0;
        foreach (self::COLUMNS as $table => $columns) {
            $rows += $this->sealTable($table, 'id', $columns);
        }
        $rows += $this->sealTable('notifications', 'id', ['data']);
        $this->info("Baris terkunci: {$rows}");

        $files = 0;
        foreach (DB::table('users')->whereNotNull('avatar_path')->pluck('avatar_path') as $path) {
            $files += $this->file($path);
        }
        foreach (DB::table('users')->whereNotNull('background_path')->pluck('background_path') as $path) {
            $files += $this->file($path) + $this->file(preg_replace('/\.jpg$/', '-t.jpg', $path));
        }
        $this->info("Berkas terkunci: {$files}");

        return self::SUCCESS;
    }

    /** @param list<string> $columns */
    private function sealTable(string $table, string $key, array $columns): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
        if ($columns === []) {
            return 0;
        }
        $changed = 0;
        DB::table($table)->select([$key, ...$columns])->orderBy($key)->chunk(200, function ($chunk) use ($table, $key, $columns, &$changed) {
            foreach ($chunk as $row) {
                $update = [];
                foreach ($columns as $column) {
                    $value = $row->{$column};
                    if ($value !== null && $value !== '' && ! str_starts_with((string) $value, Sealed::PREFIX)) {
                        $update[$column] = Sealed::lock((string) $value);
                    }
                }
                if ($update !== []) {
                    DB::table($table)->where($key, $row->{$key})->update($update);
                    $changed++;
                }
            }
        });

        return $changed;
    }

    private function file(string $path): int
    {
        $before = (string) \Illuminate\Support\Facades\Storage::disk('local')->get($path);
        if ($before === '' || str_starts_with($before, FileVault::MAGIC)) {
            return 0;
        }
        FileVault::seal($path);

        return 1;
    }
}
