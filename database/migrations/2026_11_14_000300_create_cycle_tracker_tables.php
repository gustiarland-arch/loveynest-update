<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Period and ovulation tracker. New tables only. Every row belongs to one user and is never shared with the partner.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cycle_settings')) {
            Schema::create('cycle_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('cycle_length')->default(28);
                $table->unsignedTinyInteger('period_length')->default(5);
                $table->unsignedTinyInteger('luteal_length')->default(14);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cycle_periods')) {
            Schema::create('cycle_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('started_on');
                $table->date('ended_on')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'started_on']);
            });
        }

        if (! Schema::hasTable('cycle_logs')) {
            Schema::create('cycle_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('logged_on');
                $table->unsignedTinyInteger('flow')->nullable(); // 0 none, 1 light, 2 medium, 3 heavy
                $table->string('mood', 16)->nullable();
                $table->unsignedTinyInteger('cramps')->nullable(); // 0..3
                $table->string('skin', 16)->nullable();
                $table->unsignedTinyInteger('energy')->nullable(); // 1..5
                $table->unsignedTinyInteger('sleep')->nullable(); // 1..5
                $table->json('symptoms')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'logged_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_logs');
        Schema::dropIfExists('cycle_periods');
        Schema::dropIfExists('cycle_settings');
    }
};
