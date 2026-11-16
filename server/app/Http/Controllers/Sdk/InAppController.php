<?php

namespace App\Http\Controllers\Sdk;

use App\Audience\Audience;
use App\Http\Controllers\Controller;
use App\Models\InAppMessage;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** In-app messages for the SDKs: the ones a device may show, and what happened to them. */
class InAppController extends Controller
{
    public function index(string $appId, string $id, Audience $audience): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);

        $messages = InAppMessage::query()
            ->where('project_id', $subscription->project_id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (InAppMessage $message) => $message->isLive() && $this->targets($message, $subscription, $audience))
            ->values();

        return response()->json(['in_app_messages' => $messages->map->toSdk()->all()]);
    }

    public function event(Request $request, string $appId, string $messageId): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'uuid'],
            'event' => ['required', Rule::in(['shown', 'clicked', 'dismissed'])],
            'button_id' => ['nullable', 'string', 'max:64'],
        ]);
        $subscription = $this->subscription($appId, $data['subscription_id']);
        abort_unless(Str::isUuid($messageId), 404);

        DB::table('in_app_messages')
            ->where('id', $messageId)
            ->where('project_id', $subscription->project_id)
            ->increment($data['event']);

        if ($data['event'] === 'shown') {
            $subscription->forceFill(['last_active_at' => now()])->save();
        }

        return response()->json(['success' => true]);
    }

    /** The person is in the segment of the message (or it has none). */
    protected function targets(InAppMessage $message, Subscription $subscription, Audience $audience): bool
    {
        if (! $message->segment) {
            return true;
        }

        try {
            return $audience->query($subscription->project, ['segments' => [$message->segment]])
                ->where('subscriptions.id', $subscription->id)
                ->exists();
        } catch (ValidationException) {
            return false;
        }
    }

    protected function subscription(string $appId, string $id): Subscription
    {
        abort_unless(Str::isUuid($id), 404, 'Unknown subscription.');
        $subscription = Subscription::query()->with('project')->whereKey($id)
            ->whereHas('project', fn ($q) => $q->where('app_id', $appId))->first();
        abort_unless($subscription, 404, 'Unknown subscription.');

        return $subscription;
    }
}
