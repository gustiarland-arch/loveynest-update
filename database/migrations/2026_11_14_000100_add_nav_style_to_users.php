<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bottom navigation style chosen in Settings; follows the account to the PWA and the Android app. Only adds a column.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'nav_style')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('nav_style', 16)->default('floating');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'nav_style')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('nav_style'));
        }
    }
};
