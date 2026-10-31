<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Push\WebPushSender;

/**
 * A reminder from the cycle tracker (period coming, fertile window, pill, water, daily log). Always for the signed-in woman herself.
 * With the "discreet" setting on (the default) the text on the lock screen only says there is a reminder, not what it is about.
 */
class CycleReminder extends CoupleNotification
{
    public function __construct(private string $kind, private bool $discreet = true, private array $params = []) {}

    public function type(): string
    {
        return 'cycle_reminder';
    }

    public function topic(): ?string
    {
        return 'cycle-'.$this->kind;
    }

    /** Its own switches live in the tracker (Pengaturan siklus), so the general notification preferences are not consulted. */
    public function via(User $notifiable): array
    {
        $channels = ['database'];
        if (WebPushSender::isConfigured() && $notifiable->pushSubscriptions()->active()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    protected function content(): array
    {
        if ($this->discreet) {
            return ['title' => 'LoveyNest', 'body' => __('cycle.reminder.discreet'), 'url' => '/kesehatan/siklus'];
        }

        return ['title' => __("cycle.reminder.{$this->kind}.title"), 'body' => __("cycle.reminder.{$this->kind}.body", $this->params), 'url' => '/kesehatan/siklus'];
    }
}
