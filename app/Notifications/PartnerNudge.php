<?php

namespace App\Notifications;

class PartnerNudge extends CoupleNotification
{
    public function __construct(private string $actorName, private string $kind) {}

    public function type(): string
    {
        return 'partner_activity';
    }

    public function topic(): ?string
    {
        return 'nudge';
    }

    public function sound(): string
    {
        return in_array($this->kind, ['peluk', 'cium', 'kangen', 'semangat'], true) ? $this->kind : 'kabar';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.nudge.'.$this->kind, ['name' => $this->actorName]),
            'body' => __('notifications.nudge_body'),
            'url' => '/dashboard',
        ];
    }
}
