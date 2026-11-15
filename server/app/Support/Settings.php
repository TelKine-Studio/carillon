<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use PDOException;

/**
 * Application settings stored in the database and editable from the
 * administration UI. Values that would traditionally live in a .env file
 * (site name, mail server, single sign-on, ...) are managed here so
 * that an instance can be fully configured from the browser.
 */
class Settings
{
    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'app.name' => 'Carillon',
        'app.url' => null,
        'app.locale' => 'en',

        // Members may create their own projects (otherwise administrators only).
        'projects.users_can_create' => false,
        // Delivery and click history kept per message, in days.
        'messages.retention_days' => 90,

        'security.require_2fa_admins' => false,
        'sso.enabled' => false,
        'sso.label' => null,
        'sso.issuer' => null,
        'sso.client_id' => null,
        'sso.client_secret' => null,
        'sso.auto_create' => false,
        'sso.allowed_domains' => null,

        'mail.mailer' => 'log',
        'mail.host' => null,
        'mail.port' => 587,
        'mail.scheme' => null,
        'mail.username' => null,
        'mail.password' => null,
        'mail.resend_key' => null,
        'mail.from_address' => null,
        'mail.from_name' => null,
    ];

    /** Settings encrypted at rest with the application key. */
    public const ENCRYPTED = ['mail.password', 'mail.resend_key', 'sso.client_secret'];

    /** @var array<string, mixed>|null */
    protected ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->load();

        if (array_key_exists($key, $values) && $values[$key] !== null) {
            return $values[$key];
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) ($this->get($key) ?? $default);
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) ($this->get($key) ?? $default);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key) ?? $default, FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array<string, mixed> $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $stored = $value === null ? null : json_encode($value);

            if ($stored !== null && in_array($key, self::ENCRYPTED, true)) {
                $stored = Crypt::encryptString($stored);
            }

            Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
        }

        $this->values = null;
        $this->applyToConfig();
    }

    public function ssoEnabled(): bool
    {
        return $this->boolean('sso.enabled') && filled($this->get('sso.issuer')) && filled($this->get('sso.client_id'));
    }

    /**
     * Push the stored settings into Laravel's runtime configuration.
     */
    public function applyToConfig(): void
    {
        $values = $this->load();

        if ($values === []) {
            return;
        }

        config([
            'app.name' => $this->string('app.name'),
            'app.locale' => $this->string('app.locale'),
        ]);

        if ($url = $this->get('app.url')) {
            config(['app.url' => rtrim($url, '/')]);

            // HTTP requests keep using the host they were made on, so that a
            // misconfigured URL can never lock administrators out. Console
            // processes (queue, scheduler) have no request to rely on.
            if (app()->runningInConsole()) {
                app('url')->forceRootUrl(rtrim($url, '/'));
            }
        }

        $this->applyMailConfig();
    }

    protected function applyMailConfig(): void
    {
        $mailer = $this->string('mail.mailer');

        config([
            'mail.default' => in_array($mailer, ['smtp', 'resend', 'log'], true) ? $mailer : 'log',
            'mail.mailers.smtp.host' => $this->get('mail.host'),
            'mail.mailers.smtp.port' => $this->integer('mail.port'),
            'mail.mailers.smtp.scheme' => $this->get('mail.scheme'),
            'mail.mailers.smtp.username' => $this->get('mail.username'),
            'mail.mailers.smtp.password' => $this->get('mail.password'),
            'services.resend.key' => $this->get('mail.resend_key'),
            'mail.from.address' => $this->get('mail.from_address') ?: 'carillon@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            'mail.from.name' => $this->get('mail.from_name') ?: $this->string('app.name'),
        ]);
    }

    /** Forget the in-memory cache (useful in long running processes). */
    public function refresh(): void
    {
        $this->values = null;
    }

    /** @return array<string, mixed> */
    protected function load(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            $rows = Setting::query()->pluck('value', 'key')->all();
        } catch (QueryException|PDOException) {
            // The database is not migrated yet: fall back to defaults.
            return [];
        }

        return $this->values = Arr::map($rows, fn (?string $value, string $key) => $this->decode($key, $value));
    }

    protected function decode(string $key, ?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($key, self::ENCRYPTED, true)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException|MissingAppKeyException) {
                // Wrong or missing APP_KEY: the secret is unreadable, but the
                // rest of the application must keep working.
                return null;
            }
        }

        return json_decode($value, true);
    }
}
