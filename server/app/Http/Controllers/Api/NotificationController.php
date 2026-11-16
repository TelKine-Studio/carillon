<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateMessage;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** OneSignal-compatible notifications API. */
class NotificationController extends Controller
{
    /** Platform names used by OneSignal in the statistics. */
    public const PLATFORM_NAMES = ['ios' => 'ios', 'android' => 'android', 'huawei' => 'huawei', 'amazon' => 'amazon', 'web' => 'chrome_web_push'];

    public function store(Request $request, CreateMessage $create): JsonResponse
    {
        $project = $this->project($request);
        $message = $create->handle($project, $request->all(), apiKey: $request->attributes->get('api_key'));

        return response()->json([
            'id' => $message->id,
            'external_id' => $message->idempotency_key,
            'recipients' => $message->recipients,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $limit = min(50, max(1, (int) $request->query('limit', 50)));
        $offset = max(0, (int) $request->query('offset', 0));
        $query = $project->messages()->latest();

        return response()->json([
            'total_count' => (clone $query)->count(),
            'offset' => $offset,
            'limit' => $limit,
            'notifications' => $query->with('platformStats')->skip($offset)->take($limit)->get()->map(fn (Message $message) => $this->present($message))->all(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->present($this->message($request, $id)));
    }

    /** Cancels a scheduled (or not yet started) message. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $message = $this->message($request, $id);

        if (! $message->isCancelable()) {
            return response()->json(['errors' => ['Notification has already been sent to all recipients']], 400);
        }

        $message->forceFill(['status' => 'canceled', 'canceled_at' => now()])->save();

        return response()->json(['success' => true]);
    }

    protected function project(Request $request): Project
    {
        return $request->attributes->get('project');
    }

    protected function message(Request $request, string $id): Message
    {
        $message = $this->project($request)->messages()->with('platformStats')->find($id);
        abort_unless($message, 404, 'Notification not found');

        return $message;
    }

    /** @return array<string, mixed> */
    public function present(Message $message): array
    {
        $stats = [];
        foreach ($message->platformStats as $platform) {
            $stats[self::PLATFORM_NAMES[$platform->platform] ?? $platform->platform] = [
                'successful' => $platform->successful,
                'failed' => $platform->failed,
                'errored' => $platform->errored,
                'converted' => $platform->clicked,
                'received' => $platform->received,
            ];
        }

        return [
            'id' => $message->id,
            'app_id' => $message->project->app_id,
            'name' => $message->name,
            'headings' => $message->headings,
            'subtitle' => $message->subtitle,
            'contents' => $message->contents,
            ...($message->options ?? []),
            'included_segments' => $message->targeting['segments'] ?? null,
            'excluded_segments' => $message->targeting['excluded_segments'] ?? null,
            'filters' => $message->targeting['filters'] ?? null,
            'status' => $message->status,
            'canceled' => $message->status === 'canceled',
            'recipients' => $message->recipients,
            'successful' => $message->successful,
            'failed' => $message->failed,
            'errored' => $message->errored,
            'received' => $message->received,
            // Devices of this message still on the OneSignal SDK (no reception or click reports).
            'legacy' => $message->deliveries()->whereHas('subscription', fn ($q) => $q->where('sdk', Subscription::ONESIGNAL_SDK))->count(),
            'converted' => $message->clicked,
            'remaining' => in_array($message->status, ['scheduled', 'queued', 'sending'], true) ? $message->remaining() : 0,
            'queued_at' => $message->created_at?->getTimestamp(),
            'send_after' => ($message->send_after ?? $message->created_at)?->getTimestamp(),
            'completed_at' => $message->completed_at?->getTimestamp(),
            'platform_delivery_stats' => $stats ?: null,
        ];
    }
}
