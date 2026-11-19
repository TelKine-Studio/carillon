<?php

namespace App\Http\Controllers\Sdk;

use App\Http\Controllers\Controller;
use App\LiveActivities\LiveActivities;
use App\Models\LiveActivityCall;
use App\Models\Project;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Tokens of Live Activities, sent by the SDK of the app. Every call is
 * journaled (Live Activities → Calls of the app), refused ones included.
 */
class LiveActivityController extends Controller
{
    /** Why the device was refused, for the journal. */
    protected ?string $refusal = null;

    protected ?Subscription $device = null;

    public function __construct(protected LiveActivities $activities) {}

    /** Push-to-start token of a type of activity (iOS 17.2+). */
    public function startToken(Request $request, string $appId, string $id): JsonResponse
    {
        return $this->journaled($request, $appId, $id, 'start-token', function () use ($request, $appId, $id) {
            $data = $request->validate([
                'activity_type' => ['required', 'string', 'max:128'],
                'token' => ['nullable', 'string', 'max:4000'],
            ]);
            $device = $this->device($appId, $id);

            filled($data['token'] ?? null)
                ? $this->activities->saveStartToken($device, $data['activity_type'], $data['token'])
                : $this->activities->removeStartToken($device, $data['activity_type']);

            return [response()->json(['success' => true]), filled($data['token'] ?? null) ? null : 'Token removed.'];
        });
    }

    /** The app shows an activity and has its update token. */
    public function enter(Request $request, string $appId, string $id): JsonResponse
    {
        return $this->journaled($request, $appId, $id, 'enter', function () use ($request, $appId, $id) {
            $data = $request->validate([
                'activity_id' => ['required', 'string', 'max:255'],
                'activity_type' => ['nullable', 'string', 'max:128'],
                'token' => ['required', 'string', 'max:4000'],
            ]);
            $activity = $this->activities->enter($this->device($appId, $id), $data['activity_id'], $data['activity_type'] ?? 'Unknown', $data['token']);

            return [response()->json(['success' => true, 'status' => $activity->status]), null];
        });
    }

    /** The activity left the screen of the device. */
    public function exit(Request $request, string $appId, string $id): JsonResponse
    {
        return $this->journaled($request, $appId, $id, 'exit', function () use ($request, $appId, $id) {
            $data = $request->validate(['activity_id' => ['required', 'string', 'max:255']]);
            $this->activities->exit($this->device($appId, $id), $data['activity_id']);

            return [response()->json(['success' => true]), null];
        });
    }

    /** @param Closure(): array{0: JsonResponse, 1: ?string} $handle */
    protected function journaled(Request $request, string $appId, string $id, string $action, Closure $handle): JsonResponse
    {
        // The instance of a controller outlives one request (route cache, Octane).
        $this->refusal = null;
        $this->device = null;

        try {
            [$response, $detail] = $handle();
            $this->record($request, $appId, $id, $action, 'ok', $detail);

            return $response;
        } catch (ValidationException $e) {
            $this->record($request, $appId, $id, $action, 'invalid', collect($e->errors())->flatten()->first());
            throw $e;
        } catch (HttpExceptionInterface $e) {
            $this->record($request, $appId, $id, $action, $this->refusal ?? 'error', $e->getMessage());
            throw $e;
        } catch (Throwable $e) {
            $this->record($request, $appId, $id, $action, 'error', class_basename($e).': '.$e->getMessage());
            throw $e;
        }
    }

    /** The journal never breaks the call itself. */
    protected function record(Request $request, string $appId, string $id, string $action, string $outcome, ?string $detail): void
    {
        try {
            $projectId = Project::query()->where('app_id', $appId)->value('id');
            if (! $projectId) {
                return;
            }
            LiveActivityCall::create([
                'project_id' => $projectId,
                'subscription_id' => $this->device?->id,
                'subscription_ref' => Str::limit($id, 60, ''),
                'action' => $action,
                'activity_id' => Str::limit((string) $request->input('activity_id'), 250, '') ?: null,
                'activity_type' => Str::limit((string) $request->input('activity_type'), 120, '') ?: null,
                'outcome' => $outcome,
                'detail' => $detail !== null ? Str::limit($detail, 250) : null,
                'app_version' => $this->device?->app_version,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Live Activity call not journaled: '.$e->getMessage());
        }
    }

    protected function device(string $appId, string $id): Subscription
    {
        // PostgreSQL refuses to compare a UUID column with any other text.
        if (! Str::isUuid($id)) {
            $this->refusal = 'unknown_device';
            abort(404, 'Unknown subscription.');
        }
        $device = Subscription::query()->with('project:id,app_id')->find($id);
        $this->refusal = match (true) {
            ! $device => 'unknown_device',
            $device->project?->app_id !== $appId => 'other_project',
            $device->type !== 'ios' => 'not_ios',
            default => null,
        };
        abort_if($this->refusal !== null, 404, 'Unknown subscription.');

        return $this->device = $device;
    }
}
