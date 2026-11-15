<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Creates or updates a template from message fields (OneSignal format). */
class SaveTemplate
{
    /** Options a template keeps: everything but the audience and the delivery. */
    public const OPTIONS = [
        'data', 'url', 'app_url', 'web_url', 'big_picture', 'ios_attachments', 'chrome_web_image', 'chrome_web_icon',
        'chrome_web_badge', 'firefox_icon', 'buttons', 'web_buttons', 'ios_sound', 'android_sound', 'ios_badgeType',
        'ios_badgeCount', 'thread_id', 'ios_category', 'ios_interruption_level', 'ios_relevance_score',
        'android_channel_id', 'android_accent_color', 'small_icon', 'large_icon', 'android_group', 'android_visibility',
        'priority', 'ttl', 'collapse_id', 'web_push_topic', 'content_available', 'require_interaction',
    ];

    public function __construct(protected CreateMessage $messages) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Project $project, array $input, ?Template $template = null, ?User $author = null): Template
    {
        Validator::make($input, [
            'name' => [$template ? 'sometimes' : 'required', 'string', 'max:255'],
            'contents' => [$template ? 'sometimes' : 'required', 'array', 'min:1'],
            'contents.*' => ['nullable', 'string', 'max:4000'],
            'headings' => ['nullable', 'array'], 'headings.*' => ['nullable', 'string', 'max:500'],
            'subtitle' => ['nullable', 'array'], 'subtitle.*' => ['nullable', 'string', 'max:500'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'data' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer', 'between:1,10'],
            'ttl' => ['nullable', 'integer', 'min:0', 'max:2419200'],
        ], ['contents.required' => 'A template needs contents.'])->validate();

        $attributes = [];
        if (array_key_exists('name', $input)) {
            $attributes['name'] = $input['name'];
        }
        foreach (['headings', 'subtitle', 'contents'] as $field) {
            if (array_key_exists($field, $input)) {
                $attributes[$field] = $this->messages->texts($input[$field]);
            }
        }
        if (array_key_exists('contents', $attributes) && ! $attributes['contents']) {
            throw ValidationException::withMessages(['contents' => 'A template needs contents.']);
        }

        $options = array_filter(Arr::only($input, self::OPTIONS), fn ($value) => $value !== null);
        if ($template) {
            // Partial update (API PATCH): unspecified options are kept.
            $options = $input['_replace_options'] ?? false ? $options : array_merge($template->options ?? [], $options);
        }
        $attributes['options'] = $options ?: null;

        if ($template) {
            $template->update($attributes);

            return $template;
        }

        return $project->templates()->create($attributes + ['created_by' => $author?->id]);
    }
}
