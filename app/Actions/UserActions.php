<?php

namespace App\Actions;

use App\Facades\World;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Session;
use App\Models\User;
use App\Models\UserBan;
use App\Rules\ValidVatNumber;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserActions extends Action
{
    /**
     * This function creates a new user as an admin.
     * It validates the input data, hashes the password, and creates a new user.
     * It also updates the user's address information if provided.
     *
     *
     * @return User
     *
     * @throws ValidationException
     */
    public static function createUserAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'first_name' => ['required', 'min:2', 'max:255'],
            'last_name' => ['nullable', 'min:2', 'max:255'],
            'username' => ['required', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['nullable', 'min:8', 'max:50'],
            'lang' => ['nullable', 'size:2'],
            'verify_email' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'string', 'max:255'],

            // address fields
            'company_name' => ['nullable', 'min:3', 'max:255', 'required_with:tax_id'],
            'tax_id' => ['nullable', 'min:3', 'max:255', new ValidVatNumber($input['country'] ?? null)],
            'address' => ['nullable', 'min:3', 'max:255'],
            'address2' => ['nullable', 'min:3', 'max:255'],
            'country' => ['nullable', Rule::in(array_keys(World::countries())), 'required_with:tax_id'],
            'region' => ['nullable', 'min:2', 'max:255'],
            'city' => ['nullable', 'min:2', 'max:255'],
            'zip_code' => ['nullable', 'min:2', 'max:255'],
        ])->validate();

        // if password is not set, generate a random one
        if (! isset($validatedData['password'])) {
            $randomPassword = Str::random(12);
            $validatedData['password'] = $randomPassword;
        }

        // hash the password using Hash
        $validatedData['password'] = Hash::make($validatedData['password']);

        $user = User::create(self::omitNullValues($validatedData));

        $user->address()->update(self::omitNullValues([
            'company_name' => $validatedData['company_name'] ?? null,
            'tax_id' => $validatedData['tax_id'] ?? null,
            'address' => $validatedData['address'] ?? null,
            'address2' => $validatedData['address2'] ?? null,
            'country' => $validatedData['country'] ?? null,
            'region' => $validatedData['region'] ?? null,
            'city' => $validatedData['city'] ?? null,
            'zip_code' => $validatedData['zip_code'] ?? null,
        ]));

        // if a random password was generated, send it to the user
        if (isset($randomPassword)) {
            $user->email([
                'identifier' => 'account.created',
                'table' => [
                    'columns' => [
                        'Username',
                        'Email',
                        'Password',
                    ],
                    'rows' => [
                        [
                            $user->username,
                            $user->email,
                            $randomPassword,
                        ],
                    ],
                ],
                'button' => [
                    'url' => route('login'),
                ],
            ]);
        }

        // if the user should be verified, send a verification email
        if (isset($validatedData['verify_email']) && $validatedData['verify_email']) {
            $user->markEmailAsVerified();
        } else {
            $user->emailVerificationToken();
        }

        $user->logActivity([
            'user_id' => auth()->check() ? auth()->id() : null,
            'event' => 'user.created',
            'description' => 'User created manually by '.(auth()->check() ? auth()->user()->username : 'system'),
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        return $user;
    }

    /**
     * Send a manual email to a user from the admin area.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function sendEmailAsAdmin(array $input): void
    {
        $input['button_text'] = filled($input['button_text'] ?? null) ? trim((string) $input['button_text']) : null;
        $input['button_url'] = filled($input['button_url'] ?? null) ? trim((string) $input['button_url']) : null;

        $validated = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'button_text' => ['nullable', 'string', 'max:255', 'required_with:button_url'],
            'button_url' => ['nullable', 'url', 'max:2048', 'required_with:button_text'],
        ])->validate();

        $user = User::query()->find($validated['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        $lines = EmailTemplate::bodyToLines($validated['body']);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'body' => __('validation.required', ['attribute' => 'body']),
            ]);
        }

        $payload = [
            'identifier' => 'admin.manual-email',
            'subject' => $validated['subject'],
            'lines' => $lines,
        ];

        if ($validated['button_text'] && $validated['button_url']) {
            $payload['button'] = [
                'text' => $validated['button_text'],
                'url' => $validated['button_url'],
            ];
        }

        $user->email($payload);
    }

    /**
     * This function updates an existing user as an admin.
     * It validates the input data, hashes the password if provided,
     * and updates the user information.
     *
     *
     * @return bool
     *
     * @throws ValidationException
     */
    public static function updateUserAsAdmin(array $input)
    {
        $user = User::find($input['user_id'] ?? 'null');

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        $validatedData = Validator::make($input, [
            'user_id' => 'required|exists:users,id',
            'first_name' => 'sometimes|required|min:3|max:50',
            'last_name' => 'nullable|min:3|max:50',
            'username' => 'sometimes|required|min:3|max:50|unique:users,username,'.$user->id.',id',
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id.',id',
            'status' => 'sometimes|required|in:active,pending,suspended',
            'lang' => 'nullable|min:2|max:2',
            'password' => 'nullable|min:8|max:50',
        ])->validate();

        // if status is changed to suspended, or pending and the user is a staff member, prevent it
        if (isset($validatedData['status']) && in_array($validatedData['status'], ['suspended', 'pending']) && $user->isStaff()) {
            throw ValidationException::withMessages([
                'status' => 'Cannot change status of a staff member to suspended or pending',
            ]);
        }

        // if the status is changed to active, email the user
        if (isset($validatedData['status']) && $user->status !== $validatedData['status'] && $validatedData['status'] === 'active') {
            $user->email([
                'identifier' => 'account.activated',
                'button' => [
                    'url' => route('login'),
                ],
            ]);
        }

        // if password is null, remove it from the array
        if (! isset($validatedData['password'])) {
            unset($validatedData['password']);
        }

        if (isset($validatedData['password'])) {
            $validatedData['password'] = bcrypt($validatedData['password']);
        }

        unset($validatedData['user_id']);

        return $user->update(self::omitNullValues($validatedData));
    }

    /**
     * Update user address as admin
     *
     *
     * @return bool
     *
     * @throws ValidationException
     */
    public static function updateUserAddressAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'company_name' => ['nullable', 'min:3', 'max:255', 'required_with:tax_id'],
            'tax_id' => ['nullable', 'min:3', 'max:255', new ValidVatNumber($input['country'] ?? null)],
            'address' => ['nullable', 'min:3', 'max:255'],
            'address2' => ['nullable', 'min:3', 'max:255'],
            'country' => ['nullable', Rule::in(array_keys(World::countries())), 'required_with:tax_id'],
            'region' => ['nullable', 'min:2', 'max:255'],
            'city' => ['nullable', 'min:2', 'max:255'],
            'zip_code' => ['nullable', 'min:2', 'max:255'],
        ])->validate();

        $user = User::find($validatedData['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        $address = $user->address;

        unset($validatedData['user_id']);

        return $address->update(self::omitNullValues([
            'company_name' => $validatedData['company_name'] ?? null,
            'tax_id' => $validatedData['tax_id'] ?? null,
            'address' => $validatedData['address'] ?? null,
            'address2' => $validatedData['address2'] ?? null,
            'country' => $validatedData['country'] ?? null,
            'region' => $validatedData['region'] ?? null,
            'city' => $validatedData['city'] ?? null,
            'zip_code' => $validatedData['zip_code'] ?? null,
        ]));
    }

    /**
     * Delete a user as an admin.
     *
     *
     *
     * @throws Exception
     */
    public static function deleteUserAsAdmin(User $user): ?bool
    {
        if ($user->isStaff()) {
            throw new Exception('Cannot delete an staff user');
        }

        return $user->delete();
    }

    public static function updateUserBalanceAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', Rule::in(['+', '-', '='])],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $user = User::find($validatedData['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        // when + or -, amount must be greater than 0
        if (in_array($validatedData['type'], ['+', '-']) && $validatedData['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be at least 0.01 for this operation',
            ]);
        }

        try {
            $user->updateBalance(
                $validatedData['type'],
                $validatedData['amount'],
                $validatedData['description'] ?? null
            );
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }

        return true;
    }

    public static function disableTwoFactorAuthAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $user = User::find($validatedData['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if (! $user->tfa_enabled) {
            throw ValidationException::withMessages([
                'user_id' => 'Two-factor authentication is not enabled for this user',
            ]);
        }

        $user->update([
            'tfa_enabled' => false,
            'tfa_secret' => null,
        ]);

        // Notify user of 2FA disable
        $user->email([
            'identifier' => 'account.2fa.disabled_by_admin',
        ]);

        return true;
    }

    /**
     * This function updates a user account as a client.
     * It validates the input data, checks if the user exists,
     * and updates the user information.
     *
     *
     * @return bool
     *
     * @throws ValidationException
     */
    public static function updateAccountAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'first_name' => ['sometimes', 'required', 'alpha', 'min:3', 'max:255'],
            'last_name' => ['sometimes', 'required', 'alpha', 'min:3', 'max:255'],
            'username' => ['sometimes', 'required', 'alpha_num', 'min:5', 'max:50', 'unique:users,username,'.$input['user_id']],
            'phone' => ['nullable', 'regex:/^\+?[0-9]{7,15}$/'],
            'is_subscribed' => ['sometimes', 'boolean'],
        ])->validate();

        $user = User::find($input['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        unset($validatedData['user_id']);

        return $user->update(self::omitNullValues($validatedData));
    }

    public static function updateAddressAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'company_name' => ['nullable', 'min:3', 'max:255'],
            'tax_id' => ['nullable', 'required_with:company_name', 'min:3', 'max:255', new ValidVatNumber($input['country'] ?? 'US')],
            'address' => ['sometimes', 'required', 'min:3', 'max:255'],
            'address2' => ['nullable', 'min:3', 'max:255'],
            'country' => ['sometimes', 'required', Rule::in(array_keys(World::countries())), 'required_with:tax_id'],
            'region' => ['sometimes', 'required', 'min:2', 'max:255'],
            'city' => ['sometimes', 'required', 'min:2', 'max:255'],
            'zip_code' => ['sometimes', 'required', 'min:3', 'max:255'],
        ])->validate();

        $user = User::find($validatedData['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        $address = $user->address;

        unset($validatedData['user_id']);

        return $address->update(self::omitNullValues([
            'company_name' => $validatedData['company_name'] ?? null,
            'tax_id' => $validatedData['tax_id'] ?? null,
            'address' => $validatedData['address'] ?? null,
            'address2' => $validatedData['address2'] ?? null,
            'country' => $validatedData['country'] ?? null,
            'region' => $validatedData['region'] ?? null,
            'city' => $validatedData['city'] ?? null,
            'zip_code' => $validatedData['zip_code'] ?? null,
        ]));
    }

    /**
     * This function updates the email address of a user as a client.
     *
     *
     * @return bool
     *
     * @throws ValidationException
     */
    public static function updateEmailAddressAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'current_password' => ['required'],
            'new_email' => ['required', 'email', 'unique:users,email'],
            'tfa_code' => ['nullable', 'digits:6'],
        ])->validate();

        $user = User::find($input['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if (! Hash::check($input['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect',
            ]);
        }

        if ($user->tfa_enabled) {
            if (! isset($validatedData['tfa_code'])) {
                throw ValidationException::withMessages([
                    'tfa_code' => 'Two-factor authentication code is required',
                ]);
            }

            if (! $user->verifyTfaCode($validatedData['tfa_code'])) {
                throw ValidationException::withMessages([
                    'tfa_code' => 'The provided two-factor authentication code is invalid',
                ]);
            }
        }

        $token = Str::random(48);
        Email::actions()->sendEmailToAddress([
            'user_id' => $user->id,
            'token' => $token,
            'identifier' => 'account.email.change.requested',
            'to' => $validatedData['new_email'],
            'button_url' => route('confirm-email-address', $token),
            'display' => false,
        ]);
    }

    /**
     * This function updates the password of a user as a client.
     * It validates the input data, checks if the user exists,
     * and updates the password if the current password is correct.
     *
     *
     * @return bool
     *
     * @throws ValidationException
     */
    public static function updatePasswordAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'current_password' => ['required'],
            'new_password' => ['required', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required'],
            'tfa_code' => ['nullable', 'digits:6'],
        ])->validate();

        $user = User::find($input['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if (! Hash::check($input['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect',
            ]);
        }

        if ($user->tfa_enabled) {
            if (! isset($validatedData['tfa_code'])) {
                throw ValidationException::withMessages([
                    'tfa_code' => 'Two-factor authentication code is required',
                ]);
            }

            if (! $user->verifyTfaCode($validatedData['tfa_code'])) {
                throw ValidationException::withMessages([
                    'tfa_code' => 'The provided two-factor authentication code is invalid',
                ]);
            }
        }

        // Notify user of password change
        $user->email([
            'identifier' => 'account.password.change.confirmed',
        ]);

        return $user->update([
            'password' => Hash::make($validatedData['new_password']),
        ]);
    }

    public static function logoutSessionAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'session_id' => ['required', 'exists:sessions,id'],
        ])->validate();

        $user = User::find($validatedData['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        $session = Session::where('user_id', $user->id)->where('id', $validatedData['session_id'])->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session_id' => 'Session not found',
            ]);
        }

        return $session->delete();
    }

    public static function banUserAsAdmin(array $input): UserBan
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'admin_id' => ['required', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'ip_ban' => ['nullable', 'boolean'],
        ])->validate();

        $user = User::find($validatedData['user_id']);
        $admin = User::find($validatedData['admin_id']);

        if (! $user || ! $admin) {
            throw ValidationException::withMessages([
                'user_id' => 'User or admin not found.',
            ]);
        }

        if ($user->isStaff()) {
            throw ValidationException::withMessages([
                'user_id' => 'You cannot ban a staff member.',
            ]);
        }

        if ($user->hasActiveBan()) {
            throw ValidationException::withMessages([
                'reason' => 'This user already has an active ban. Lift it before creating a new one.',
            ]);
        }

        $ipBan = (bool) ($validatedData['ip_ban'] ?? false);
        $ipAddress = null;

        if ($ipBan) {
            $ipAddress = Session::query()
                ->where('user_id', $user->id)
                ->whereNotNull('ip_address')
                ->latest('last_activity')
                ->value('ip_address');

            if (! $ipAddress) {
                throw ValidationException::withMessages([
                    'ip_ban' => 'No session IP address found for this user.',
                ]);
            }

            $isLocalhost = in_array($ipAddress, ['127.0.0.1', '::1'], true);
            $isPrivateOrReserved = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

            if ($isLocalhost || $isPrivateOrReserved) {
                throw ValidationException::withMessages([
                    'ip_ban' => 'You cannot IP ban local/private/reserved addresses.',
                ]);
            }
        }

        return UserBan::create(self::omitNullValues([
            'user_id' => $user->id,
            'banned_by_id' => $admin->id,
            'reason' => $validatedData['reason'] ?? null,
            'expires_at' => $validatedData['expires_at'] ?? null,
            'is_ip_ban' => $ipBan,
            'ip_address' => $ipAddress,
        ]));
    }

    public static function liftBanAsAdmin(array $input): bool
    {
        $validatedData = Validator::make($input, [
            'ban_id' => ['required', 'exists:user_bans,id'],
            'admin_id' => ['required', 'exists:users,id'],
        ])->validate();

        $ban = UserBan::find($validatedData['ban_id']);
        $admin = User::find($validatedData['admin_id']);

        if (! $ban || ! $admin) {
            throw ValidationException::withMessages([
                'ban_id' => 'Ban or admin not found.',
            ]);
        }

        if ($ban->lifted_at) {
            return true;
        }

        $ban->update([
            'lifted_at' => now(),
            'lifted_by_id' => $admin->id,
        ]);

        return true;
    }
}
