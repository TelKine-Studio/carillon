<?php

namespace App\Sms;

use App\Models\PushCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * What is left on the SMS account: units per country for Orange (contracts),
 * an amount of money for Twilio. Kept 5 minutes.
 */
final class Balance
{
    public const TTL = 300;

    /**
     * @return array{provider: string, units: ?int, amount: ?float, currency: ?string, contracts: list<array{country: ?string, units: int, expires: ?string}>, error: ?string, checked_at: string}
     */
    public static function get(PushCredential $credential, bool $fresh = false): array
    {
        $key = self::key($credential);
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::TTL, fn () => self::fetch($credential));
    }

    /** The cached value only (never calls the provider). */
    public static function cached(PushCredential $credential): ?array
    {
        return Cache::get(self::key($credential));
    }

    /** Changes with the provider and its credentials. */
    private static function key(PushCredential $credential): string
    {
        return 'sms-balance:'.$credential->id.':'.md5(json_encode($credential->details).$credential->secret);
    }

    private static function fetch(PushCredential $credential): array
    {
        $driver = $credential->detail('driver', 'twilio');
        $result = ['provider' => $driver, 'units' => null, 'amount' => null, 'currency' => null, 'contracts' => [], 'error' => null, 'checked_at' => now()->toIso8601String()];

        try {
            if ($driver === 'orange') {
                $response = Http::withToken(app(OrangeSms::class)->token($credential))->acceptJson()->timeout(8)
                    ->get(OrangeSms::BASE.'/sms/admin/v1/contracts');
                if (! $response->successful()) {
                    return ['error' => self::reason($response->json(), $response->status())] + $result;
                }
                $contracts = self::contracts($response->json());

                return ['contracts' => $contracts, 'units' => array_sum(array_column($contracts, 'units'))] + $result;
            }

            $sid = (string) $credential->detail('account_sid');
            $response = Http::withBasicAuth($sid, (string) $credential->secret)->acceptJson()->timeout(8)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Balance.json");
            if (! $response->successful()) {
                return ['error' => self::reason($response->json(), $response->status())] + $result;
            }

            return ['amount' => (float) $response->json('balance'), 'currency' => strtoupper((string) $response->json('currency'))] + $result;
        } catch (Throwable $e) {
            return ['error' => mb_substr($e->getMessage(), 0, 180)] + $result;
        }
    }

    /**
     * Every contract with remaining units, whatever the version of the Orange
     * answer (partner → contracts → serviceContracts, or a flat list).
     *
     * @return list<array{country: ?string, units: int, expires: ?string}>
     */
    private static function contracts(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (array_key_exists('availableUnits', $data)) {
            return [[
                'country' => $data['country'] ?? null,
                'units' => (int) $data['availableUnits'],
                'expires' => $data['expires'] ?? $data['expirationDate'] ?? null,
            ]];
        }

        return array_merge([], ...array_map([self::class, 'contracts'], array_values(array_filter($data, 'is_array'))));
    }

    private static function reason(mixed $body, int $status): string
    {
        $text = data_get($body, 'message') ?? data_get($body, 'error.message') ?? data_get($body, 'requestError.serviceException.text') ?? data_get($body, 'requestError.policyException.text');

        return mb_substr(trim('HTTP '.$status.' '.(is_string($text) ? $text : '')), 0, 180);
    }
}
