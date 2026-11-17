<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Support\Audit;
use App\Support\Sessions;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'locales' => SetLocale::AVAILABLE,
            'twoFactorQr' => $request->user()->two_factor_secret && ! $request->user()->hasTwoFactor()
                ? app(TwoFactor::class)->qrCodeSvg(app(TwoFactor::class)->otpauthUrl($request->user(), $request->user()->two_factor_secret))
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'locale' => ['nullable', Rule::in(array_keys(SetLocale::AVAILABLE))],
        ]);
        // The email receives the password reset links: changing it takes the password.
        if ($data['email'] !== $user->email) {
            $request->validate(['email_password' => ['required', 'current_password']], [
                'email_password.required' => __('Enter your password to change your email address.'),
                'email_password.current_password' => __('The password is incorrect.'),
            ]);
            Audit::log('auth.email_changed', $user, ['from' => $user->email, 'to' => $data['email']], $user);
        }

        $user->update([...$data, 'weekly_report' => $request->boolean('weekly_report')]);

        if ($user->locale && array_key_exists($user->locale, SetLocale::AVAILABLE)) {
            app()->setLocale($user->locale);
        }

        return back()->with('status', __('Profile updated.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        Sessions::endOthers($request->user(), $request->session()->getId());

        return back()->with('status', __('Password updated.'));
    }
}
