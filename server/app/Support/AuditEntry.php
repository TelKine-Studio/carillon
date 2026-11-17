<?php

namespace App\Support;

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Turns an audit log entry into something a person reads: a sentence
 * ("Ada created the project Shop"), an icon, a tone and labelled details.
 */
class AuditEntry
{
    /** Actions shown in red: something was removed, weakened or refused. */
    public const DANGER = [
        'auth.login_failed', 'auth.2fa_failed', 'auth.2fa_disabled', 'user.deleted',
        'project.deleted', 'project.member_removed', 'api_key.revoked', 'push.removed',
        'webhook.deleted', 'template.deleted',
    ];

    /** Metadata keys already used in the sentence, or of no use to a reader. */
    protected const HIDDEN = ['template', 'app_id'];

    /** @param  array<string, string>  $projects  app_id => project name */
    public function __construct(public AuditLog $log, protected array $projects = []) {}

    /** Filters of the list, by first segment of the action. */
    public static function groups(): array
    {
        return [
            '' => __('All events'),
            'auth' => __('Sign-ins'),
            'user' => __('Users'),
            'project' => __('Projects'),
            'api_key' => __('API keys'),
            'push' => __('Delivery settings'),
            'webhook' => __('Webhooks'),
            'template' => __('Templates'),
            'audience' => __('Audience'),
            'settings' => __('Instance settings'),
        ];
    }

    public function icon(): string
    {
        return match (Str::before($this->log->action, '.')) {
            'auth' => match ($this->log->action) {
                'auth.logout' => 'logout',
                'auth.2fa_enabled', 'auth.2fa_disabled', 'auth.2fa_failed' => 'shield',
                default => 'lock',
            },
            'user' => $this->log->action === 'user.invited' ? 'user-plus' : 'user',
            'project' => str_starts_with($this->log->action, 'project.member') ? 'users' : 'layers',
            'api_key' => 'key',
            'push' => $this->metadata('provider') === 'email' ? 'mail' : 'bell',
            'webhook' => 'link',
            'template' => 'file',
            'audience' => 'upload',
            'settings' => 'settings',
            default => 'activity',
        };
    }

    public function danger(): bool
    {
        return in_array($this->log->action, self::DANGER, true);
    }

    /** The sentence, with names in bold. */
    public function sentence(): HtmlString
    {
        $pattern = match ($this->log->action) {
            'auth.login' => 'Sign-in by :actor',
            'auth.sso_login' => 'Single sign-on by :actor',
            'auth.login_failed' => 'Failed sign-in for :subject',
            'auth.logout' => 'Sign-out by :actor',
            'auth.password_reset' => 'Password reset by :subject',
            'auth.2fa_enabled' => 'Two-factor authentication enabled by :actor',
            'auth.2fa_disabled' => 'Two-factor authentication disabled by :actor',
            'auth.2fa_failed' => 'Wrong two-factor code for :subject',
            'auth.email_changed' => ':actor changed their email address',
            'project.identity_verification' => ':actor changed the identity verification of :subject',
            'user.invited' => ':actor invited :subject',
            'user.activated' => ':subject activated their account',
            'user.updated' => ':actor updated the account of :subject',
            'user.deleted' => ':actor deleted the account of :subject',
            'project.created' => ':actor created the project :subject',
            'project.updated' => ':actor updated the project :subject',
            'project.deleted' => ':actor deleted the project :subject',
            'project.member_added' => ':actor added :member to the project :subject',
            'project.member_updated' => ':actor changed the role of :member in the project :subject',
            'project.member_removed' => ':actor removed :member from the project :subject',
            'api_key.created' => ':actor created the API key :subject',
            'api_key.revoked' => ':actor revoked the API key :subject',
            'push.updated' => ':actor changed the delivery settings of the project :subject',
            'push.removed' => ':actor removed delivery settings from the project :subject',
            'webhook.created' => ':actor added a webhook to the project :subject',
            'webhook.secret_rotated' => ':actor renewed a webhook secret of the project :subject',
            'webhook.deleted' => ':actor deleted a webhook of the project :subject',
            'template.created' => ':actor saved the template :item in the project :subject',
            'template.deleted' => ':actor deleted the template :item from the project :subject',
            'audience.imported' => ':actor imported an audience into the project :subject',
            'audience.sync_updated' => ':actor set up the OneSignal sync of the project :subject',
            'audience.sync_removed' => ':actor removed the OneSignal sync of the project :subject',
            'settings.updated' => ':actor changed the instance settings',
            default => null,
        };

        if (! $pattern) {
            return new HtmlString(e(Audit::label($this->log->action)));
        }

        $bold = fn (?string $value) => '<strong class="font-semibold text-zinc-900 dark:text-white">'.e($value ?: __('unknown')).'</strong>';

        $sentence = __($pattern, [
            'actor' => $this->log->user ? $bold($this->log->user->name) : e(__('someone')),
            'subject' => $bold($this->subject()),
            'member' => $bold($this->metadata('user') ?? $this->metadata('member')),
            'item' => $bold($this->metadata('name')),
        ]);

        // "someone" may open the sentence.
        return new HtmlString(Str::ucfirst($sentence));
    }

    /** Name of what the action was about, even once it has been deleted. */
    public function subject(): ?string
    {
        return $this->log->description
            ?? $this->metadata('name')
            ?? $this->metadata('email')
            ?? ($this->metadata('project') ? ($this->projects[$this->metadata('project')] ?? null) : null);
    }

    /** @return array<int, array{label: string, value: string, mono?: bool}> */
    public function details(): array
    {
        $used = match ($this->log->action) {
            'auth.login_failed' => ['email'],
            'project.created', 'project.deleted', 'template.created', 'template.deleted', 'api_key.created', 'api_key.revoked' => ['name'],
            'project.member_added', 'project.member_updated', 'project.member_removed' => ['user'],
            default => [],
        };

        $rows = [];

        foreach ((array) $this->log->metadata as $key => $value) {
            if (in_array($key, [...self::HIDDEN, ...$used], true) || $value === null || $value === '' || $value === []) {
                continue;
            }

            $rows[] = $this->detail((string) $key, $value);
        }

        return $rows;
    }

    /** Device, network and exact time of the action. */
    public function context(): array
    {
        return array_values(array_filter([
            ['label' => __('Date'), 'value' => $this->log->created_at->isoFormat('LLLL')],
            $this->log->ip_address ? ['label' => __('IP address'), 'value' => $this->log->ip_address, 'mono' => true] : null,
            $this->log->user_agent ? ['label' => __('Device'), 'value' => UserAgent::describe($this->log->user_agent)] : null,
            $this->log->user_agent ? ['label' => __('Full user agent'), 'value' => $this->log->user_agent, 'mono' => true, 'small' => true] : null,
            $this->log->user ? ['label' => __('Account'), 'value' => $this->log->user->email] : null,
        ]));
    }

    public function device(): ?string
    {
        return UserAgent::describe($this->log->user_agent);
    }

    protected function detail(string $key, mixed $value): array
    {
        $yes = fn ($v) => $v ? __('Yes') : __('No');

        return match ($key) {
            'role' => ['label' => __('Role'), 'value' => (Role::tryFrom((string) $value) ?? ProjectRole::tryFrom((string) $value))?->label() ?? (string) $value],
            'provider' => ['label' => __('Service'), 'value' => match ($value) {
                'apns' => 'Apple (APNs)', 'fcm' => 'Firebase (FCM)', 'webpush' => __('Web push'), 'email' => __('Email'), default => (string) $value,
            }],
            'driver' => ['label' => __('Sending method'), 'value' => match ($value) {
                'smtp' => 'SMTP', 'resend' => 'Resend', 'ses' => 'Amazon SES', 'postmark' => 'Postmark', 'mailgun' => 'Mailgun', 'direct' => __('Direct delivery'), default => (string) $value,
            }],
            'section' => ['label' => __('Section'), 'value' => match ($value) {
                'general' => __('General'), 'security' => __('Security'), 'mail' => __('Email'), default => Str::headline((string) $value),
            }],
            'changed' => ['label' => __('Changed'), 'value' => collect((array) $value)->map(fn ($field) => match ($field) {
                'name' => __('Name'), 'email' => __('Email address'), 'role' => __('Role'), 'is_active' => __('Status'), 'password' => __('Password'), default => Str::headline((string) $field),
            })->implode(', ')],
            'project' => ['label' => __('Project'), 'value' => $this->projects[$value] ?? (string) $value],
            'user', 'member' => ['label' => __('Member'), 'value' => (string) $value],
            'active' => ['label' => __('Status'), 'value' => $value ? __('Active') : __('Suspended')],
            'two_factor' => ['label' => __('Two-factor authentication'), 'value' => $yes($value)],
            'sso' => ['label' => __('Single sign-on'), 'value' => $yes($value)],
            'enabled' => ['label' => __('Every night'), 'value' => $yes($value)],
            'generated' => ['label' => __('Keys generated by Carillon'), 'value' => $yes($value)],
            'require_2fa_admins' => ['label' => __('Two-factor required for administrators'), 'value' => $yes($value)],
            'url' => ['label' => __('URL'), 'value' => (string) $value, 'mono' => true],
            'file' => ['label' => __('File'), 'value' => (string) $value],
            'email' => ['label' => __('Email address'), 'value' => (string) $value],
            'name' => ['label' => __('Name'), 'value' => (string) $value],
            default => ['label' => Str::headline($key), 'value' => $this->format($value)],
        };
    }

    protected function format(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? __('Yes') : __('No'),
            is_array($value) => collect($value)->map(fn ($v) => $this->format($v))->implode(', '),
            is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) === 1 => Carbon::parse($value)->isoFormat('LLL'),
            default => (string) $value,
        };
    }

    protected function metadata(string $key): mixed
    {
        return $this->log->metadata[$key] ?? null;
    }
}
