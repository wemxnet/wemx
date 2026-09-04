<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login()
    {
        return view('theme::auth.login');
    }

    public function forgotPassword()
    {
        return view('theme::auth.forgot-password');
    }

    public function forgotPasswordEmailSent()
    {
        return view('theme::auth.forgot-password-sent');
    }

    public function resetPassword($token)
    {
        $token = PasswordResetToken::where('token', $token)->firstOrFail();

        // if token is older than 15 minutes, redirect to forgot password page
        if ($token->created_at->diffInMinutes(now()) > 15) {
            return redirect()->route('forgot-password');
        }

        return view('theme::auth.reset-password', ['token' => $token]);
    }

    public function register()
    {
        if (! settings('enable_registrations', true)) {
            return redirect()->route('login');
        }

        return view('theme::auth.register');
    }

    public function verifyEmail()
    {
        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('verify-email.verified');
        }

        return view('theme::auth.verify-email');
    }

    public function resendVerificationEmail()
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('verify-email.verified');
        }

        $verificationEmailsSent = $user->emails()->where('identifier', 'email_verification')->latest();
        $lastVerificationEmail = $verificationEmailsSent->first();

        // check if it was sent at least 2 minutes ago
        if ($lastVerificationEmail && $lastVerificationEmail->created_at->diffInMinutes(now()) < 2) {
            return redirect()->route('verify-email')->with('status', 'Please wait before resending the verification email.');
        }

        // if more than 6 emails have been sent, do not send again
        if ($verificationEmailsSent->count() >= 6) {
            return redirect()->route('verify-email')->with('status', 'You have reached the limit of verification emails sent. Please contact support.');
        }

        auth()->user()->emailVerificationToken();

        return redirect()->route('verify-email')->with('status', 'Verification email sent!');
    }

    public function verifiedEmail()
    {
        return view('theme::auth.verified-email');
    }

    public function verifyEmailWithToken($token)
    {
        $user = User::where('verification_token', $token)->firstOrFail();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('verify-email.verified');
        }

        $user->markEmailAsVerified();

        return redirect()->route('verify-email.verified');
    }

    public function logout(Request $request)
    {
        auth()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function enableTwoFactor()
    {
        if (auth()->user()->tfa_enabled) {
            return redirect()->route('dashboard');
        }

        return view('theme::auth.enable-2fa');
    }

    public function disableTwoFactor()
    {
        if (! auth()->user()->tfa_enabled) {
            return redirect()->route('dashboard');
        }

        return view('theme::auth.disable-2fa');
    }

    public function requireTwoFactor()
    {
        if (! auth()->user()->tfa_enabled) {
            return redirect()->route('dashboard');
        }

        return view('theme::auth.require-2fa');
    }

    public function lostAccessTwoFactor($emailToken)
    {
        $email = Email::where('token', $emailToken)->where('identifier', 'account.2fa.disable.request')->firstOrFail();

        // if email is older than 24 hours, redirect to login
        if ($email->created_at->diffInHours(now()) > 24) {
            return redirect()->route('login');
        }

        $email->user->update([
            'tfa_enabled' => false,
            'tfa_secret' => null,
        ]);

        $email->user->email([
            'identifier' => 'account.2fa.disable.confirmed',
        ]);

        return redirect()->route('account.settings');
    }

    public function updateAddress()
    {
        return view('theme::auth.update-address');
    }

    public function confirmEmailAddress(Email $email)
    {
        // check if email is not older than 24 hours
        if ($email->created_at->diffInHours(now()) > 24) {
            return redirect()->route('dashboard');
        }

        $user = $email->user;

        // check if the email is not already used by another user
        if (User::where('email', $email->to)->exists()) {
            return redirect()->route('dashboard');
        }

        // notify user on their previous email address
        $user->email([
            'identifier' => 'account.email.change.confirmed',
            'variables' => [
                'new_email' => $email->to,
            ],
        ]);

        $user->update([
            'email' => $email->to,
        ]);

        return redirect()->route('dashboard');
    }
}
