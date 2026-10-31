<?php

namespace App\Console\Commands;

use App\Services\Health\CycleReminderDispatcher;
use Illuminate\Console\Command;

class DispatchCycleReminders extends Command
{
    protected $signature = 'usandus:siklus-pengingat';

    protected $description = 'Kirim pengingat pelacak siklus (haid, masa subur, pil, air, catatan harian). Dijalankan scheduler tiap 10 menit.';

    public function handle(CycleReminderDispatcher $dispatcher): int
    {
        $result = $dispatcher->run();
        $this->line("terkirim={$result['sent']} dilewati={$result['skipped']}");

        return self::SUCCESS;
    }
}
