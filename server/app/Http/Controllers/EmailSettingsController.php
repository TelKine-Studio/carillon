<?php

namespace App\Http\Controllers;

use App\Email\Drivers;
use App\Email\EmailBuilder;
use App\Email\Transports;
use App\Models\Project;
use App\Models\PushCredential;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use Throwable;

/** How a project sends its emails (Settings → Platforms → Email). */
class EmailSettingsController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $existing = $project->credential('email');
        $driver = (string) $request->input('driver');

        $rules = [
            'driver' => ['required', Rule::in(array_keys(Drivers::ALL))],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'rate' => ['nullable', 'integer', 'min:1', 'max:500'],
            'tracking' => ['nullable', 'boolean'],
        ];
        // The secret is kept when left empty (it is never shown again).
        $keepSecret = $existing && $existing->detail('driver') === $driver;
        $secretRule = [$keepSecret ? 'nullable' : 'required', 'string', 'max:500'];

        $rules += match ($driver) {
            'smtp' => [
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
                'username' => ['nullable', 'string', 'max:255'],
                'secret' => ['nullable', 'string', 'max:500'],
            ],
            'resend', 'postmark' => ['secret' => $secretRule, 'message_stream' => ['nullable', 'string', 'max:100']],
            'ses' => ['aws_key_id' => ['required', 'string', 'max:128'], 'region' => ['required', 'string', 'regex:/^[a-z]{2}-[a-z]+-\d$/'], 'secret' => $secretRule],
            'mailgun' => ['domain' => ['required', 'string', 'max:255'], 'region' => ['required', Rule::in(['us', 'eu'])], 'secret' => $secretRule],
            'direct' => ['helo' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/']],
            default => [],
        };

        $data = $request->validate($rules, ['region.regex' => __('An AWS region, e.g. eu-west-1.')]);

        $details = [
            'driver' => $driver,
            'from_address' => mb_strtolower($data['from_address']),
            'from_name' => $data['from_name'] ?? null,
            'reply_to' => $data['reply_to'] ?? null,
            'rate' => $data['rate'] ?? null,
            'tracking' => $request->boolean('tracking'),
            // Keeps the address of the bounce webhook stable.
            'webhook_secret' => $existing?->detail('webhook_secret') ?: Str::random(32),
        ];
        if (isset($data['aws_key_id'])) {
            $details['key_id'] = $data['aws_key_id'];
        }
        foreach (['host', 'port', 'encryption', 'username', 'region', 'domain', 'message_stream', 'helo'] as $field) {
            if (array_key_exists($field, $data)) {
                $details[$field] = $data[$field];
            }
        }

        $secret = filled($data['secret'] ?? null) ? trim($data['secret']) : ($keepSecret ? $existing->secret : null);

        if ($driver === 'direct') {
            // DKIM signs the emails for the domain of the sender.
            $domain = Str::after($details['from_address'], '@');
            $sameKey = $keepSecret && $existing->detail('dkim_domain') === $domain;
            $details['dkim_domain'] = $domain;
            $details['dkim_selector'] = $sameKey ? $existing->detail('dkim_selector') : 'carillon'.now()->format('ym');
            $secret = $sameKey ? $existing->secret : self::dkimKey();
            $details['helo'] ??= $domain;
        }

        PushCredential::query()->updateOrCreate(
            ['project_id' => $project->id, 'provider' => 'email'],
            ['details' => array_filter($details, fn ($value) => $value !== null), 'secret' => $secret, 'last_error' => null],
        );
        Audit::log('push.updated', $project, ['provider' => 'email', 'driver' => $driver]);

        return back()->with('status', __('Email settings saved.'));
    }

    /** Sends a test email to the given address. */
    public function test(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);
        $data = $request->validate(['to' => ['required', 'email', 'max:255']]);
        $credential = $project->credential('email');
        abort_unless($credential?->isUsable(), 404);

        $html = '<!doctype html><html><body style="font:15px/1.6 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#18181b;padding:24px">'
            .'<p style="margin:0 0 12px;font-size:18px;font-weight:600">'.e(__('Your emails are ready')).'</p>'
            .'<p style="margin:0">'.e(__(':project can now send emails with Carillon (:driver).', ['project' => $project->name, 'driver' => self::label($credential->detail('driver'))])).'</p>'
            .'</body></html>';

        $email = (new Email)
            ->from(new Address((string) $credential->detail('from_address'), (string) ($credential->detail('from_name') ?: $project->name)))
            ->to($data['to'])
            ->subject(__('Test email from :project', ['project' => $project->name]))
            ->html($html)
            ->text(EmailBuilder::toText($html));

        try {
            if ($credential->detail('driver') === 'direct') {
                $email = (new DkimSigner((string) $credential->secret, (string) $credential->detail('dkim_domain'), (string) $credential->detail('dkim_selector')))->sign($email);
            }
            Transports::make($credential)->send($email);
            $credential->forceFill(['verified_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $e) {
            $credential->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->save();

            return back()->withErrors(['alert' => __('The test email could not be sent: :error', ['error' => mb_substr($e->getMessage(), 0, 200)])]);
        }

        return back()->with('status', __('Test email sent to :address.', ['address' => $data['to']]));
    }

    /** Direct delivery needs outgoing port 25, often blocked by hosting providers. */
    public function checkPort(Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);

        $socket = @fsockopen('gmail-smtp-in.l.google.com', 25, $code, $error, 8);
        if ($socket) {
            $greeting = (string) fgets($socket, 512);
            fclose($socket);
            if (str_starts_with($greeting, '220')) {
                return back()->with('status', __('Port 25 is open: this server can deliver emails directly.'));
            }
        }

        return back()->withErrors(['alert' => __('Port 25 is blocked on this server: ask your hosting provider to open it, or choose an email service.')]);
    }

    public static function label(?string $driver): string
    {
        return ['smtp' => 'SMTP', 'resend' => 'Resend', 'ses' => 'Amazon SES', 'postmark' => 'Postmark', 'mailgun' => 'Mailgun', 'direct' => __('Direct delivery')][$driver] ?? (string) $driver;
    }

    /** The public key for the DNS record of DKIM. */
    public static function dkimRecord(PushCredential $credential): ?string
    {
        $key = openssl_pkey_get_private((string) $credential->secret);
        if (! $key) {
            return null;
        }
        $public = openssl_pkey_get_details($key)['key'] ?? '';

        return 'v=DKIM1; k=rsa; p='.preg_replace('/-----[^-]+-----|\s+/', '', $public);
    }

    protected static function dkimKey(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (! $key || ! openssl_pkey_export($key, $pem)) {
            throw ValidationException::withMessages(['driver' => 'The DKIM key could not be generated.']);
        }

        return $pem;
    }
}
