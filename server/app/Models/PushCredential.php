<?php

namespace App\Models;

use App\Email\Drivers;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credentials of one push provider for a project:
 * - apns: details {key_id, team_id, bundle_id}, secret = .p8 private key;
 * - fcm: details {project_id, client_email}, secret = service account JSON;
 * - webpush: details {public_key, subject}, secret = VAPID private key;
 * - hms: details {app_id}, secret = app secret (Huawei Push Kit);
 * - adm: details {client_id}, secret = client secret (Amazon Device Messaging);
 * - email: details {driver, from_address, ...} (see App\Email\Drivers),
 *   secret = password, API key or DKIM private key;
 * - sms: details {driver: twilio, account_sid, from}, secret = auth token,
 *   or {driver: orange, client_id, sender_address, sender_name}, secret = client secret;
 * - whatsapp: details {phone_number_id, business_account_id}, secret = access token.
 */
#[Fillable(['project_id', 'provider', 'details', 'secret', 'verified_at', 'last_error'])]
#[Hidden(['secret'])]
class PushCredential extends Model
{
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'secret' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function detail(string $key, mixed $default = null): mixed
    {
        return $this->details[$key] ?? $default;
    }

    public function isUsable(): bool
    {
        // An SMTP server may need no password (relay on a private network).
        if ($this->provider === 'email') {
            return Drivers::usable($this);
        }

        if (blank($this->secret)) {
            return false;
        }

        return match ($this->provider) {
            'apns' => filled($this->detail('key_id')) && filled($this->detail('team_id')) && filled($this->detail('bundle_id')),
            'fcm' => filled($this->detail('project_id')),
            'webpush' => filled($this->detail('public_key')),
            'hms' => filled($this->detail('app_id')),
            'whatsapp' => filled($this->detail('phone_number_id')),
            'adm' => filled($this->detail('client_id')),
            'sms' => $this->detail('driver') === 'orange'
                ? filled($this->detail('client_id')) && filled($this->detail('sender_address'))
                : filled($this->detail('account_sid')) && filled($this->detail('from')),
            'email' => Drivers::usable($this),
            default => false,
        };
    }
}
