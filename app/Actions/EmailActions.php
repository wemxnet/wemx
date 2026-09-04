<?php

namespace App\Actions;

use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmailActions extends Action
{
    public function sendUserEmail(array $input)
    {
        $user = isset($input['user_id']) ? User::find($input['user_id']) : null;

        $input = EmailTemplate::compose($input, $user);

        if ($input === null) {
            return null;
        }

        $validated = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'token' => ['nullable', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'mailable_type' => ['nullable', 'string', 'max:255'],
            'mailable_id' => ['nullable'],
            'from' => ['nullable', 'email'],
            'to' => ['nullable', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array'],
            'table' => ['nullable', 'array'],
            'table.columns' => ['required_if:table,true', 'array'],
            'table.rows' => ['required_if:table,true', 'array'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'required_with:button_text'],
            'attachments' => ['nullable', 'array'],
            'theme' => ['nullable', 'string', 'max:255'],
            'display' => ['nullable', 'boolean'],
        ])->validate();

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if (! isset($validated['to'])) {
            $validated['to'] = $user->email;
        }

        if (! isset($validated['theme'])) {
            $validated['theme'] = 'default';
        }

        if (! isset($validated['display'])) {
            $validated['display'] = true;
        }

        return Email::create(self::omitNullValues($validated));
    }

    public function sendEmailToAddress(array $input)
    {
        $user = isset($input['user_id']) ? User::find($input['user_id']) : null;

        $input = EmailTemplate::compose($input, $user);

        if ($input === null) {
            return null;
        }

        $validated = Validator::make($input, [
            'user_id' => ['nullable', 'exists:users,id'],
            'token' => ['nullable', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255', 'required_with:cooldown'],
            'mailable_type' => ['nullable', 'string', 'max:255'],
            'mailable_id' => ['nullable'],
            'from' => ['nullable', 'email'],
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array'],
            'table' => ['nullable', 'array'],
            'table.columns' => ['required_if:table,true', 'array'],
            'table.rows' => ['required_if:table,true', 'array'],
            'button_text' => ['nullable', 'string', 'max:255', 'required_with:button_url'],
            'button_url' => ['nullable', 'url', 'required_with:button_text'],
            'attachments' => ['nullable', 'array'],
            'theme' => ['nullable', 'string', 'max:255'],
            'display' => ['nullable', 'boolean'],
            'cooldown' => ['nullable', 'integer'],
        ])->validate();

        if (isset($validated['cooldown'])) {
            $lastEmail = Email::where('identifier', $validated['identifier'])
                ->where('created_at', '>', now()->subMinutes($validated['cooldown']))
                ->latest()
                ->first();

            if ($lastEmail) {
                return $lastEmail;
            }
        }

        unset($validated['cooldown']);

        return Email::create(self::omitNullValues($validated));
    }
}
