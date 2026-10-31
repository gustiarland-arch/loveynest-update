<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentity extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "6281234567890" shown as "+62 812-3456-7890" style without revealing all digits. */
    public function maskedLabel(): string
    {
        if ($this->provider === 'phone') {
            $p = $this->provider_id;

            return '+'.substr($p, 0, 2).' '.str_repeat('•', max(0, strlen($p) - 6)).substr($p, -4);
        }

        return (string) ($this->label ?: 'Google');
    }
}
