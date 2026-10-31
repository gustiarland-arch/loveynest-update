<?php

namespace App\Models;

use App\Casts\SealedJson;
use Illuminate\Notifications\DatabaseNotification;

/** Database notification with millisecond timestamps, so newest-first is exact. */
class AppNotification extends DatabaseNotification
{
    /** The title and text of each notification are kept scrambled in the database (see Casts\SealedJson). */
    public function casts(): array
    {
        return array_merge(parent::casts(), ['data' => SealedJson::class]);
    }

    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** What the UI needs; the stored URL is only used server-side by "open". */
    public function toView(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? $this->type,
            'title' => $this->data['title'] ?? '',
            'body' => $this->data['body'] ?? '',
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
