<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Period tracker v2: more things to log (sex, discharge, digestion, tests, activity, water, weight, temperature, sleep hours,
// pill), a goal and birth control, reminders, an optional PIN and pregnancy mode. Only adds columns; nothing is changed or removed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('cycle_settings', 'goal')) {
                $table->string('goal', 12)->default('track'); // track | conceive | pregnancy
            }
            if (! Schema::hasColumn('cycle_settings', 'birth_control')) {
                $table->string('birth_control', 16)->nullable(); // pill | iud | implant | injection | condom | none
            }
            if (! Schema::hasColumn('cycle_settings', 'pin_hash')) {
                $table->string('pin_hash')->nullable();
            }
            if (! Schema::hasColumn('cycle_settings', 'reminders')) {
                $table->json('reminders')->nullable();
            }
            if (! Schema::hasColumn('cycle_settings', 'pregnancy_started_on')) {
                $table->date('pregnancy_started_on')->nullable(); // first day of the last period
            }
            if (! Schema::hasColumn('cycle_settings', 'onboarded_at')) {
                $table->dateTime('onboarded_at')->nullable();
            }
        });

        Schema::table('cycle_logs', function (Blueprint $table) {
            foreach (['moods', 'sex', 'digestion', 'activity', 'other'] as $json) {
                if (! Schema::hasColumn('cycle_logs', $json)) {
                    $table->json($json)->nullable();
                }
            }
            if (! Schema::hasColumn('cycle_logs', 'discharge')) {
                $table->string('discharge', 16)->nullable();
            }
            if (! Schema::hasColumn('cycle_logs', 'pregnancy_test')) {
                $table->string('pregnancy_test', 12)->nullable();
            }
            if (! Schema::hasColumn('cycle_logs', 'ovulation_test')) {
                $table->string('ovulation_test', 12)->nullable();
            }
            if (! Schema::hasColumn('cycle_logs', 'pill_taken')) {
                $table->boolean('pill_taken')->nullable();
            }
            if (! Schema::hasColumn('cycle_logs', 'water_ml')) {
                $table->unsignedSmallInteger('water_ml')->nullable();
            }
            if (! Schema::hasColumn('cycle_logs', 'weight_kg')) {
                $table->decimal('weight_kg', 4, 1)->nullable();
            }
            if (! Schema::hasColumn('cycle_logs', 'temperature_c')) {
                $table->decimal('temperature_c', 3, 1)->nullable();
            }
            if (! Schema::hasColumn('cycle_logs', 'sleep_hours')) {
                $table->decimal('sleep_hours', 3, 1)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cycle_logs', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['moods', 'sex', 'digestion', 'activity', 'other', 'discharge', 'pregnancy_test', 'ovulation_test', 'pill_taken', 'water_ml', 'weight_kg', 'temperature_c', 'sleep_hours'],
                fn ($c) => Schema::hasColumn('cycle_logs', $c),
            )));
        });
        Schema::table('cycle_settings', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['goal', 'birth_control', 'pin_hash', 'reminders', 'pregnancy_started_on', 'onboarded_at'],
                fn ($c) => Schema::hasColumn('cycle_settings', $c),
            )));
        });
    }
};
