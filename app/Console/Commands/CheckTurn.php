<?php

namespace App\Console\Commands;

use App\Support\IceServers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Shows which ICE servers calls and Arena voice will use, and whether a TURN relay is set up. */
class CheckTurn extends Command
{
    protected $signature = 'usandus:cek-turn';

    protected $description = 'Cek server relay (TURN) untuk telepon dan suara Arena.';

    public function handle(): int
    {
        Cache::forget('rtc.ice.v1');
        $servers = IceServers::get();
        foreach ($servers as $s) {
            $this->line('- '.implode(', ', (array) $s['urls']).(isset($s['username']) ? '  (dengan kredensial)' : ''));
        }
        if (! IceServers::hasRelay()) {
            $this->warn('Belum ada TURN. Telepon dan suara Arena bisa macet di "menyambungkan" saat memakai data seluler.');
            $this->warn('Isi CLOUDFLARE_TURN_KEY_ID + CLOUDFLARE_TURN_API_TOKEN (atau METERED_*, atau TURN_URL) di .env, lalu jalankan perintah ini lagi.');

            return self::FAILURE;
        }
        $this->info('TURN aktif. Telepon dan suara Arena bisa lewat relay bila jalur langsung gagal.');

        return self::SUCCESS;
    }
}
