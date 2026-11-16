<?php

namespace App\Http;

use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Reads the message composer form (messages and templates). */
final class ComposerInput
{
    /**
     * Composer form → OneSignal format.
     *
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request): array
    {
        $input = ['name' => $request->input('name')];

        foreach ((array) $request->input('texts', []) as $text) {
            $language = strtolower((string) ($text['language'] ?? ''));
            if (! preg_match('/^[a-z]{2}$/', $language)) {
                continue;
            }
            foreach (['title' => 'headings', 'subtitle' => 'subtitle', 'body' => 'contents'] as $field => $key) {
                if (filled($text[$field] ?? null)) {
                    $input[$key][$language] = $text[$field];
                }
            }
        }

        match ($request->input('audience', 'segments')) {
            'users' => $input['include_aliases'] = ['external_id' => self::list($request->input('external_ids'))],
            'devices' => $input['include_subscription_ids'] = self::list($request->input('subscription_ids')),
            default => [
                $input['included_segments'] = array_values((array) $request->input('included_segments', [])),
                $input['excluded_segments'] = array_values((array) $request->input('excluded_segments', [])),
            ],
        };

        $platforms = (array) $request->input('platforms', []);
        if ($platforms !== [] && count(array_intersect(Subscription::PUSH_TYPES, $platforms)) < count(Subscription::PUSH_TYPES)) {
            $input['isIos'] = in_array('ios', $platforms, true);
            $input['isAndroid'] = in_array('android', $platforms, true);
            $input['isHuawei'] = in_array('huawei', $platforms, true);
            $input['isAdm'] = in_array('amazon', $platforms, true);
            $input['isAnyWeb'] = in_array('web', $platforms, true);
        }

        foreach (['url', 'big_picture', 'collapse_id', 'ios_sound', 'thread_id', 'android_channel_id'] as $field) {
            if (filled($request->input($field))) {
                $input[$field] = trim((string) $request->input($field));
            }
        }
        $input['priority'] = (int) $request->input('priority', 10) === 5 ? 5 : 10;
        if (filled($request->input('ttl'))) {
            $input['ttl'] = (int) $request->input('ttl');
        }
        if (filled($request->input('ios_badge'))) {
            $input['ios_badgeType'] = 'SetTo';
            $input['ios_badgeCount'] = (int) $request->input('ios_badge');
        }

        $data = [];
        foreach ((array) $request->input('data', []) as $row) {
            if (filled($row['key'] ?? null)) {
                $data[trim($row['key'])] = (string) ($row['value'] ?? '');
            }
        }
        if ($data !== []) {
            $input['data'] = $data;
        }

        $buttons = [];
        foreach ((array) $request->input('buttons', []) as $row) {
            if (filled($row['text'] ?? null)) {
                $buttons[] = ['id' => trim($row['id'] ?? '') ?: 'button-'.(count($buttons) + 1), 'text' => trim($row['text'])];
            }
        }
        if ($buttons !== []) {
            $input['buttons'] = array_slice($buttons, 0, 3);
            $input['web_buttons'] = array_map(fn ($b) => ['id' => $b['id'], 'text' => $b['text']], array_slice($buttons, 0, 2));
        }

        // The date is typed in the browser's time zone.
        if ($request->input('schedule') === 'later' && filled($request->input('send_at'))) {
            try {
                $input['send_after'] = CarbonImmutable::parse($request->input('send_at'), $request->input('timezone') ?: config('app.timezone'))->utc()->toIso8601String();
            } catch (Throwable) {
                throw ValidationException::withMessages(['send_at' => __('Invalid date.')]);
            }
        }

        if ($request->input('channel') === 'email') {
            $input = self::email($request, $input);
        }
        if ($request->input('channel') === 'sms') {
            $input = self::sms($request, $input);
        }

        if ($request->input('schedule') === 'timezone') {
            $input['delayed_option'] = 'timezone';
            $input['delivery_time_of_day'] = (string) $request->input('delivery_time');
        }

        return $input;
    }

    /**
     * The email fields replace the push ones; "devices" become addresses.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected static function email(Request $request, array $input): array
    {
        $kept = array_intersect_key($input, array_flip(['name', 'included_segments', 'excluded_segments', 'include_aliases', 'send_after']));
        $kept['target_channel'] = 'email';

        if ($request->input('audience') === 'devices') {
            $kept['include_email_tokens'] = self::list($request->input('email_addresses'));
        }

        foreach (['email_subject', 'email_preheader', 'email_body', 'email_from_name'] as $field) {
            if (filled($request->input($field))) {
                $kept[$field] = (string) $request->input($field);
            }
        }

        // Blocks of the visual editor, kept to edit the email again (the HTML is what is sent).
        $design = json_decode((string) $request->input('email_design'), true);
        if (is_array($design) && is_array($design['blocks'] ?? null) && strlen((string) $request->input('email_design')) <= 200000) {
            $kept['email_design'] = $design;
        }

        return $kept;
    }

    /**
     * The SMS text replaces the push fields; "devices" become phone numbers.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected static function sms(Request $request, array $input): array
    {
        $kept = array_intersect_key($input, array_flip(['name', 'included_segments', 'excluded_segments', 'include_aliases', 'send_after', 'delayed_option', 'delivery_time_of_day']));
        $kept['target_channel'] = 'sms';
        $kept['contents'] = filled($request->input('sms_body')) ? ['en' => (string) $request->input('sms_body')] : null;

        if ($request->input('audience') === 'devices') {
            // Numbers may contain spaces: one per line or separated by commas.
            $kept['include_phone_numbers'] = array_values(array_filter(array_map('trim', preg_split('/[\n,;]+/', (string) $request->input('phone_numbers')) ?: [])));
        }
        if (filled($request->input('sms_media_url'))) {
            $kept['sms_media_urls'] = [trim((string) $request->input('sms_media_url'))];
        }

        return array_filter($kept, fn ($value) => $value !== null);
    }

    /** @return list<string> */
    protected static function list(mixed $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $value) ?: [])));
    }
}
