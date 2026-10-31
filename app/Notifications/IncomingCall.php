<?php

namespace App\Notifications;

/** "Hansen menelepon" on the partner's phone, also when LoveyNest is closed. Tapping it opens the chat, where the call rings. */
class IncomingCall extends CoupleNotification
{
    public function __construct(private string $callerName, private bool $video = false) {}

    public function type(): string
    {
        return 'couple_update';
    }

    public function topic(): ?string
    {
        return 'call';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.call_title', ['name' => $this->callerName]),
            'body' => __('notifications.call_body'),
            'url' => '/chat',
        ];
    }
}
