<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Linked sign-in identities (Google, phone) and one-time codes. Only adds tables and one defaulted column:
// existing accounts keep their password login untouched.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_identities')) {
            Schema::create('user_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 16); // google | phone
                $table->string('provider_id', 191); // Google "sub" or the phone number as 62812...
                $table->string('label', 191)->nullable(); // Google e-mail, shown in Settings
                $table->dateTime('verified_at')->nullable();
                $table->dateTime('last_used_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'provider_id']);
                $table->index(['user_id', 'provider']);
            });
        }

        if (! Schema::hasTable('otp_codes')) {
            Schema::create('otp_codes', function (Blueprint $table) {
                $table->id();
                $table->string('purpose', 8); // login | link
                $table->string('phone', 20);
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('code_hash', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                // dateTime, not timestamp: an old MariaDB would give a NOT NULL timestamp an automatic ON UPDATE that resets the expiry.
                $table->dateTime('expires_at');
                $table->dateTime('consumed_at')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
                $table->index(['phone', 'purpose', 'created_at']);
            });
        }

        if (! Schema::hasColumn('users', 'has_password')) {
            Schema::table('users', function (Blueprint $table) {
                // false for accounts opened with Google: they have a random password nobody knows.
                $table->boolean('has_password')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('user_identities');
        if (Schema::hasColumn('users', 'has_password')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('has_password'));
        }
    }
};
