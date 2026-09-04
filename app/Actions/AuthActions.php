<?php

namespace App\Actions;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Rules\NotReservedUsername;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthActions extends Action
{
    public function loginAsClient(array $input): void
    {
        $validatedData = Validator::make($input, [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'min:5', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ])->validate();

        $authField = filter_var($validatedData['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$authField => $validatedData['username'], 'password' => $validatedData['password']], $validatedData['remember'] ?? false)) {
            // notify user on their previous email address
            auth()->user()->email([
                'identifier' => 'account.new-login',
            ]);

            return;
        } else {
            throw ValidationException::withMessages([
                'username' => 'Email, username or password is incorrect.',
            ]);
        }
    }

    public function registerAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'first_name' => ['required', 'alpha', 'min:3', 'max:255'],
            'last_name' => ['required', 'alpha', 'min:3', 'max:255'],
            'username' => ['required', 'alpha_num', 'min:5', 'max:255', 'unique:users,username', new NotReservedUsername],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'log_user_in' => ['nullable', 'boolean'],
        ])->validate();

        // Hash the password before creating the user
        $validatedData['password'] = Hash::make($validatedData['password']);

        try {
            $user = User::create($validatedData);
            $user->emailVerificationToken();
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'email' => 'Something went wrong. Please try again.',
            ]);
        }

        // if this is the first user created, automatically verify their email
        if (User::count() === 1) {
            $user->markEmailAsVerified();
        }

        if (isset($validatedData['log_user_in']) && $validatedData['log_user_in']) {
            // Log the user in if requested
            auth()->login($user);
        }

        return $user;
    }

    public function requestPasswordAsClient(array $input): void
    {
        $validatedData = Validator::make($input, [
            'email' => ['required', 'email'],
        ])->validate();

        // check if user exists
        $user = User::where('email', $validatedData['email'])->first();

        if (! $user) {
            return;
        }

        $user->emailPasswordResetLink();
    }

    public function resetPasswordAsClient(array $input): void
    {
        $validatedData = Validator::make($input, [
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ])->validate();

        // locate the token
        $token = PasswordResetToken::where('token', $validatedData['token'])->first();

        if (! $token) {
            throw ValidationException::withMessages([
                'password' => 'The provided password reset token is invalid.',
            ]);
        }

        // check if token is older than 15 minutes
        if ($token->created_at->diffInMinutes(now()) > 15) {
            throw ValidationException::withMessages([
                'password' => 'The provided password reset token has expired.',
            ]);
        }

        if (! $token->user) {
            throw ValidationException::withMessages([
                'password' => 'The user associated with this token does not exist.',
            ]);
        }

        // update the user's password
        $token->user->update([
            'password' => Hash::make($validatedData['password']),
        ]);

        // delete the token
        $token->delete();

        // notify the user
        $token->user->email([
            'identifier' => 'account.password.reset',
        ]);
    }

    public static function enableTwoFactorAuthAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'tfa_code' => ['required', 'digits:6'],
        ])->validate();

        $user = User::find($input['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if ($user->tfa_enabled) {
            throw ValidationException::withMessages([
                'tfa_code' => 'Two-factor authentication is already enabled.',
            ]);
        }

        $tfa_secret = $user->generateTwoFactorSecret();

        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey($tfa_secret, $validatedData['tfa_code']);

        if (! $valid) {
            throw ValidationException::withMessages([
                'tfa_code' => 'The provided two-factor authentication code is invalid.',
            ]);
        }

        $user->email([
            'identifier' => 'account.2fa.enabled',
        ]);

        return $user->update([
            'tfa_enabled' => true,
            'tfa_secret' => $tfa_secret,
        ]);
    }

    public static function disableTwoFactorAuthAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'tfa_code' => ['required', 'digits:6'],
        ])->validate();

        $user = User::find($input['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if (! $user->tfa_enabled) {
            throw ValidationException::withMessages([
                'tfa_code' => 'Two-factor authentication is not enabled.',
            ]);
        }

        // if tfa_secret is null for whatever reason, disable tfa
        if (! $user->tfa_secret) {
            return $user->update([
                'tfa_enabled' => false,
                'tfa_secret' => null,
            ]);
        }

        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey($user->tfa_secret, $validatedData['tfa_code']);

        if (! $valid) {
            throw ValidationException::withMessages([
                'tfa_code' => 'The provided two-factor authentication code is invalid.',
            ]);
        }

        $user->email([
            'identifier' => 'account.2fa.disabled',
        ]);

        return $user->update([
            'tfa_enabled' => false,
            'tfa_secret' => null,
        ]);
    }

    public static function checkTwoFactorAuthAsClient(array $input): bool
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'tfa_code' => ['required', 'digits:6'],
        ])->validate();

        $user = User::find($input['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if (! $user->tfa_enabled || ! $user->tfa_secret) {
            return true;
        }

        $google2fa = new Google2FA;
        $validCode = $google2fa->verifyKey($user->tfa_secret, $validatedData['tfa_code']);

        if (! $validCode) {
            throw ValidationException::withMessages([
                'tfa_code' => 'The provided two-factor authentication code is invalid.',
            ]);
        }

        return true;
    }

    public static function requestDisabelmentTfaAsClient(array $input): void
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'current_password' => ['required', 'string'],
        ])->validate();

        $user = User::find($input['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if (! $user->tfa_enabled) {
            throw ValidationException::withMessages([
                'tfa_code' => 'Two-factor authentication is not enabled.',
            ]);
        }

        if (! Hash::check($validatedData['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided password does not match your current password.',
            ]);
        }

        $token = Str::random(64);
        $user->email([
            'display' => false,
            'token' => $token,
            'identifier' => 'account.2fa.disable.request',
            'button' => [
                'url' => route('lost-access-2fa', ['email_token' => $token]),
            ],
        ]);
    }

    public static function reauthenticateAdmin(array $input): bool
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'password' => ['required', 'string'],
            'tfa_code' => ['nullable', 'digits:6'],
        ])->validate();

        $user = User::find($validatedData['user_id']);

        if (! $user || ! $user->isStaff()) {
            throw ValidationException::withMessages([
                'password' => 'Unauthorized.',
            ]);
        }

        if (! Hash::check($validatedData['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The provided password is incorrect.',
            ]);
        }

        if ($user->tfa_enabled) {
            if (! isset($validatedData['tfa_code'])) {
                throw ValidationException::withMessages([
                    'tfa_code' => 'Two-factor authentication code is required.',
                ]);
            }

            self::checkTwoFactorAuthAsClient([
                'user_id' => $user->id,
                'tfa_code' => $validatedData['tfa_code'],
            ]);
        }

        return true;
    }
}
