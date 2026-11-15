<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reusable content of a message (texts, link, image, buttons, options),
 * sent from the dashboard or with "template_id" in the API.
 */
#[Fillable(['project_id', 'name', 'headings', 'subtitle', 'contents', 'options', 'created_by'])]
class Template extends Model
{
    use HasUuids;

    /** Fields of a message a template may hold (no audience, no delivery). */
    public const FIELDS = ['headings', 'subtitle', 'contents'];

    protected function casts(): array
    {
        return [
            'headings' => 'array',
            'subtitle' => 'array',
            'contents' => 'array',
            'options' => 'array',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    /** Message fields in the OneSignal format, merged under the request. */
    public function toInput(): array
    {
        return array_filter([
            'headings' => $this->headings,
            'subtitle' => $this->subtitle,
            'contents' => $this->contents,
            ...($this->options ?? []),
        ], fn ($value) => $value !== null);
    }
}
