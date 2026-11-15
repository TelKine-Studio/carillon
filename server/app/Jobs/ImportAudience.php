<?php

namespace App\Jobs;

use App\Audience\OneSignalImport;
use App\Models\AudienceImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportAudience implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $import = AudienceImport::query()->with('project')->find($this->importId);
        if (! $import || $import->status !== 'queued') {
            return;
        }

        $import->forceFill(['status' => 'running', 'started_at' => now()])->save();
        $reader = new OneSignalImport($import->project);

        try {
            $reader->file(Storage::disk('local')->path($import->path), fn (array $counts) => $import->forceFill($counts)->save());
            $import->forceFill($reader->counts + ['status' => 'done', 'notes' => $reader->reasons ?: null, 'completed_at' => now()])->save();
        } catch (Throwable $e) {
            $import->forceFill($reader->counts + ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250), 'notes' => $reader->reasons ?: null, 'completed_at' => now()])->save();
        } finally {
            Storage::disk('local')->delete($import->path);
        }
    }
}
