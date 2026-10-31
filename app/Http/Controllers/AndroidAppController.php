<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Download page, version check and file of the Android app (APK). All public: the file holds no private data. */
class AndroidAppController extends Controller
{
    private function path(): string
    {
        return storage_path('app/'.config('usandus.android.file'));
    }

    /** @return array{code: int, name: string, notes: string, size: int, sha256: string, url: string}|null */
    public static function info(): ?array
    {
        $path = storage_path('app/'.config('usandus.android.file'));
        if (! is_file($path)) {
            return null;
        }

        // Hashing 7 MB on every check is wasteful; the file only changes when a new one is uploaded.
        $sha = Cache::remember('android.apk.sha.'.filemtime($path).'.'.filesize($path), now()->addDay(), fn () => hash_file('sha256', $path));

        return [
            'code' => (int) config('usandus.android.version_code'),
            'name' => (string) config('usandus.android.version_name'),
            'notes' => (string) config('usandus.android.notes'),
            'size' => filesize($path),
            'sha256' => $sha,
            'url' => url('/aplikasi/LoveyNest.apk'),
        ];
    }

    public function page(): \Illuminate\Contracts\View\View
    {
        return view('aplikasi', ['info' => self::info()]);
    }

    /**
     * Which web build is on the server. Every open app (browser, installed PWA, Android app) asks this every couple of minutes,
     * so the first ask after a new build was uploaded also triggers the one-time "new version" notification to everyone
     * (usandus:kabari-build). That way it does not wait for the scheduler/cron.
     */
    public function build(): JsonResponse
    {
        $id = \App\Support\BuildId::current();
        if ($id !== 'dev' && Cache::get('usandus.build.seen') !== $id && Cache::add('usandus.build.announcing', 1, 120)) {
            defer(function () {
                try {
                    \Illuminate\Support\Facades\Artisan::call('usandus:kabari-build');
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return response()->json(['build' => $id])->header('Cache-Control', 'no-store');
    }

    public function version(): JsonResponse
    {
        $info = self::info();
        $response = $info ? response()->json($info) : response()->json(['message' => 'Belum ada berkas aplikasi.'], 404);

        // Public facts about the newest APK; the promo site (another subdomain) reads them to show the version and size.
        return $response->header('Access-Control-Allow-Origin', '*')->header('Cache-Control', 'public, max-age=300');
    }

    public function apk(Request $request): BinaryFileResponse
    {
        abort_unless(is_file($this->path()), 404);

        return response()->download($this->path(), 'LoveyNest.apk', ['Content-Type' => 'application/vnd.android.package-archive']);
    }
}
