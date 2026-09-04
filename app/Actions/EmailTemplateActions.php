<?php

namespace App\Actions;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmailTemplateActions extends Action
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function updateAsAdmin(array $input): EmailTemplate
    {
        $validated = Validator::make($input, [
            'identifier' => ['required', 'string', Rule::in(array_keys(EmailTemplate::definitions()))],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'enabled' => ['required', 'boolean'],
        ])->validate();

        $buttonText = trim((string) ($validated['button_text'] ?? ''));

        return EmailTemplate::query()->updateOrCreate(
            ['identifier' => $validated['identifier']],
            [
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'button_text' => $buttonText !== '' ? $buttonText : null,
                'enabled' => $validated['enabled'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function resetAsAdmin(array $input): void
    {
        $validated = Validator::make($input, [
            'identifier' => ['required', 'string', Rule::in(array_keys(EmailTemplate::definitions()))],
        ])->validate();

        EmailTemplate::query()->where('identifier', $validated['identifier'])->delete();
    }
}
