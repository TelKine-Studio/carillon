<?php

namespace App\Integrations;

use App\Journeys\JourneyRunner;
use App\Models\Integration;
use App\Models\Subscriber;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads the users of the app in its own database (PostgreSQL or MySQL) with
 * a SELECT query, in a read-only session, and copies their columns to
 * Carillon: external_id finds the user, language / timezone / country are
 * properties, every other column becomes a tag (empty → tag removed).
 */
final class SqlSync
{
    public const MAX_ROWS = 200_000;

    public const TIMEOUT_SECONDS = 120;

    public const PROPERTIES = ['language', 'timezone', 'country'];

    /** Tests replace the connection to the database of the app. */
    public static ?Closure $connector = null;

    /** @return array{rows: int, updated: int, created: int, unknown: int} */
    public static function run(Integration $integration): array
    {
        $query = self::query((string) $integration->setting('query'));
        $idColumn = (string) $integration->setting('id_column', 'external_id');
        $create = (bool) $integration->setting('create_users', false);
        $name = self::connect($integration);
        $connection = DB::connection($name);

        try {
            self::readOnly($connection);

            $counts = ['rows' => 0, 'updated' => 0, 'created' => 0, 'unknown' => 0];
            $chunk = [];
            foreach ($connection->cursor($query) as $row) {
                $row = (array) $row;
                if (! array_key_exists($idColumn, $row)) {
                    throw new InvalidArgumentException(__('The query must return a column “:column” with the external id of each user.', ['column' => $idColumn]));
                }
                if (++$counts['rows'] > self::MAX_ROWS) {
                    $counts['rows'] = self::MAX_ROWS;
                    break;
                }
                if (filled($row[$idColumn])) {
                    $chunk[mb_substr((string) $row[$idColumn], 0, 255)] = $row;
                }
                if (count($chunk) >= 500) {
                    self::apply($integration, $chunk, $idColumn, $create, $counts);
                    $chunk = [];
                }
            }
            self::apply($integration, $chunk, $idColumn, $create, $counts);

            return $counts;
        } finally {
            DB::purge($name);
        }
    }

    /**
     * Opens a read-only transaction with a time limit: the query cannot
     * write, whatever the rights of the user. Closed with the connection.
     */
    public static function readOnly(Connection $connection): void
    {
        $ms = self::TIMEOUT_SECONDS * 1000;
        match ($connection->getDriverName()) {
            'pgsql' => (function () use ($connection, $ms) {
                $connection->beginTransaction();
                $connection->statement('SET TRANSACTION READ ONLY');
                $connection->statement('SET LOCAL statement_timeout = '.$ms);
            })(),
            'mysql', 'mariadb' => (function () use ($connection, $ms) {
                try {
                    $connection->statement('SET SESSION MAX_EXECUTION_TIME = '.$ms); // MySQL
                } catch (QueryException) {
                    $connection->statement('SET SESSION max_statement_time = '.self::TIMEOUT_SECONDS); // MariaDB
                }
                $connection->unprepared('START TRANSACTION READ ONLY');
            })(),
            default => null,
        };
    }

    /** Only one SELECT (or WITH … SELECT) statement. */
    public static function query(string $query): string
    {
        $query = rtrim(trim($query), "; \n\r\t");
        if (! preg_match('/^(select|with)\b/i', $query) || str_contains($query, ';')) {
            throw new InvalidArgumentException(__('Only one SELECT query is allowed.'));
        }

        return $query;
    }

    /** Connection to the database of the app, registered for this run. */
    public static function connect(Integration $integration): string
    {
        $name = 'integration_'.$integration->id;
        if (self::$connector) {
            (self::$connector)($integration, $name);

            return $name;
        }

        $pgsql = $integration->kind === 'postgres';
        config(["database.connections.$name" => array_filter([
            'driver' => $pgsql ? 'pgsql' : 'mysql',
            'host' => $integration->setting('host'),
            'port' => (int) $integration->setting('port', $pgsql ? 5432 : 3306),
            'database' => $integration->setting('database'),
            'username' => $integration->setting('username'),
            'password' => (string) $integration->secret,
            'charset' => $pgsql ? 'utf8' : 'utf8mb4',
            'sslmode' => $pgsql ? $integration->setting('sslmode', 'prefer') : null,
            'options' => $pgsql ? [] : [\PDO::ATTR_TIMEOUT => 10],
            'prefix' => '',
        ], fn ($v) => $v !== null)]);

        return $name;
    }

    /** @param array<string, array<string, mixed>> $rows */
    protected static function apply(Integration $integration, array $rows, string $idColumn, bool $create, array &$counts): void
    {
        if (! $rows) {
            return;
        }
        $journeys = app(JourneyRunner::class);
        $existing = Subscriber::query()->where('project_id', $integration->project_id)
            ->whereIn('external_id', array_map('strval', array_keys($rows)))->get()->keyBy('external_id');

        foreach ($rows as $externalId => $row) {
            $externalId = (string) $externalId;
            $subscriber = $existing[$externalId] ?? null;
            if (! $subscriber) {
                if (! $create) {
                    $counts['unknown']++;

                    continue;
                }
                $subscriber = new Subscriber(['project_id' => $integration->project_id, 'external_id' => $externalId]);
            }

            $tagsBefore = $subscriber->tags;
            $tags = [];
            foreach ($row as $column => $value) {
                $column = (string) $column;
                if ($column === $idColumn) {
                    continue;
                }
                if (in_array($column, self::PROPERTIES, true)) {
                    self::property($subscriber, $column, $value);

                    continue;
                }
                $tags[$column] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
            $subscriber->mergeTags($tags);

            if (! $subscriber->exists) {
                $subscriber->save();
                $counts['created']++;
            } elseif ($subscriber->isDirty()) {
                $subscriber->save();
                $counts['updated']++;
            } else {
                continue;
            }
            $journeys->tagsChanged($subscriber, $tagsBefore);
        }
    }

    protected static function property(Subscriber $subscriber, string $column, mixed $value): void
    {
        $value = is_string($value) ? trim($value) : null;
        if (! filled($value)) {
            return;
        }
        match ($column) {
            'language' => $subscriber->language = strtolower(substr($value, 0, 2)),
            'country' => strlen($value) === 2 ? $subscriber->country = strtoupper($value) : null,
            'timezone' => in_array($value, timezone_identifiers_list(), true) ? $subscriber->timezone = $value : null,
        };
    }
}
