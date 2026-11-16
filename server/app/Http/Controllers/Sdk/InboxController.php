<?php

namespace App\Http\Controllers\Sdk;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inbox of the app (notification center): the push notifications a person
 * received on any of their devices, newest first, with what they read.
 */
class InboxController extends Controller
{
    public const LIMIT = 50;

    public function index(Request $request, string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        $language = $subscription->subscriber?->language;

        $devices = Subscription::query()->where('subscriber_id', $subscription->subscriber_id)->whereIn('type', Subscription::PUSH_TYPES)->pluck('id');
        $sent = DB::table('deliveries')
            ->whereIn('subscription_id', $devices)
            ->where('status', 'sent')
            ->selectRaw('message_id, min(sent_at) as sent_at')
            ->groupBy('message_id');
        $reads = DB::table('inbox_reads')->where('subscriber_id', $subscription->subscriber_id)->get()->keyBy('message_id');

        $messages = Message::query()
            ->joinSub($sent, 'sent', 'sent.message_id', '=', 'messages.id')
            ->where('messages.project_id', $subscription->project_id)
            ->where('messages.is_test', false)
            ->select('messages.*', 'sent.sent_at as delivered_at')
            ->orderByDesc('sent.sent_at')
            ->limit(self::LIMIT * 2)
            ->get()
            // Hidden from the inbox when the message says so, or removed by the person.
            ->reject(fn (Message $message) => $message->option('inbox') === false || isset($reads[$message->id]?->removed_at))
            ->take(self::LIMIT)
            ->values();

        $items = $messages->map(fn (Message $message) => array_filter([
            'id' => $message->id,
            'title' => Message::localized($message->headings, $language),
            'body' => Message::localized($message->contents, $language),
            'image' => $message->option('big_picture'),
            'icon' => $message->option('large_icon'),
            'url' => $message->option('app_url') ?? $message->option('url'),
            'data' => $message->option('data'),
            'sent_at' => Carbon::parse($message->delivered_at)->toIso8601String(),
            'read' => isset($reads[$message->id]?->read_at),
        ], fn ($value) => $value !== null))->all();

        return response()->json([
            'messages' => $items,
            'unread' => collect($items)->where('read', false)->count(),
        ]);
    }

    /** Marks messages as read (ids, or every one with "all"), or removes them. */
    public function update(Request $request, string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        $data = $request->validate([
            'message_ids' => ['nullable', 'array', 'max:200'],
            'message_ids.*' => ['uuid'],
            'all' => ['nullable', 'boolean'],
            'action' => ['nullable', 'in:read,remove'],
        ]);

        $ids = $request->boolean('all')
            ? DB::table('deliveries')->join('subscriptions', 'subscriptions.id', '=', 'deliveries.subscription_id')
                ->where('subscriptions.subscriber_id', $subscription->subscriber_id)->where('deliveries.status', 'sent')
                ->distinct()->pluck('deliveries.message_id')->all()
            : Message::query()->where('project_id', $subscription->project_id)->whereIn('id', $data['message_ids'] ?? [])->pluck('id')->all();

        $now = now();
        $column = ($data['action'] ?? 'read') === 'remove' ? 'removed_at' : 'read_at';
        foreach (array_chunk($ids, 200) as $chunk) {
            DB::table('inbox_reads')->upsert(
                array_map(fn ($messageId) => ['subscriber_id' => $subscription->subscriber_id, 'message_id' => $messageId, $column => $now], $chunk),
                ['subscriber_id', 'message_id'],
                [$column],
            );
        }

        return response()->json(['success' => true, 'count' => count($ids)]);
    }

    protected function subscription(string $appId, string $id): Subscription
    {
        abort_unless(Str::isUuid($id), 404, 'Unknown subscription.');
        $subscription = Subscription::query()->with(['project', 'subscriber'])->whereKey($id)
            ->whereHas('project', fn ($q) => $q->where('app_id', $appId))->first();
        abort_unless($subscription, 404, 'Unknown subscription.');

        return $subscription;
    }
}
