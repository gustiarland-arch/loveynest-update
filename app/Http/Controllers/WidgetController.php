<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Services\Activity\WidgetData;
use App\Models\Nudge;
use App\Models\Task;
use App\Services\Ai\QuoteWriter;
use App\Services\Chat\ChatService;
use App\Services\Couple\CoupleFacts;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** One compact screen to pin as a shortcut: the day at a glance. */
class WidgetController extends Controller
{
    public function __invoke(Request $request, WidgetData $widgets, ChatService $chat): Response
    {
        $user = $request->user();
        $partner = $user->partner();
        return Inertia::render('Widget', [
            'partner' => $partner ? ['firstName' => $partner->firstName(), 'avatarUrl' => $partner->avatarUrl(), 'avatarFrame' => $partner->avatar_frame, 'gender' => $partner->gender] : null,
            // v2 widgets: days together, countdown, quote, today's tasks, feelings sent today, birthday.
            'extras' => Inertia::defer(fn () => $this->extras($user, $partner), 'extras'),
            // Heavy cards come after the frame (see DashboardController).
            'moods' => Inertia::defer(fn () => $widgets->moods($user), 'cards'),
            'event' => Inertia::defer(function () use ($widgets, $user) {
                $event = $widgets->nextEvent($user);

                return $event ? EventResource::make($event)->resolve() : null;
            }, 'cards'),
            'unreadChat' => Inertia::defer(function () use ($chat, $user, $partner) {
                $conversation = $chat->conversationFor($user);

                return $conversation && $partner ? $chat->unreadCount($conversation, $user) : 0;
            }, 'cards'),
            'photo' => Inertia::defer(fn () => $widgets->latestPhoto($user), 'cards'),
            'letter' => Inertia::defer(fn () => $widgets->latestLetter($user), 'cards'),
        ]);
    }

    private function extras($user, $partner): array
    {
        $facts = app(CoupleFacts::class)->for($user);
        $now = CarbonImmutable::now($user->timezone);
        $start = $now->startOfDay()->utc();
        $end = $now->endOfDay()->utc();
        $tasks = Task::query()->visibleTo($user)->whereNotNull('due_at')->whereBetween('due_at', [$start, $end])->get(['id', 'status']);
        $space = $user->activeSpaceId();
        $nudges = fn ($id) => $id && $space ? Nudge::where('sender_id', $id)->where('couple_space_id', $space)->where('created_at', '>=', $start)->count() : 0;
        $birthday = null;
        if ($partner?->birthday) {
            $next = CarbonImmutable::create($now->year, $partner->birthday->month, $partner->birthday->day, 0, 0, 0, $now->timezone);
            if ($next->lt($now->startOfDay())) {
                $next = $next->addYear();
            }
            $birthday = ['in' => (int) $now->startOfDay()->diffInDays($next), 'date' => $next->toDateString()];
        }

        return [
            'days' => $facts['days'],
            'streak' => $facts['streak'],
            'next' => $facts['next'],
            'quote' => app(QuoteWriter::class)->stored($user),
            'tasks' => ['total' => $tasks->count(), 'done' => $tasks->where('status', 'done')->count()],
            'nudges' => ['mine' => $nudges($user->id), 'theirs' => $nudges($partner?->id)],
            'birthday' => $birthday,
        ];
    }
}
