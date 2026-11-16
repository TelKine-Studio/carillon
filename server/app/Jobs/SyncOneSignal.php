<?php

namespace App\Jobs;

use App\Models\AudienceImport;
use App\Models\OneSignalSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports the current subscribers of a OneSignal app: asks OneSignal for a
 * CSV export, then (the scheduler runs it again every minute while the
 * file is generated) downloads it and hands it to the regular import. New installs of an app still running the OneSignal
 * SDK reach Carillon this way.
 */
class SyncOneSignal implements ShouldQueue
{
    use Queueable;

    public const API = 'https://api.onesignal.com/players/csv_export';

    /** The export file is given up after this many minutes. */
    public const PATIENCE = 60;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $syncId) {}

    public function handle(): void
    {
        $sync = OneSignalSync::query()->with('project')->find($this->syncId);
        if (! $sync) {
            return;
        }

        $lock = Cache::lock('onesignal-sync-'.$sync->id, $this->timeout);
        if (! $lock->get()) {
            return;
        }

        try {
            $sync->pending_url ? $this->download($sync) : $this->request($sync);
        } catch (Throwable $e) {
            $sync->forceFill(['pending_url' => null, 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
        } finally {
            $lock->release();
        }
    }

    protected function request(OneSignalSync $sync): void
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->withHeaders(['Authorization' => self::authorization($sync->api_key)])
            ->post(self::API.'?app_id='.urlencode($sync->app_id), [
                'extra_fields' => ['external_user_id', 'timezone_id', 'country'],
            ]);

        $url = $response->json('csv_file_url');
        if ($response->failed() || ! is_string($url) || $url === '') {
            $sync->forceFill(['pending_url' => null, 'last_error' => self::error($response)])->save();

            return;
        }

        $sync->forceFill(['pending_url' => $url, 'requested_at' => now(), 'last_error' => null])->save();
    }

    protected function download(OneSignalSync $sync): void
    {
        $path = 'imports/'.bin2hex(random_bytes(16)).'.csv.gz';
        Storage::disk('local')->makeDirectory('imports');
        $full = Storage::disk('local')->path($path);

        $response = Http::timeout(600)->sink($full)->get($sync->pending_url);

        if ($response->successful() && filesize($full) > 0) {
            $import = AudienceImport::query()->create([
                'project_id' => $sync->project_id,
                'source' => 'onesignal-sync',
                'filename' => __('Automatic sync with OneSignal'),
                'path' => $path,
            ]);
            ImportAudience::dispatch($import->id);
            $sync->forceFill(['pending_url' => null, 'last_synced_at' => now(), 'last_error' => null])->save();

            return;
        }

        @unlink($full);

        // OneSignal answers 403/404 until the file is generated: the next
        // scheduler run tries again.
        if ($sync->requested_at?->gt(now()->subMinutes(self::PATIENCE))) {
            return;
        }

        $sync->forceFill(['pending_url' => null, 'last_error' => __('OneSignal did not deliver the export file within an hour.')])->save();
    }

    /** Keys of the current OneSignal API start with os_v2_; older ones use Basic. */
    public static function authorization(string $key): string
    {
        return (str_starts_with($key, 'os_v2_') ? 'Key ' : 'Basic ').$key;
    }

    protected static function error(Response $response): string
    {
        $errors = $response->json('errors');
        $message = is_array($errors) ? implode(' ', array_map(fn ($e) => is_string($e) ? $e : json_encode($e), $errors)) : (is_string($errors) ? $errors : null);

        return mb_substr(__('OneSignal refused the export (:status).', ['status' => $response->status()]).($message ? ' '.$message : ''), 0, 500);
    }
}
