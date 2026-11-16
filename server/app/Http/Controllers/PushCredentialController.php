<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\PushCredential;
use App\Sms\Phone;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Minishlink\WebPush\VAPID;

/** APNs, FCM and web push keys of a project. */
class PushCredentialController extends Controller
{
    public function update(Request $request, Project $project, string $provider): RedirectResponse
    {
        $this->authorize('configure', $project);
        abort_unless(in_array($provider, ['apns', 'fcm', 'webpush', 'sms'], true), 404);

        $existing = $project->credential($provider);

        [$details, $secret] = match ($provider) {
            'apns' => $this->apns($request, $existing),
            'fcm' => $this->fcm($request),
            'webpush' => $this->webPush($request, $existing),
            'sms' => $this->sms($request, $existing),
        };

        PushCredential::query()->updateOrCreate(
            ['project_id' => $project->id, 'provider' => $provider],
            ['details' => $details, 'secret' => $secret, 'last_error' => null],
        );
        Audit::log('push.updated', $project, ['provider' => $provider]);

        return back()->with('status', match ($provider) {
            'apns' => __('Apple Push keys saved.'),
            'fcm' => __('Firebase keys saved.'),
            'webpush' => __('Web push keys saved.'),
            'sms' => __('SMS settings saved.'),
        });
    }

    /** New VAPID key pair for web push (existing browser subscriptions stop working). */
    public function generateWebPush(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);

        $keys = VAPID::createVapidKeys();
        $subject = $project->credential('webpush')?->detail('subject') ?: 'mailto:'.$request->user()->email;

        PushCredential::query()->updateOrCreate(
            ['project_id' => $project->id, 'provider' => 'webpush'],
            ['details' => ['public_key' => $keys['publicKey'], 'subject' => $subject], 'secret' => $keys['privateKey'], 'last_error' => null],
        );
        Audit::log('push.updated', $project, ['provider' => 'webpush', 'generated' => true]);

        return back()->with('status', __('New web push keys generated.'));
    }

    public function destroy(Project $project, string $provider): RedirectResponse
    {
        $this->authorize('configure', $project);

        $project->credentials()->where('provider', $provider)->delete();
        Audit::log('push.removed', $project, ['provider' => $provider]);

        return back()->with('status', __('Keys removed. This platform no longer receives notifications.'));
    }

    /** @return array{0: array<string, string>, 1: string} */
    protected function apns(Request $request, ?PushCredential $existing): array
    {
        $data = $request->validate([
            'key_id' => ['required', 'string', 'regex:/^[A-Z0-9]{10}$/'],
            'team_id' => ['required', 'string', 'regex:/^[A-Z0-9]{10}$/'],
            'bundle_id' => ['required', 'string', 'max:155', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'private_key' => [$existing ? 'nullable' : 'required', 'file', 'max:16'],
        ], [
            'key_id.regex' => __('The Key ID has 10 letters and digits, e.g. ABC123DEFG.'),
            'team_id.regex' => __('The Team ID has 10 letters and digits, e.g. 4ZA5MXYWG7.'),
        ]);

        $secret = $existing?->secret;

        if ($request->hasFile('private_key')) {
            $secret = trim((string) file_get_contents($request->file('private_key')->getRealPath()));
            $key = openssl_pkey_get_private($secret);

            if (! $key || (openssl_pkey_get_details($key)['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
                throw ValidationException::withMessages(['private_key' => __('This is not an Apple Push key (.p8 file).')]);
            }
        }

        return [[
            'key_id' => strtoupper($data['key_id']),
            'team_id' => strtoupper($data['team_id']),
            'bundle_id' => $data['bundle_id'],
        ], $secret];
    }

    /** @return array{0: array<string, string>, 1: string} */
    protected function fcm(Request $request): array
    {
        $request->validate(['service_account' => ['required', 'file', 'max:64']]);

        $raw = (string) file_get_contents($request->file('service_account')->getRealPath());
        $account = json_decode($raw, true);

        if (! is_array($account) || ($account['type'] ?? null) !== 'service_account' || ! isset($account['project_id'], $account['client_email'], $account['private_key']) || ! openssl_pkey_get_private($account['private_key'])) {
            throw ValidationException::withMessages(['service_account' => __('This is not a Firebase service account key (JSON file).')]);
        }

        return [[
            'project_id' => $account['project_id'],
            'client_email' => $account['client_email'],
        ], $raw];
    }

    /** @return array{0: array<string, string>, 1: string} */
    /** Twilio: account SID, sender, auth token (kept when left empty). */
    protected function sms(Request $request, ?PushCredential $existing): array
    {
        // "+225 07 00…" is accepted as typed.
        if (($phone = Phone::normalize((string) $request->input('from'))) !== null) {
            $request->merge(['from' => $phone]);
        }
        $data = $request->validate([
            'account_sid' => ['required', 'string', 'regex:/^AC[0-9a-fA-F]{32}$/'],
            'from' => ['required', 'string', 'max:64', 'regex:/^(\+[1-9]\d{6,14}|MG[0-9a-fA-F]{32}|[A-Za-z0-9 ]{1,11})$/'],
            'auth_token' => [$existing ? 'nullable' : 'required', 'string', 'max:128'],
        ], [
            'account_sid.regex' => __('The Account SID starts with AC, followed by 32 characters.'),
            'from.regex' => __('A number in the international format (+2250700000000), a Messaging Service SID (MG…) or a sender name of 11 characters at most.'),
        ]);

        return [
            ['driver' => 'twilio', 'account_sid' => trim($data['account_sid']), 'from' => trim($data['from'])],
            filled($data['auth_token'] ?? null) ? trim($data['auth_token']) : $existing->secret,
        ];
    }

    protected function webPush(Request $request, ?PushCredential $existing): array
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255', 'regex:/^(mailto:.+@.+|https:\/\/.+)$/'],
            'public_key' => ['nullable', 'string', 'max:200'],
            'private_key' => ['nullable', 'string', 'max:200'],
        ], [
            'subject.regex' => __('Use a contact address: mailto:you@example.com or https://example.com.'),
        ]);

        // Keys pasted from another service (e.g. to keep existing browser
        // subscriptions), otherwise the current ones.
        if (filled($data['public_key'] ?? null) && filled($data['private_key'] ?? null)) {
            return [['public_key' => trim($data['public_key']), 'subject' => $data['subject']], trim($data['private_key'])];
        }

        if (! $existing) {
            $keys = VAPID::createVapidKeys();

            return [['public_key' => $keys['publicKey'], 'subject' => $data['subject']], $keys['privateKey']];
        }

        return [array_merge($existing->details ?? [], ['subject' => $data['subject']]), $existing->secret];
    }
}
