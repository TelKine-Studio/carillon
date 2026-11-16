<?php

namespace App\Actions;

use App\Audience\Audience;
use App\Jobs\DispatchMessage;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Push\LocalTime;
use App\Sms\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates a message from the OneSignal "Create notification" format
 * (POST /notifications), used by the API and the dashboard.
 */
class CreateMessage
{
    /** Fields stored as options of the message (everything but texts and targeting). */
    public const OPTIONS = [
        'data', 'url', 'web_url', 'app_url', 'big_picture', 'ios_attachments', 'chrome_web_image', 'chrome_web_icon',
        'chrome_web_badge', 'firefox_icon', 'buttons', 'web_buttons', 'ios_sound', 'android_sound', 'ios_badgeType',
        'ios_badgeCount', 'thread_id', 'ios_category', 'ios_interruption_level', 'ios_relevance_score',
        'android_channel_id', 'android_accent_color', 'small_icon', 'large_icon', 'android_group', 'android_visibility',
        'priority', 'ttl', 'collapse_id', 'web_push_topic', 'content_available', 'require_interaction',
        'delayed_option', 'delivery_time_of_day',
        'email_subject', 'email_body', 'email_preheader', 'email_from_name', 'email_from_address', 'email_reply_to_address', 'disable_email_click_tracking',
        'email_design', 'sms_from', 'sms_media_urls', 'whatsapp_template', 'whatsapp_language', 'whatsapp_variables',
    ];

    /** Recipients that can be listed explicitly in one call. */
    public const MAX_IDS = 20000;

    public function __construct(protected Audience $audience) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Project $project, array $input, ?User $author = null, ?ApiKey $apiKey = null, bool $draft = false): Message
    {
        // A template gives the content; the fields of the request win.
        $template = null;
        if (filled($input['template_id'] ?? null)) {
            $id = (string) $input['template_id'];
            $template = Str::isUuid($id) ? $project->templates()->find($id) : null;
            if (! $template) {
                throw ValidationException::withMessages(['template_id' => 'Template not found.']);
            }
            $input = array_replace($template->toInput(), array_filter($input, fn ($value) => $value !== null));
        }

        $this->validate($input);

        if (filled($input['idempotency_key'] ?? $input['external_id'] ?? null)) {
            $key = (string) ($input['idempotency_key'] ?? $input['external_id']);
            if ($existing = $project->messages()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
        }

        $targeting = $this->targeting($input);
        $sendAfter = $this->sendAfter($input['send_after'] ?? null);

        $options = Arr::only($input, self::OPTIONS);
        // ios_attachments {"id": "https://..."}: the first image is the picture.
        if (! isset($options['big_picture']) && is_array($options['ios_attachments'] ?? null)) {
            $options['big_picture'] = reset($options['ios_attachments']) ?: null;
        }
        $options['ttl'] ??= $project->setting('default_ttl');
        // Only the time zone option changes the delivery ("last-active" is
        // sent right away).
        if (($options['delayed_option'] ?? null) === 'timezone') {
            $options['delivery_time_of_day'] = LocalTime::parse($options['delivery_time_of_day'])->format();
        } else {
            unset($options['delayed_option'], $options['delivery_time_of_day']);
        }
        $options = array_filter($options, fn ($value) => $value !== null);

        $message = new Message([
            'project_id' => $project->id,
            'channel' => self::channel($input),
            'created_by' => $author?->id,
            'api_key_id' => $apiKey?->id,
            'template_id' => $template?->id,
            'name' => $input['name'] ?? null,
            'idempotency_key' => $input['idempotency_key'] ?? $input['external_id'] ?? null,
            'headings' => $this->texts($input['headings'] ?? null),
            'subtitle' => $this->texts($input['subtitle'] ?? null),
            'contents' => $this->texts($input['contents'] ?? null) ?? [],
            'options' => $options ?: null,
            'targeting' => $targeting,
            'status' => $draft ? 'draft' : ($sendAfter ? 'scheduled' : 'queued'),
            'send_after' => $sendAfter,
        ]);
        $message->setRelation('project', $project);

        // Fails early on an unknown segment or filter.
        $recipients = $this->audience->query($project, $targeting)->count();

        // A draft is saved as is: its audience may still be empty.
        if ($recipients === 0 && ! $sendAfter && ! $draft) {
            throw ValidationException::withMessages(['recipients' => 'All included players are not subscribed']);
        }

        $message->recipients = $recipients;
        $message->save();

        if (! $sendAfter && ! $draft) {
            DispatchMessage::dispatch($message->id);
        }

        return $message;
    }

    /** @param array<string, mixed> $input */
    protected function validate(array $input): void
    {
        $text = ['nullable', 'array'];

        Validator::make($input, [
            // A WhatsApp template carries its own text.
            'contents' => [empty($input['content_available']) && ! self::isEmail($input) && ! (self::channel($input) === 'whatsapp' && filled($input['whatsapp_template'] ?? null)) ? 'required' : 'nullable', 'array', 'min:1'],
            'contents.*' => ['nullable', 'string', 'max:4000'],
            'headings' => $text, 'headings.*' => ['nullable', 'string', 'max:500'],
            'subtitle' => $text, 'subtitle.*' => ['nullable', 'string', 'max:500'],
            'name' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'data' => ['nullable', 'array'],
            'url' => ['nullable', 'string', 'max:2000'],
            'web_url' => ['nullable', 'string', 'max:2000'],
            'app_url' => ['nullable', 'string', 'max:2000'],
            'big_picture' => ['nullable', 'string', 'max:2000'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.id' => ['required_with:buttons', 'string', 'max:64'],
            'buttons.*.text' => ['required_with:buttons', 'string', 'max:100'],
            'web_buttons' => ['nullable', 'array', 'max:2'],
            'priority' => ['nullable', 'integer', 'between:1,10'],
            'ttl' => ['nullable', 'integer', 'min:0', 'max:2419200'],
            'collapse_id' => ['nullable', 'string', 'max:64'],
            'ios_badgeType' => ['nullable', 'in:None,SetTo,Increase'],
            'ios_badgeCount' => ['nullable', 'integer'],
            'included_segments' => ['nullable', 'array'], 'included_segments.*' => ['string', 'max:255'],
            'excluded_segments' => ['nullable', 'array'], 'excluded_segments.*' => ['string', 'max:255'],
            'filters' => ['nullable', 'array', 'max:200'],
            'include_subscription_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'include_player_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'include_external_user_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'include_aliases' => ['nullable', 'array'],
            'include_aliases.external_id' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'target_channel' => ['nullable', 'in:push,email,sms,whatsapp'],
            'whatsapp_template' => ['nullable', 'string', 'max:512'],
            'whatsapp_language' => ['nullable', 'string', 'max:16'],
            'whatsapp_variables' => ['nullable', 'array', 'max:20'],
            'whatsapp_variables.*' => ['nullable', 'string', 'max:1024'],
            'include_phone_numbers' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'include_phone_numbers.*' => ['string', 'max:32'],
            'sms_from' => ['nullable', 'string', 'max:64'],
            'sms_media_urls' => ['nullable', 'array', 'max:10'],
            'sms_media_urls.*' => ['url:https'],
            'send_after' => ['nullable', 'string', 'max:100'],
            'delayed_option' => ['nullable', 'in:timezone,last-active,immediate'],
            'email_subject' => [self::isEmail($input) ? 'required' : 'nullable', function ($attribute, $value, $fail) {
                if (! is_string($value) && ! is_array($value)) {
                    $fail('email_subject must be a text or a {"en": ...} map.');
                }
            }],
            'email_body' => [self::isEmail($input) ? 'required' : 'nullable', function ($attribute, $value, $fail) {
                if ((! is_string($value) && ! is_array($value)) || strlen(json_encode($value)) > 500000) {
                    $fail('email_body must be HTML (500 KB at most), or a {"en": ...} map.');
                }
            }],
            'email_preheader' => ['nullable'],
            'email_from_name' => ['nullable', 'string', 'max:100'],
            'email_from_address' => ['nullable', 'email', 'max:255'],
            'email_reply_to_address' => ['nullable', 'email', 'max:255'],
            'include_email_tokens' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'include_email_tokens.*' => ['string', 'max:255'],
            'delivery_time_of_day' => ['nullable', 'string', 'max:16'],
        ], [
            'email_subject.required' => 'Email messages need an email_subject.',
            'email_body.required' => 'Email messages need an email_body.',
            'contents.required' => 'Message Notifications must have English language content',
            'target_channel.in' => 'target_channel is push, email, sms or whatsapp.',
        ])->after(function ($validator) use ($input) {
            if (($input['delayed_option'] ?? null) === 'timezone' && ! LocalTime::parse($input['delivery_time_of_day'] ?? null)) {
                $validator->errors()->add('delivery_time_of_day', 'delivery_time_of_day must be a time of day like "9:00AM" when delayed_option is "timezone".');
            }
        })->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function targeting(array $input): array
    {
        $ids = array_values(array_unique(array_merge($input['include_subscription_ids'] ?? [], $input['include_player_ids'] ?? [])));
        $externalIds = array_values(array_unique(array_map('strval', array_merge(
            $input['include_aliases']['external_id'] ?? [],
            $input['include_external_user_ids'] ?? [],
        ))));
        $segments = array_values($input['included_segments'] ?? []);
        $filters = array_values($input['filters'] ?? []);
        // Addresses (OneSignal include_email_tokens): email channel only.
        $emails = array_values(array_unique(array_map(fn ($email) => mb_strtolower(trim((string) $email)), $input['include_email_tokens'] ?? [])));
        // Numbers (OneSignal include_phone_numbers): SMS channel only.
        $phones = array_values(array_unique(array_filter(array_map(fn ($phone) => Phone::normalize((string) $phone), $input['include_phone_numbers'] ?? []))));

        if ($ids === [] && $externalIds === [] && $segments === [] && $filters === [] && $emails === [] && $phones === []) {
            throw ValidationException::withMessages(['targeting' => 'You must include which players, segments, or tags you wish to send this notification to.']);
        }

        if (in_array(self::channel($input), ['sms', 'whatsapp'], true)) {
            return array_filter([
                'channel' => self::channel($input),
                'subscription_ids' => $ids,
                'external_ids' => $externalIds,
                'phones' => $phones,
                'segments' => $segments,
                'excluded_segments' => array_values($input['excluded_segments'] ?? []),
                'filters' => $filters,
            ]);
        }

        if (self::isEmail($input)) {
            return array_filter([
                'channel' => 'email',
                'subscription_ids' => $ids,
                'external_ids' => $externalIds,
                'emails' => $emails,
                'segments' => $segments,
                'excluded_segments' => array_values($input['excluded_segments'] ?? []),
                'filters' => $filters,
            ]);
        }

        // Platform flags of OneSignal: only those set to true are kept.
        $flags = ['ios' => $input['isIos'] ?? null, 'android' => $input['isAndroid'] ?? null, 'huawei' => $input['isHuawei'] ?? null, 'amazon' => $input['isAdm'] ?? null, 'web' => $input['isAnyWeb'] ?? $input['isChromeWeb'] ?? null];
        $platforms = array_keys(array_filter($flags, fn ($value) => $value === true || $value === 'true' || $value === 1));

        return array_filter([
            'subscription_ids' => $ids,
            'external_ids' => $externalIds,
            'segments' => $segments,
            'excluded_segments' => array_values($input['excluded_segments'] ?? []),
            'filters' => $filters,
            'platforms' => $platforms,
        ]);
    }

    /** push, email or sms (OneSignal target_channel, else guessed from the fields). */
    public static function channel(array $input): string
    {
        if (($input['target_channel'] ?? null) === 'whatsapp') {
            return 'whatsapp';
        }
        if (($input['target_channel'] ?? null) === 'sms' || (! isset($input['target_channel']) && filled($input['include_phone_numbers'] ?? null))) {
            return 'sms';
        }

        return self::isEmail($input) ? 'email' : 'push';
    }

    /** An email (OneSignal: target_channel "email", or email fields without push contents). */
    public static function isEmail(array $input): bool
    {
        if (isset($input['target_channel'])) {
            return $input['target_channel'] === 'email';
        }

        return (filled($input['email_body'] ?? null) || filled($input['include_email_tokens'] ?? null)) && blank($input['contents'] ?? null);
    }

    protected function sendAfter(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            throw ValidationException::withMessages(['send_after' => 'Invalid date for send_after.']);
        }

        return $date->isFuture() ? $date->utc() : null;
    }

    /** @return array<string, string>|null */
    public function texts(mixed $texts): ?array
    {
        if (! is_array($texts)) {
            return null;
        }

        $clean = [];
        foreach ($texts as $language => $text) {
            if (is_string($text) && trim($text) !== '') {
                $clean[strtolower(substr((string) $language, 0, 7))] = $text;
            }
        }

        return $clean ?: null;
    }
}
