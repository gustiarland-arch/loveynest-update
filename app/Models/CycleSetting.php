<?php

namespace App\Models;

use App\Casts\Sealed;
use App\Casts\SealedJson;
use Illuminate\Database\Eloquent\Model;

class CycleSetting extends Model
{
    protected $guarded = ['id']; // user_id is always set from the signed-in user, never from input

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['reminders' => SealedJson::class, 'goal' => Sealed::class, 'birth_control' => Sealed::class, 'pregnancy_started_on' => 'date:Y-m-d', 'onboarded_at' => 'datetime'];
    }

    /** Reminder preferences with defaults filled in. */
    public function reminderPrefs(): array
    {
        return array_replace_recursive([
            'period' => ['on' => false, 'days_before' => 2],
            'ovulation' => ['on' => false],
            'pill' => ['on' => false, 'time' => '20:00'],
            'water' => ['on' => false],
            'log' => ['on' => false, 'time' => '21:00'],
            // Discreet: the notification does not say what it is about (shows on the lock screen).
            'discreet' => true,
        ], $this->reminders ?? []);
    }
}
