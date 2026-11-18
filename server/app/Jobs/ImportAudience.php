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
            $this->fail($import, $e->getMessage(), $reader);
        } finally {
            Storage::disk('local')->delete($import->path);
        }
    }

    /** The worker gave up on the job (killed, timed out): the import does not stay "running". */
    public function failed(?Throwable $e): void
    {
        $import = AudienceImport::query()->find($this->importId);
        if ($import?->running()) {
            $this->fail($import, $e?->getMessage() ?? __('The import was interrupted. Run it again.'));
        }
    }

    protected function fail(AudienceImport $import, string $message, ?OneSignalImport $reader = null): void
    {
        $failure = ['status' => 'failed', 'error' => mb_substr($message, 0, 250), 'completed_at' => now()];

        try {
            $import->forceFill(($reader?->counts ?? []) + $failure + ['notes' => $reader?->reasons ?: null])->save();
        } catch (Throwable) {
            // The counts themselves may be what the database refuses: the failure is recorded anyway.
            $import->newQuery()->whereKey($import->id)->update($failure);
        }
    }
}
