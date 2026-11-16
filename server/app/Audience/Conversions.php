<?php

namespace App\Audience;

use App\Journeys\JourneyRunner;
use App\Models\CustomEvent;
use App\Models\Delivery;
use App\Models\Project;
use App\Models\Subscriber;
use Carbon\CarbonInterface;

/**
 * Records custom events and attributes them to a message, as OneSignal
 * outcomes: "direct" when the person clicked a notification in the window,
 * otherwise "influenced" when they received one.
 */
class Conversions
{
    /** Attribution window, in hours. */
    public const WINDOW = 24;

    /** Keys of the properties read as the value of the event (revenue). */
    public const VALUE_KEYS = ['value', 'revenue', 'amount', 'price', 'total'];

    public function __construct(protected JourneyRunner $journeys) {}

    /** @param array<string, mixed> $properties */
    public function record(Project $project, ?Subscriber $subscriber, string $name, ?float $value = null, array $properties = [], ?CarbonInterface $at = null): CustomEvent
    {
        $at ??= now();
        if ($value === null) {
            foreach (self::VALUE_KEYS as $key) {
                if (isset($properties[$key]) && is_numeric($properties[$key])) {
                    $value = (float) $properties[$key];
                    break;
                }
            }
        }

        [$message, $attribution] = $subscriber ? $this->attribute($subscriber, $at) : [null, null];

        $event = CustomEvent::query()->create([
            'project_id' => $project->id,
            'subscriber_id' => $subscriber?->id,
            'name' => mb_substr($name, 0, 128),
            'value' => $value,
            'properties' => $properties ?: null,
            'message_id' => $message,
            'attribution' => $attribution,
            'occurred_at' => $at,
        ]);

        if ($subscriber) {
            $this->journeys->trigger($project, 'event', $subscriber, ['event' => $event->name]);
        }

        return $event;
    }

    /** @return array{0: ?string, 1: ?string} message id and attribution */
    protected function attribute(Subscriber $subscriber, CarbonInterface $at): array
    {
        $since = $at->copy()->subHours(self::WINDOW);
        $deliveries = Delivery::query()
            ->whereIn('subscription_id', $subscriber->subscriptions()->select('id'))
            ->where('status', 'sent');

        $clicked = (clone $deliveries)->whereBetween('clicked_at', [$since, $at])->latest('clicked_at')->value('message_id');
        if ($clicked) {
            return [$clicked, 'direct'];
        }

        $received = (clone $deliveries)->whereBetween('sent_at', [$since, $at])->latest('sent_at')->value('message_id');

        return $received ? [$received, 'influenced'] : [null, null];
    }
}
