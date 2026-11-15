<?php

namespace App\Audience;

use App\Models\Project;
use App\Models\Segment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the targeting of a message (OneSignal format) into the query of the
 * devices to notify:
 * - include_subscription_ids / include_aliases.external_id: these devices
 *   or people only;
 * - otherwise included_segments (minus excluded_segments) and/or filters;
 * - platforms (ios, android, web) restrict the result.
 *
 * Filters are AND-ed, an {"operator": "OR"} entry starts a new group.
 */
class Audience
{
    /** Tag keys usable in filters (they are inlined as JSON paths). */
    protected const KEY = '/^[A-Za-z0-9_.\- ]{1,128}$/';

    public const RELATIONS = ['=', '!=', '>', '<', 'exists', 'not_exists', 'time_elapsed_gt', 'time_elapsed_lt'];

    /**
     * @param  array<string, mixed>  $targeting
     * @return Builder<Subscription>
     */
    public function query(Project $project, array $targeting): Builder
    {
        $query = Subscription::query()
            ->select('subscriptions.*')
            ->join('subscribers', 'subscribers.id', '=', 'subscriptions.subscriber_id')
            ->where('subscriptions.project_id', $project->id)
            ->where('subscriptions.enabled', true)
            ->whereNull('subscriptions.invalid_at')
            ->whereNotNull('subscriptions.token');

        if ($platforms = $targeting['platforms'] ?? null) {
            $query->whereIn('subscriptions.type', $platforms);
        }

        if ($ids = $targeting['subscription_ids'] ?? null) {
            return $query->whereIn('subscriptions.id', $ids);
        }

        if ($externalIds = $targeting['external_ids'] ?? null) {
            return $query->whereIn('subscribers.external_id', $externalIds);
        }

        $segments = $targeting['segments'] ?? [];
        $filters = $targeting['filters'] ?? [];

        if ($segments !== []) {
            $query->where(function (Builder $any) use ($project, $segments) {
                foreach ($segments as $name) {
                    $any->orWhere(fn (Builder $segment) => $this->applySegment($segment, $project, $name));
                }
            });
        }

        if ($filters !== []) {
            $query->where(fn (Builder $q) => $this->applyFilters($q, $filters));
        }

        // Excluded with a subquery: negating the conditions would also drop
        // devices for which they are unknown (NULL tags).
        foreach ($targeting['excluded_segments'] ?? [] as $name) {
            $members = Subscription::query()
                ->select('subscriptions.id')
                ->join('subscribers', 'subscribers.id', '=', 'subscriptions.subscriber_id')
                ->where('subscriptions.project_id', $project->id)
                ->where(fn (Builder $segment) => $this->applySegment($segment, $project, $name));
            $query->whereNotIn('subscriptions.id', $members);
        }

        // Nothing targeted: nobody (OneSignal requires an explicit target).
        if ($segments === [] && $filters === []) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /** @param Builder<Subscription> $query */
    protected function applySegment(Builder $query, Project $project, string $name): void
    {
        match ($name) {
            'All', 'Subscribed Users', 'Total Subscriptions' => $query->whereRaw('1 = 1'),
            'Active Users' => $query->where('subscriptions.last_active_at', '>=', now()->subDays(7)),
            'Engaged Users' => $query->where('subscriptions.sessions', '>=', 5)->where('subscriptions.last_active_at', '>=', now()->subDays(30)),
            'Inactive Users' => $query->where(fn (Builder $q) => $q->whereNull('subscriptions.last_active_at')->orWhere('subscriptions.last_active_at', '<', now()->subDays(7))),
            default => $this->applyCustomSegment($query, $project, $name),
        };
    }

    /** @param Builder<Subscription> $query */
    protected function applyCustomSegment(Builder $query, Project $project, string $name): void
    {
        $segment = Segment::query()->where('project_id', $project->id)->where('name', $name)->first();

        if (! $segment) {
            throw ValidationException::withMessages(['included_segments' => __('Unknown segment: :name', ['name' => $name])]);
        }

        $this->applyFilters($query, $segment->filters ?? []);
    }

    /**
     * @param  Builder<Subscription>  $query
     * @param  list<array<string, mixed>>  $filters
     */
    public function applyFilters(Builder $query, array $filters): void
    {
        $groups = [[]];
        foreach ($filters as $filter) {
            if (strtoupper((string) ($filter['operator'] ?? '')) === 'OR') {
                $groups[] = [];
            } else {
                $groups[array_key_last($groups)][] = $filter;
            }
        }

        $query->where(function (Builder $any) use ($groups) {
            foreach (array_filter($groups) as $group) {
                $any->orWhere(function (Builder $all) use ($group) {
                    foreach ($group as $filter) {
                        $this->applyFilter($all, $filter);
                    }
                });
            }
        });
    }

    /**
     * @param  Builder<Subscription>  $query
     * @param  array<string, mixed>  $filter
     */
    protected function applyFilter(Builder $query, array $filter): void
    {
        $field = $filter['field'] ?? null;
        $relation = $filter['relation'] ?? '=';
        $value = $filter['value'] ?? null;

        if (! in_array($relation, self::RELATIONS, true)) {
            throw ValidationException::withMessages(['filters' => __('Unknown relation: :relation', ['relation' => $relation])]);
        }

        match ($field) {
            'tag' => $this->tagFilter($query, (string) ($filter['key'] ?? ''), $relation, $value),
            'language' => $this->compare($query, 'subscribers.language', $relation, $value),
            'country' => $this->compare($query, 'subscribers.country', $relation, strtoupper((string) $value)),
            'app_version' => $this->compare($query, 'subscriptions.app_version', $relation, $value),
            'session_count' => $this->compare($query, 'subscriptions.sessions', $relation, (int) $value),
            'external_id' => $this->compare($query, 'subscribers.external_id', $relation, $value),
            'device_type' => $this->compare($query, 'subscriptions.type', $relation, $value),
            'last_session' => $this->hoursAgo($query, 'subscriptions.last_active_at', $relation, $filter),
            'first_session' => $this->hoursAgo($query, 'subscriptions.created_at', $relation, $filter),
            default => throw ValidationException::withMessages(['filters' => __('Unknown filter field: :field', ['field' => (string) $field])]),
        };
    }

    /** @param Builder<Subscription> $query */
    protected function compare(Builder $query, string $column, string $relation, mixed $value): void
    {
        match ($relation) {
            'exists' => $query->whereNotNull($column),
            'not_exists' => $query->whereNull($column),
            '!=' => $query->where(fn (Builder $q) => $q->where($column, '!=', $value)->orWhereNull($column)),
            default => $query->where($column, $relation, $value),
        };
    }

    /**
     * last_session / first_session: "hours_ago" (or value) with > (longer ago) or < (more recent).
     *
     * @param  Builder<Subscription>  $query
     * @param  array<string, mixed>  $filter
     */
    protected function hoursAgo(Builder $query, string $column, string $relation, array $filter): void
    {
        $hours = (float) ($filter['hours_ago'] ?? $filter['value'] ?? 0);
        $limit = now()->subSeconds((int) round($hours * 3600));

        $relation === '>'
            ? $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, '<', $limit))
            : $query->where($column, '>=', $limit);
    }

    /** @param Builder<Subscription> $query */
    protected function tagFilter(Builder $query, string $key, string $relation, mixed $value): void
    {
        if (! preg_match(self::KEY, $key)) {
            throw ValidationException::withMessages(['filters' => __('Invalid tag key: :key', ['key' => $key])]);
        }

        $text = $this->tagText($key);

        match ($relation) {
            'exists' => $query->whereRaw("{$text} is not null"),
            'not_exists' => $query->whereRaw("{$text} is null"),
            '=' => $query->whereRaw("{$text} = ?", [(string) $value]),
            '!=' => $query->whereRaw("({$text} is null or {$text} != ?)", [(string) $value]),
            '>', '<' => $query->whereRaw("{$this->tagNumber($key)} {$relation} ?", [(float) $value]),
            // Tag holding a UNIX timestamp: more (or less) than "value" seconds ago.
            'time_elapsed_gt' => $query->whereRaw("{$this->tagNumber($key)} < ?", [now()->getTimestamp() - (int) $value]),
            'time_elapsed_lt' => $query->whereRaw("{$this->tagNumber($key)} > ?", [now()->getTimestamp() - (int) $value]),
        };
    }

    protected function tagText(string $key): string
    {
        $quoted = str_replace("'", "''", $key);

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "(subscribers.tags::jsonb ->> '{$quoted}')",
            'mysql', 'mariadb' => "json_unquote(json_extract(subscribers.tags, '$.\"{$quoted}\"'))",
            default => "json_extract(subscribers.tags, '$.\"{$quoted}\"')",
        };
    }

    /** Numeric value of a tag (null when the tag is not a number). */
    protected function tagNumber(string $key): string
    {
        $text = $this->tagText($key);

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "(case when {$text} ~ '^-?[0-9]+(\\.[0-9]+)?$' then ({$text})::numeric end)",
            'mysql', 'mariadb' => "cast({$text} as decimal(30,10))",
            default => "cast({$text} as real)",
        };
    }
}
