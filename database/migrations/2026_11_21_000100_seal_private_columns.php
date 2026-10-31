<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Columns that hold scrambled text from now on (see App\Casts\Sealed): longer than the plain text, so they become TEXT. */
    public const COLUMNS = [
        'cycle_logs' => ['mood', 'skin', 'symptoms', 'note', 'moods', 'sex', 'digestion', 'activity', 'other', 'discharge', 'pregnancy_test', 'ovulation_test'],
        'cycle_settings' => ['goal', 'birth_control', 'reminders'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->longText($column)->nullable()->change();
                    }
                }
            });
        }

        // Scramble what is already stored. Safe to run again; if it fails here, run `php artisan usandus:segel-data` by hand.
        try {
            Artisan::call('usandus:segel-data');
        } catch (Throwable $e) {
            Log::warning('seal.migration_failed', ['error' => $e::class]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: scrambled text is read back as it is.
    }
};
