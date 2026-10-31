<?php

namespace App\Models;

use App\Casts\Sealed;
use App\Casts\SealedJson;
use Illuminate\Database\Eloquent\Model;

class CycleLog extends Model
{
    protected $guarded = ['id']; // user_id is always set from the signed-in user, never from input

    protected function casts(): array
    {
        return [
            'logged_on' => 'date:Y-m-d',
            // Health notes are kept scrambled in the database (see Casts\Sealed). Numbers and dates stay readable: the predictions need them.
            'symptoms' => SealedJson::class, 'moods' => SealedJson::class, 'sex' => SealedJson::class, 'digestion' => SealedJson::class, 'activity' => SealedJson::class, 'other' => SealedJson::class,
            'mood' => Sealed::class, 'skin' => Sealed::class, 'note' => Sealed::class, 'discharge' => Sealed::class, 'pregnancy_test' => Sealed::class, 'ovulation_test' => Sealed::class,
            'pill_taken' => 'boolean',
            'weight_kg' => 'float', 'temperature_c' => 'float', 'sleep_hours' => 'float',
        ];
    }
}
