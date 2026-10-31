<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\IncomingCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Voice calls between the two of a couple, from the chat. The phones talk directly (WebRTC); the server only keeps
 * who is calling whom (one call per couple, in the cache, no table) and passes the small connection notes.
 *
 * Life of a call: ringing (45 s) → active → ended. Ended carries a reason: declined | missed | cancelled | hangup | lost
 * (the other phone stopped answering for 25 s). An ended call stays visible for 15 s so both screens can close it.
 */
class CallController extends Controller
{
    private const RING_SECONDS = 45;

    private const LOST_SECONDS = 25;

    private function partnerOf(Request $request): User
    {
        $partner = $request->user()->partner();
        abort_unless($partner, 422, 'Ajak pasanganmu bergabung dulu.');

        return $partner;
    }

    private function key(Request $request): string
    {
        return 'call.space.'.$request->user()->activeSpaceId();
    }

    /** The current state, with time-outs applied (a ringing call that nobody answered becomes "missed"). */
    private function current(string $key): ?array
    {
        $c = Cache::get($key);
        if (! $c) {
            return null;
        }
        $now = time();
        if ($c['status'] === 'ringing' && $now - $c['created'] > self::RING_SECONDS) {
            $c = $this->finish($key, $c, 'missed');
        } elseif ($c['status'] === 'active') {
            $quiet = min($c['seen'][$c['caller']] ?? $now, $c['seen'][$c['callee']] ?? $now);
            if ($now - $quiet > self::LOST_SECONDS) {
                $c = $this->finish($key, $c, 'lost');
            }
        }
        if ($c['status'] === 'ended' && $now - $c['ended'] > 15) {
            return null;
        }

        return $c;
    }

    private function finish(string $key, array $c, string $reason): array
    {
        if ($c['status'] === 'ended') {
            return $c;
        }
        $c['status'] = 'ended';
        $c['reason'] = $reason;
        $c['ended'] = time();
        $c['duration'] = $c['answered'] ? $c['ended'] - $c['answered'] : 0;
        Cache::put($key, $c, now()->addMinutes(2));

        return $c;
    }

    /** What one person sees of the call. */
    private function view(?array $c, User $me): ?array
    {
        if (! $c || ! in_array($me->id, [$c['caller'], $c['callee']], true)) {
            return null;
        }

        return [
            'id' => $c['id'], 'status' => $c['status'], 'role' => $c['caller'] === $me->id ? 'caller' : 'callee',
            'reason' => $c['reason'] ?? null, 'video' => $c['video'], 'duration' => $c['duration'] ?? 0,
            'elapsed' => $c['answered'] ? time() - $c['answered'] : 0, 'ringing' => $c['status'] === 'ringing' ? time() - $c['created'] : 0,
        ];
    }

    private function mine(Request $request, int $id): array
    {
        $c = $this->current($this->key($request));
        abort_unless($c && $c['id'] === $id && in_array($request->user()->id, [$c['caller'], $c['callee']], true), 404);

        return $c;
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json(['call' => $this->view($this->current($this->key($request)), $request->user())])->header('Cache-Control', 'no-store');
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate(['video' => ['nullable', 'boolean']]);
        $user = $request->user();
        $partner = $this->partnerOf($request);
        $key = $this->key($request);

        $result = Cache::lock($key.'.lock', 5)->block(3, function () use ($key, $user, $partner, $data) {
            $c = $this->current($key);
            if ($c && $c['status'] !== 'ended') {
                return ['busy' => $this->view($c, $user)];
            }
            $c = [
                'id' => (int) (microtime(true) * 1000) % 2000000000, 'caller' => $user->id, 'callee' => $partner->id, 'status' => 'ringing',
                'video' => (bool) ($data['video'] ?? false), 'created' => time(), 'answered' => null, 'ended' => null, 'reason' => null, 'seen' => [$user->id => time()],
            ];
            Cache::put($key, $c, now()->addHours(4));

            return ['call' => $c];
        });

        if (isset($result['busy'])) {
            return response()->json(['message' => 'Sedang ada panggilan.', 'call' => $result['busy']], 409);
        }
        try {
            $partner->notify(new IncomingCall($user->firstName(), $result['call']['video']));
        } catch (Throwable $e) {
            Log::warning('notification.failed', ['type' => 'call', 'error' => $e::class]);
        }

        return response()->json(['call' => $this->view($result['call'], $user)]);
    }

    public function accept(Request $request, int $call): JsonResponse
    {
        return $this->change($request, $call, function (array $c, User $me, string $key) {
            abort_unless($c['callee'] === $me->id && $c['status'] === 'ringing', 409, 'Panggilan sudah berakhir.');
            $c['status'] = 'active';
            $c['answered'] = time();
            $c['seen'] = [$c['caller'] => time(), $c['callee'] => time()];
            Cache::put($key, $c, now()->addHours(4));

            return $c;
        });
    }

    public function decline(Request $request, int $call): JsonResponse
    {
        return $this->change($request, $call, fn (array $c, User $me, string $key) => $this->finish($key, $c, 'declined'));
    }

    public function hangup(Request $request, int $call): JsonResponse
    {
        return $this->change($request, $call, function (array $c, User $me, string $key) {
            $reason = $c['status'] === 'ringing' ? ($c['caller'] === $me->id ? 'cancelled' : 'declined') : 'hangup';

            return $this->finish($key, $c, $reason);
        });
    }

    private function change(Request $request, int $id, callable $fn): JsonResponse
    {
        $key = $this->key($request);
        $me = $request->user();
        $c = Cache::lock($key.'.lock', 5)->block(3, function () use ($request, $id, $fn, $me, $key) {
            return $fn($this->mine($request, $id), $me, $key);
        });

        return response()->json(['call' => $this->view($c, $me)]);
    }

    /** A connection note (offer, answer, ice, bye) for the other phone. */
    public function signal(Request $request, int $call): JsonResponse
    {
        $c = $this->mine($request, $call);
        $data = $request->validate(['type' => ['required', 'in:offer,answer,ice,bye,restart'], 'data' => ['nullable', 'string', 'max:16000']]);
        $other = $c['caller'] === $request->user()->id ? $c['callee'] : $c['caller'];
        $key = 'call.sig.'.$c['id'].'.'.$other;
        Cache::lock($key.'.lock', 3)->block(2, function () use ($key, $data) {
            $list = Cache::get($key, ['n' => 0, 'items' => []]);
            $list['n']++;
            $list['items'][] = ['id' => $list['n'], 'type' => $data['type'], 'data' => $data['data'] ?? null];
            $list['items'] = array_slice($list['items'], -80);
            Cache::put($key, $list, now()->addMinutes(10));
        });

        return response()->json(['ok' => true]);
    }

    /** Notes for me since `after`. Also the heartbeat: a phone that stops asking for 25 s has left the call. */
    public function signals(Request $request, int $call): JsonResponse
    {
        $key = $this->key($request);
        $me = $request->user();
        $c = Cache::lock($key.'.lock', 5)->block(3, function () use ($request, $call, $key, $me) {
            $c = $this->mine($request, $call);
            if ($c['status'] !== 'ended') {
                $c['seen'][$me->id] = time();
                Cache::put($key, $c, now()->addHours(4));
            }

            return $c;
        });
        $after = max(0, (int) $request->query('after', 0));
        $list = Cache::get('call.sig.'.$c['id'].'.'.$me->id, ['n' => 0, 'items' => []]);

        return response()->json([
            'call' => $this->view($c, $me),
            'last' => $list['n'],
            'items' => array_values(array_filter($list['items'], fn ($i) => $i['id'] > $after)),
        ])->header('Cache-Control', 'no-store');
    }
}
