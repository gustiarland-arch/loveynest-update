<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CyclePeriod extends Model
{
    protected $guarded = ['id']; // user_id is always set from the signed-in user, never from input

    protected function casts(): array
    {
        return ['started_on' => 'date:Y-m-d', 'ended_on' => 'date:Y-m-d'];
    }
}
