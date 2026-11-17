<?php

namespace App\Integrations;

use App\Models\Integration;
use App\Support\NetworkGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Every night, the send history of the day before as a gzipped CSV in a
 * bucket of the project: Amazon S3 or any S3-compatible storage
 * (Cloudflare R2, MinIO, Scaleway, OVH…), signed with AWS Signature V4.
 */
final class S3Export
{
    public const COLUMNS = ['message_id', 'message_name', 'channel', 'subscription_id', 'external_id', 'platform', 'status', 'error', 'sent_at', 'received_at', 'clicked_at'];

    /** @return array{day: string, rows: int, key: string, bytes: int} */
    public static function run(Integration $integration, CarbonImmutable $day): array
    {
        $project = $integration->project;
        $start = $day->startOfDay();
        $file = tempnam(sys_get_temp_dir(), 'carillon-export');
        $gz = gzopen($file, 'wb6');
        $rows = 0;

        try {
            gzwrite($gz, self::line(self::COLUMNS));
            DB::table('deliveries')
                ->join('messages', 'messages.id', '=', 'deliveries.message_id')
                ->join('subscriptions', 'subscriptions.id', '=', 'deliveries.subscription_id')
                ->leftJoin('subscribers', 'subscribers.id', '=', 'subscriptions.subscriber_id')
                ->where('messages.project_id', $project->id)
                ->where('deliveries.sent_at', '>=', $start)
                ->where('deliveries.sent_at', '<', $start->addDay())
                ->orderBy('deliveries.id')
                ->select([
                    'deliveries.id', 'deliveries.message_id', 'messages.name as message_name', 'messages.channel', 'deliveries.subscription_id',
                    'subscribers.external_id', 'subscriptions.type as platform', 'deliveries.status', 'deliveries.error',
                    'deliveries.sent_at', 'deliveries.received_at', 'deliveries.clicked_at',
                ])
                ->lazyById(5000, 'deliveries.id', 'id')
                ->each(function ($row) use ($gz, &$rows) {
                    $row = (array) $row;
                    gzwrite($gz, self::line(array_map(fn ($column) => $row[$column] ?? '', self::COLUMNS)));
                    $rows++;
                });
            gzclose($gz);

            $key = trim((string) $integration->setting('prefix', ''), '/');
            $key = ($key !== '' ? $key.'/' : '').'carillon/'.$project->app_id.'/deliveries/'.$day->format('Y/m/d').'.csv.gz';
            self::put($integration, $key, $file, 'application/gzip');

            return ['day' => $day->toDateString(), 'rows' => $rows, 'key' => $key, 'bytes' => filesize($file)];
        } finally {
            if (is_resource($gz)) {
                gzclose($gz);
            }
            @unlink($file);
        }
    }

    /** Uploads a small object (test of the settings). */
    public static function test(Integration $integration): string
    {
        $key = trim((string) $integration->setting('prefix', ''), '/');
        $key = ($key !== '' ? $key.'/' : '').'carillon/'.$integration->project->app_id.'/.carillon-test';
        $file = tempnam(sys_get_temp_dir(), 'carillon-test');
        file_put_contents($file, 'Carillon can write in this bucket. '.now()->toIso8601String()."\n");
        try {
            self::put($integration, $key, $file, 'text/plain');
        } finally {
            @unlink($file);
        }

        return $key;
    }

    public static function put(Integration $integration, string $key, string $file, string $type): void
    {
        $region = (string) $integration->setting('region', 'us-east-1');
        $bucket = (string) $integration->setting('bucket');
        $endpoint = rtrim((string) ($integration->setting('endpoint') ?: 'https://s3.'.$region.'.amazonaws.com'), '/');
        $parts = parse_url($endpoint);
        $scheme = $parts['scheme'] ?? 'https';
        $host = ($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = '/'.implode('/', array_map('rawurlencode', explode('/', $key)));
        if ($integration->setting('path_style', (bool) $integration->setting('endpoint'))) {
            $path = '/'.rawurlencode($bucket).$path;
        } else {
            $host = $bucket.'.'.$host;
        }

        if ($blocked = NetworkGuard::checkUrl($scheme.'://'.$host)) {
            throw new RuntimeException($blocked);
        }
        $hash = hash_file('sha256', $file);
        $headers = self::sign('PUT', $host, $path, $hash, $region, (string) $integration->setting('key_id'), (string) $integration->secret, now()->toImmutable());
        $response = Http::timeout(300)->connectTimeout(10)->withoutRedirecting()
            ->withOptions(NetworkGuard::pin($scheme.'://'.$host))
            ->withHeaders($headers + ['Content-Type' => $type])
            ->withBody(file_get_contents($file), $type)
            ->put($scheme.'://'.$host.$path);

        if (! $response->successful()) {
            preg_match('#<Code>(.*?)</Code>#', $response->body(), $code);
            preg_match('#<Message>(.*?)</Message>#', $response->body(), $message);
            throw new RuntimeException(trim('HTTP '.$response->status().' '.($code[1] ?? '').' '.($message[1] ?? '')));
        }
    }

    /**
     * AWS Signature Version 4 headers of a request without query string.
     *
     * @return array<string, string>
     */
    public static function sign(string $method, string $host, string $path, string $payloadHash, string $region, string $keyId, string $secret, CarbonImmutable $now): array
    {
        $date = $now->utc()->format('Ymd');
        $time = $now->utc()->format('Ymd\THis\Z');
        $scope = $date.'/'.$region.'/s3/aws4_request';
        $canonical = implode("\n", [
            $method, $path, '',
            'host:'.$host, 'x-amz-content-sha256:'.$payloadHash, 'x-amz-date:'.$time, '',
            'host;x-amz-content-sha256;x-amz-date', $payloadHash,
        ]);
        $toSign = implode("\n", ['AWS4-HMAC-SHA256', $time, $scope, hash('sha256', $canonical)]);
        $key = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $region, hash_hmac('sha256', $date, 'AWS4'.$secret, true), true), true), true);

        return [
            'Authorization' => 'AWS4-HMAC-SHA256 Credential='.$keyId.'/'.$scope.', SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature='.hash_hmac('sha256', $toSign, $key),
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $time,
        ];
    }

    /** @param list<mixed> $values */
    protected static function line(array $values): string
    {
        return implode(',', array_map(function ($value) {
            $value = (string) $value;

            return preg_match('/[",\n\r]/', $value) ? '"'.str_replace('"', '""', $value).'"' : $value;
        }, $values))."\n";
    }
}
