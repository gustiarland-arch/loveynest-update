<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Auth\GoogleAuth;
use App\Services\Chat\ChatService;
use App\Services\Notifications\NotificationPreferences;
use App\Support\E2E;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Support for the Android app 2.x, which shows the live website in a WebView (so every cPanel update reaches it at once).
 *
 * 1. Google sign-in: Google refuses sign-in inside an app's WebView, so the app opens /aplikasi/google in Chrome.
 *    After Google, the server hands a one-time token back to the app (loveynest://masuk?token=…), and the app opens
 *    /aplikasi/masuk?token=… in its WebView, which signs the same person in there. The token lives 2 minutes and works once.
 * 2. Notifications: a WebView cannot receive Web Push, so the app asks /aplikasi/kabar every ~15 minutes in the background
 *    and shows what is new. Chat stays locked: only "N pesan baru" is shown, never the text.
 */
class AppBridgeController extends Controller
{
    private const TOKEN_TTL = 120;

    /** Opened in Chrome by the app. Already signed in there: hand over at once; otherwise go through Google first. */
    public function google(Request $request, GoogleAuth $google): RedirectResponse|Response
    {
        if ($request->user()) {
            return $this->handoff($request->user());
        }
        abort_unless(GoogleAuth::configured(), 404);
        $state = Str::random(40);
        $request->session()->put('google_state', $state);
        $request->session()->put('google_mode', 'login');
        $request->session()->put('google_app', true);

        return redirect()->away($google->authUrl($state));
    }

    /** A small page in Chrome that sends the one-time token back into the app. */
    public static function handoff(User $user): Response
    {
        $token = Str::random(48);
        Cache::put('app-login:'.hash('sha256', $token), $user->id, self::TOKEN_TTL);
        $intent = 'intent://masuk?token='.$token.'#Intent;scheme=loveynest;package='.config('usandus.android.package').';end';
        $plain = 'loveynest://masuk?token='.$token;
        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Kembali ke LoveyNest</title><style>body{font-family:system-ui,sans-serif;background:#fff6f9;color:#3a1530;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:24px}'
            .'a{display:inline-block;margin-top:16px;padding:14px 26px;border-radius:999px;background:#e0245e;color:#fff;font-weight:700;text-decoration:none}</style></head><body><div>'
            .'<h1 style="font-size:1.3rem">Berhasil masuk 💗</h1><p>Ketuk tombol di bawah untuk kembali ke aplikasi LoveyNest.</p>'
            .'<a id="go" href="'.e($intent).'">Buka LoveyNest</a><p style="font-size:.85rem;opacity:.7;margin-top:18px">Tombol berlaku 2 menit.</p></div>'
            .'<script>setTimeout(function(){location.href='.json_encode($intent).'},300);document.getElementById("go").addEventListener("click",function(e){if(!/Android/.test(navigator.userAgent)){e.preventDefault();location.href='.json_encode($plain).'}})</script>'
            .'</body></html>';

        return response($html)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    /** Opened inside the app's WebView with the token from Chrome. */
    public function masuk(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $id = strlen($token) === 48 ? Cache::pull('app-login:'.hash('sha256', $token)) : null;
        $user = $id ? User::find($id) : null;
        if (! $user) {
            return redirect()->route('login')->with('error', 'Tautan masuk sudah tidak berlaku. Coba masuk dengan Google sekali lagi.');
        }
        Auth::login($user, true);
        $request->session()->regenerate();
        Log::channel('audit')->info('login.google.app', ['user' => $user->id, 'ip' => $request->ip()]);

        return redirect()->route(! $user->profile_completed_at ? 'profile.setup' : ($user->activeSpaceId() ? 'dashboard' : 'onboarding'));
    }

    /**
     * What is new since the cursor the app kept ("<ms>.<chat id>"). The first ask only returns a cursor, so a fresh
     * install never floods the phone with old news.
     */
    public function kabar(Request $request, ChatService $chat): JsonResponse
    {
        $user = $request->user();
        $raw = (string) $request->query('c', '');
        $since = 0;
        $chatSince = 0;
        if (preg_match('/^(\d{1,15})\.(\d{1,12})$/', $raw, $m)) {
            [$since, $chatSince] = [(int) $m[1], (int) $m[2]];
        }
        $now = (int) floor(microtime(true) * 1000);
        $spaceId = $user->activeSpaceId();
        $conversation = $spaceId ? ChatConversation::where('couple_space_id', $spaceId)->first() : null;
        $lastChat = $conversation ? (int) $conversation->messages()->max('id') : 0;
        $cursor = $now.'.'.$lastChat;

        if ($raw === '' || $since === 0) {
            return response()->json(['items' => [], 'cursor' => $cursor])->header('Cache-Control', 'no-store');
        }

        $items = [];
        $partner = $user->partner();

        $ringing = $spaceId ? Cache::get('call.space.'.$spaceId) : null;
        if (is_array($ringing) && ($ringing['status'] ?? null) === 'ringing' && ($ringing['callee'] ?? null) === $user->id && time() - (int) ($ringing['created'] ?? 0) < 45) {
            $items[] = ['id' => 'call-'.$ringing['id'], 'title' => ($partner?->firstName() ?? 'Pasanganmu').' menelepon 📞', 'body' => 'Buka LoveyNest untuk mengangkat.', 'url' => '/chat', 'tag' => 'call', 'snd' => 'pengingat'];
        }

        if ($conversation && $partner) {
            $from = max($chatSince, $chat->lastReadId($conversation, $user->id));
            $fresh = $conversation->messages()->where('sender_id', $partner->id)->where('id', '>', $from);
            $count = (clone $fresh)->count();
            if ($count > 0) {
                $item = ['id' => 'chat-'.$lastChat, 'title' => $partner->firstName().' mengirim pesan 💬', 'body' => $count === 1 ? '1 pesan baru' : $count.' pesan baru', 'url' => '/chat', 'tag' => 'chat', 'snd' => 'kabar'];
                // With previews on, the newest message goes along still locked: only this phone (which holds the couple key) can read it.
                if (app(NotificationPreferences::class)->for($user, 'chat_message')['show_preview'] && ($last = (clone $fresh)->latest('id')->first())) {
                    $preview = ChatService::preview($last);
                    if (E2E::isSealed($preview)) {
                        if (strlen($preview) <= 1800) {
                            $item['ct'] = $preview;
                            $item['more'] = $count;
                        }
                    } elseif ($preview !== '') {
                        $item['body'] = Str::limit($preview, 80).($count > 1 ? '  (+'.($count - 1).' pesan lagi)' : '');
                    }
                }
                $items[] = $item;
            }
        }

        $rows = $user->notifications()->whereNull('read_at')->where('created_at', '>', Carbon::createFromTimestampMs($since))->latest()->limit(5)->get();
        foreach ($rows as $row) {
            $data = (array) $row->data;
            $items[] = [
                'id' => (string) $row->id,
                'title' => Str::limit((string) ($data['title'] ?? 'LoveyNest'), 80),
                'body' => Str::limit((string) ($data['body'] ?? ''), 160),
                'url' => is_string($data['url'] ?? null) && str_starts_with($data['url'], '/') ? $data['url'] : '/dashboard',
                'tag' => (string) ($data['type'] ?? 'info'),
                'snd' => in_array($data['snd'] ?? null, ['peluk', 'cium', 'kangen', 'semangat', 'selesai', 'kabar', 'pengingat'], true) ? $data['snd'] : 'kabar',
            ];
        }

        return response()->json(['items' => $items, 'cursor' => $cursor])->header('Cache-Control', 'no-store');
    }
}
