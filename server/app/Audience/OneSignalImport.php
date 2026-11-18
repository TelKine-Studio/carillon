<?php

namespace App\Audience;

use App\Http\Controllers\Sdk\SubscriptionController;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Sms\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reads the subscribers exported by OneSignal (CSV, optionally gzipped: the
 * "players" export and the newer "subscriptions" export) and creates the
 * people and devices. Apple and Firebase tokens keep working with your own
 * keys, email addresses are imported for the email channel; web subscriptions are tied to the VAPID keys of OneSignal and are
 * skipped (the web SDK subscribes the visitors again).
 */
class OneSignalImport
{
    /** OneSignal device_type numbers. */
    protected const DEVICE_TYPES = [0 => 'ios', 1 => 'android', 2 => 'amazon', 5 => 'web', 7 => 'web', 8 => 'web', 17 => 'web', 11 => 'email', 13 => 'huawei', 14 => 'sms'];

    protected const SUBSCRIPTION_TYPES = ['iospush' => 'ios', 'androidpush' => 'android', 'huaweipush' => 'huawei', 'fireospush' => 'amazon', 'chromepush' => 'web', 'firefoxpush' => 'web', 'safaripush' => 'web', 'safarilegacypush' => 'web', 'email' => 'email', 'sms' => 'sms', 'whatsapp' => 'whatsapp'];

    /** @var array<string, int> */
    public array $counts = ['rows' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'kept' => 0];

    /** @var array<string, int> reasons of the skipped rows */
    public array $reasons = [];

    public function __construct(protected Project $project) {}

    /**
     * @param  callable(array<string, int>): void|null  $progress  called every 500 rows
     */
    public function file(string $path, ?callable $progress = null): void
    {
        $handle = fopen(str_ends_with(strtolower($path), '.gz') ? 'compress.zlib://'.$path : $path, 'r');
        if (! $handle) {
            throw new \RuntimeException('The file cannot be read.');
        }

        try {
            $header = fgetcsv($handle, escape: '\\');
            if (! $header) {
                throw new \RuntimeException('The file is empty.');
            }
            $header = array_map(fn ($name) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name))), $header);
            if (! array_intersect($header, ['token', 'identifier', 'push_token'])) {
                // The export of the dashboard (Users & subscriptions) lists the
                // subscriptions without their push token: only the API gives it.
                throw new \RuntimeException(array_intersect($header, ['subscription id', 'onesignal id', 'subscription_id', 'onesignal_id'])
                    ? __('This export from the OneSignal dashboard has no push tokens. Use the automatic sync below: it gets them through the OneSignal API.')
                    : __('This is not a OneSignal export: no "token" or "identifier" column.'));
            }

            $batch = [];
            while (($values = fgetcsv($handle, escape: '\\')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                $batch[] = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
                if (count($batch) === 500) {
                    $this->rows($batch);
                    $batch = [];
                    $progress && $progress($this->counts);
                }
            }
            $this->rows($batch);
            $progress && $progress($this->counts);
        } finally {
            fclose($handle);
        }
    }

    /** @param list<array<string, string|null>> $rows */
    public function rows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->counts['rows']++;
                $device = $this->normalize($row);

                if (is_string($device)) {
                    $this->counts['skipped']++;
                    $this->reasons[$device] = ($this->reasons[$device] ?? 0) + 1;

                    continue;
                }

                $this->import($device);
            }
        });
    }

    /**
     * A row in our terms, or the reason it is skipped.
     *
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>|string
     */
    public function normalize(array $row): array|string
    {
        $value = fn (string ...$keys) => collect($keys)->map(fn ($key) => isset($row[$key]) ? trim((string) $row[$key]) : '')->first(fn ($v) => $v !== '');

        $type = null;
        if ($raw = $value('type')) {
            $type = self::SUBSCRIPTION_TYPES[strtolower($raw)] ?? null;
        } elseif (($raw = $value('device_type')) !== null) {
            $type = is_numeric($raw) ? (self::DEVICE_TYPES[(int) $raw] ?? null) : (['ios' => 'ios', 'android' => 'android'][strtolower($raw)] ?? null);
        }

        if ($type === 'web') {
            return 'web';
        }
        if (! in_array($type, ['ios', 'android', 'huawei', 'amazon', 'email', 'sms', 'whatsapp'], true)) {
            return 'unsupported';
        }

        $token = $value('token', 'identifier', 'push_token');
        if (! $token || strlen($token) > 4000) {
            return 'no_token';
        }
        if ($type === 'ios' && ! preg_match('/^[0-9a-f]{64,200}$/i', $token)) {
            return 'invalid_token';
        }
        if ($type === 'email') {
            $token = SubscriptionController::email($token);
            if (! $token) {
                return 'invalid_token';
            }
        }
        if ($type === 'sms' || $type === 'whatsapp') {
            $token = Phone::normalize($token);
            if (! $token) {
                return 'invalid_token';
            }
        }

        $enabled = true;
        if (($flag = $value('enabled', 'subscribed')) !== null) {
            $enabled = in_array(strtolower($flag), ['1', 'true', 'yes', 't'], true);
        } elseif (($types = $value('notification_types')) !== null && is_numeric($types)) {
            $enabled = (int) $types > 0;
        }
        if (in_array(strtolower((string) $value('invalid_identifier')), ['1', 'true', 't'], true)) {
            return 'invalid_token';
        }

        $tags = [];
        $rawTags = $value('tags');
        if ($rawTags) {
            $decoded = json_decode($rawTags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }
        foreach ($row as $key => $tag) {
            if ((str_starts_with($key, 'tags.') || str_starts_with($key, 'tag:')) && trim((string) $tag) !== '') {
                $tags[substr($key, str_starts_with($key, 'tags.') ? 5 : 4)] = $tag;
            }
        }

        $timezone = $value('timezone_id');
        if (! $timezone && ($zone = $value('timezone')) && ! is_numeric($zone)) {
            $timezone = $zone;
        }
        if ($timezone && ! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = null;
        }

        $country = strtoupper((string) $value('country'));

        return [
            'type' => $type,
            'token' => $token,
            'enabled' => $enabled,
            'environment' => $type === 'ios' ? ($value('test_type') === '1' ? 'sandbox' : 'production') : null,
            'external_id' => $value('external_id', 'external_user_id'),
            'onesignal_id' => mb_substr((string) $value('id', 'player_id', 'subscription_id'), 0, 64) ?: null,
            'tags' => array_slice(array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $tags), 0, Subscriber::MAX_TAGS, true),
            'language' => ($language = $value('language')) ? strtolower(substr($language, 0, 2)) : null,
            'country' => strlen($country) === 2 ? $country : null,
            'timezone' => $timezone,
            'app_version' => mb_substr((string) $value('app_version', 'game_version'), 0, 32) ?: null,
            'device_model' => mb_substr((string) $value('device_model'), 0, 64) ?: null,
            'device_os' => mb_substr((string) $value('device_os'), 0, 32) ?: null,
            'sessions' => (int) $value('session_count'),
            'last_active_at' => $this->date($value('last_active')),
            'first_seen_at' => $this->date($value('created_at')),
        ];
    }

    /** @param array<string, mixed> $device */
    protected function import(array $device): void
    {
        $subscription = Subscription::query()
            ->where('project_id', $this->project->id)
            ->where('token_hash', hash('sha256', $device['token']))
            ->when(! in_array($device['type'], Subscription::PUSH_TYPES, true), fn ($q) => $q->where('type', $device['type']))
            ->first();

        // A device the Carillon SDK registered (same FCM or APNs token as in
        // OneSignal) is the app's: the export of the old app neither re-enables
        // it, nor moves it to another person, nor brings back old tags.
        if ($subscription && $subscription->sdk !== Subscription::ONESIGNAL_SDK) {
            $this->counts['kept']++;
            if ($device['onesignal_id'] && ! $subscription->onesignal_id) {
                $subscription->forceFill(['onesignal_id' => $device['onesignal_id']])->save();
            }

            return;
        }

        $subscriber = $device['external_id']
            ? Subscriber::query()->firstOrNew(['project_id' => $this->project->id, 'external_id' => $device['external_id']])
            : ($subscription?->subscriber ?? new Subscriber(['project_id' => $this->project->id]));

        if ($device['tags'] !== []) {
            $subscriber->mergeTags($device['tags']);
        }
        foreach (['language', 'country', 'timezone'] as $field) {
            $subscriber->{$field} ??= $device[$field];
        }
        if (($device['first_seen_at'] ?? null) && $device['first_seen_at']->isPast() && (! $subscriber->first_seen_at || $subscriber->first_seen_at->gt($device['first_seen_at']))) {
            $subscriber->first_seen_at = $device['first_seen_at'];
        }
        if ($device['last_active_at'] && (! $subscriber->last_active_at || $subscriber->last_active_at->lt($device['last_active_at']))) {
            $subscriber->last_active_at = $device['last_active_at'];
        }
        $subscriber->save();

        $this->counts[$subscription ? 'updated' : 'created']++;
        $subscription ??= new Subscription(['project_id' => $this->project->id]);
        $subscription->fill([
            'subscriber_id' => $subscriber->id,
            'type' => $device['type'],
            'token' => $device['token'],
            'enabled' => $device['enabled'],
            // A token refused by Apple or Google stays dead: OneSignal does not know it yet.
            'invalid_at' => $subscription->invalid_at,
            'environment' => $device['environment'],
            'app_version' => $device['app_version'] ?? $subscription->app_version,
            'device_model' => $device['device_model'] ?? $subscription->device_model,
            'device_os' => $device['device_os'] ?? $subscription->device_os,
            'sdk' => $subscription->sdk ?? Subscription::ONESIGNAL_SDK,
            'onesignal_id' => $device['onesignal_id'] ?? $subscription->onesignal_id,
            'sessions' => max((int) $subscription->sessions, $device['sessions']),
            'last_active_at' => $device['last_active_at'] ?? $subscription->last_active_at,
        ]);
        if (! $device['enabled'] && $subscription->status_reason === null) {
            $subscription->setStatus('imported_unsubscribed');
        }
        $subscription->save();
    }

    protected function date(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return is_numeric($value) ? CarbonImmutable::createFromTimestamp((int) $value) : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
