<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\SystemHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Instance settings: push keys are configured per project instead. */
class SettingsController extends Controller
{
    public const SECTIONS = ['general', 'security', 'mail', 'system'];

    public function edit(Settings $settings, string $section = 'general'): View
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);

        return view('admin.settings.'.$section, [
            'settings' => $settings,
            'section' => $section,
            'locales' => SetLocale::AVAILABLE,
            'health' => $section === 'system' ? SystemHealth::check() : null,
        ]);
    }

    public function updateGeneral(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'app_url' => ['required', 'url:http,https', 'max:255'],
            'app_locale' => ['required', Rule::in(array_keys(SetLocale::AVAILABLE))],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $settings->set([
            'app.name' => $data['app_name'],
            'app.url' => rtrim($data['app_url'], '/'),
            'app.locale' => $data['app_locale'],
            'projects.users_can_create' => $request->boolean('users_can_create_projects'),
            'messages.retention_days' => (int) $data['retention_days'],
        ]);

        Audit::log('settings.updated', null, ['section' => 'general']);

        return back()->with('status', __('Settings saved.'));
    }

    public function updateMail(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'mailer' => ['required', Rule::in(['log', 'smtp', 'resend'])],
            'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:255'],
            'port' => ['nullable', 'required_if:mailer,smtp', 'integer', 'between:1,65535'],
            'scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'resend_key' => [
                'nullable', 'string', 'max:255', 'starts_with:re_',
                Rule::requiredIf(fn () => $request->input('mailer') === 'resend' && blank($settings->get('mail.resend_key'))),
            ],
            'from_address' => ['nullable', 'required_if:mailer,smtp,resend', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $values = [
            'mail.mailer' => $data['mailer'],
            'mail.host' => $data['host'] ?? null,
            'mail.port' => isset($data['port']) ? (int) $data['port'] : null,
            'mail.scheme' => $data['scheme'] ?? null,
            'mail.username' => $data['username'] ?? null,
            'mail.from_address' => $data['from_address'] ?? null,
            'mail.from_name' => $data['from_name'] ?? null,
        ];

        // An empty password field keeps the stored password.
        if (filled($data['password'] ?? null)) {
            $values['mail.password'] = $data['password'];
        } elseif ($request->boolean('clear_password')) {
            $values['mail.password'] = null;
        }

        // Same for the Resend API key.
        if (filled($data['resend_key'] ?? null)) {
            $values['mail.resend_key'] = $data['resend_key'];
        }

        $settings->set($values);

        Audit::log('settings.updated', null, ['section' => 'mail']);

        return back()->with('status', __('Mail settings saved.'));
    }

    public function updateSecurity(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'sso_label' => ['nullable', 'string', 'max:60'],
            'sso_issuer' => ['nullable', 'required_if:sso_enabled,1', 'url:https,http', 'max:255'],
            'sso_client_id' => ['nullable', 'required_if:sso_enabled,1', 'string', 'max:255'],
            'sso_client_secret' => ['nullable', 'string', 'max:500'],
            'sso_allowed_domains' => ['nullable', 'string', 'max:500'],
        ]);

        $values = [
            'security.require_2fa_admins' => $request->boolean('require_2fa_admins'),
            'security.private_network' => $request->boolean('private_network'),
            'sso.enabled' => $request->boolean('sso_enabled'),
            'sso.label' => $data['sso_label'] ?? null,
            'sso.issuer' => isset($data['sso_issuer']) ? rtrim($data['sso_issuer'], '/') : null,
            'sso.client_id' => $data['sso_client_id'] ?? null,
            'sso.auto_create' => $request->boolean('sso_auto_create'),
            'sso.allowed_domains' => $data['sso_allowed_domains'] ?? null,
        ];

        if (filled($data['sso_client_secret'] ?? null)) {
            $values['sso.client_secret'] = $data['sso_client_secret'];
        }

        if ($values['security.require_2fa_admins'] && ! $request->user()->hasTwoFactor()) {
            return back()->withInput()->withErrors(['require_2fa_admins' => __('Enable two-factor authentication on your own account first.')]);
        }

        $settings->set($values);
        Audit::log('settings.updated', null, ['section' => 'security', 'sso' => $values['sso.enabled'], 'require_2fa_admins' => $values['security.require_2fa_admins'], 'private_network' => $values['security.private_network']]);

        return back()->with('status', __('Security settings saved.'));
    }

    public function testMail(Request $request): RedirectResponse
    {
        try {
            Mail::raw(__('This is a test email sent by :app. Your mail settings work!', ['app' => config('app.name')]), function ($message) use ($request) {
                $message->to($request->user()->email)->subject(__('Test email from :app', ['app' => config('app.name')]));
            });
        } catch (Throwable $e) {
            return back()->withErrors(['alert' => __('The test email could not be sent: :error', ['error' => $e->getMessage()])]);
        }

        return back()->with('status', __('A test email has been sent to :email.', ['email' => $request->user()->email]));
    }
}
