<?php

namespace App\Actions;

use App\Models\EmailTemplate;
use App\Models\MassMail;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MassMailActions extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function queueAsAdmin(array $input): MassMail
    {
        $input['button_text'] = filled($input['button_text'] ?? null) ? trim((string) $input['button_text']) : null;
        $input['button_url'] = filled($input['button_url'] ?? null) ? trim((string) $input['button_url']) : null;
        $input['package_id'] = filled($input['package_id'] ?? null) ? $input['package_id'] : null;
        $input['order_status'] = filled($input['order_status'] ?? null) ? $input['order_status'] : null;
        $input['user_status'] = filled($input['user_status'] ?? null) ? $input['user_status'] : null;
        $input['country'] = filled($input['country'] ?? null) ? $input['country'] : null;
        $input['scheduled_at'] = filled($input['scheduled_at'] ?? null) ? $input['scheduled_at'] : null;

        $validated = Validator::make($input, [
            'created_by' => ['required', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'button_text' => ['nullable', 'string', 'max:255', 'required_with:button_url'],
            'button_url' => ['nullable', 'url', 'max:2048', 'required_with:button_text'],
            'audience_type' => ['required', Rule::in(MassMail::AUDIENCE_TYPES)],
            'package_id' => [
                Rule::requiredIf(fn () => ($input['audience_type'] ?? null) === MassMail::AUDIENCE_WITH_PACKAGE),
                'nullable',
                'integer',
                'exists:packages,id',
            ],
            'order_status' => [
                Rule::requiredIf(fn () => ($input['audience_type'] ?? null) === MassMail::AUDIENCE_WITH_ORDER_STATUS),
                'nullable',
                Rule::in(MassMail::ORDER_STATUSES),
            ],
            'user_status' => [
                Rule::requiredIf(fn () => ($input['audience_type'] ?? null) === MassMail::AUDIENCE_USER_STATUS),
                'nullable',
                Rule::in(MassMail::USER_STATUSES),
            ],
            'country' => [
                Rule::requiredIf(fn () => ($input['audience_type'] ?? null) === MassMail::AUDIENCE_BY_COUNTRY),
                'nullable',
                'string',
                'size:2',
            ],
            'subscribed_only' => ['nullable', 'boolean'],
            'verified_only' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ])->validate();

        $lines = EmailTemplate::bodyToLines($validated['body']);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'body' => __('validation.required', ['attribute' => 'body']),
            ]);
        }

        if (($validated['audience_type'] === MassMail::AUDIENCE_WITH_PACKAGE)
            && ! Package::query()->whereKey($validated['package_id'])->exists()) {
            throw ValidationException::withMessages([
                'package_id' => __('validation.exists', ['attribute' => 'package']),
            ]);
        }

        $filters = self::omitNullValues([
            'package_id' => $validated['package_id'] ?? null,
            'order_status' => $validated['order_status'] ?? null,
            'user_status' => $validated['user_status'] ?? null,
            'country' => $validated['country'] ?? null,
            'subscribed_only' => (bool) ($validated['subscribed_only'] ?? false) ?: null,
            'verified_only' => (bool) ($validated['verified_only'] ?? false) ?: null,
        ]);

        $recipientCount = MassMail::customersQuery($validated['audience_type'], $filters)->count();

        if ($recipientCount === 0) {
            throw ValidationException::withMessages([
                'audience_type' => __('messages.mass_mail_no_recipients'),
            ]);
        }

        return MassMail::query()->create([
            'created_by' => $validated['created_by'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'button_text' => $validated['button_text'] ?? null,
            'button_url' => $validated['button_url'] ?? null,
            'audience_type' => $validated['audience_type'],
            'filters' => $filters === [] ? null : $filters,
            'status' => MassMail::STATUS_QUEUED,
            'recipient_count' => $recipientCount,
            'scheduled_at' => $validated['scheduled_at'] ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function cancelAsAdmin(array $input): MassMail
    {
        $validated = Validator::make($input, [
            'mass_mail_id' => ['required', 'exists:mass_mails,id'],
        ])->validate();

        $massMail = MassMail::query()->findOrFail($validated['mass_mail_id']);

        if (! $massMail->isCancellable()) {
            throw ValidationException::withMessages([
                'mass_mail_id' => __('messages.mass_mail_cannot_cancel'),
            ]);
        }

        $massMail->update([
            'status' => MassMail::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $massMail->fresh();
    }

    public function processDue(int $chunkSize = 100): int
    {
        $processed = 0;

        $campaigns = MassMail::query()
            ->whereIn('status', [MassMail::STATUS_QUEUED, MassMail::STATUS_SENDING])
            ->where(function ($query): void {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('id')
            ->get();

        foreach ($campaigns as $campaign) {
            $processed += $this->process($campaign, $chunkSize);
        }

        return $processed;
    }

    public function process(MassMail $massMail, int $chunkSize = 100): int
    {
        if (! $massMail->isDue()) {
            return 0;
        }

        if ($massMail->status === MassMail::STATUS_QUEUED) {
            $massMail->update([
                'status' => MassMail::STATUS_SENDING,
                'started_at' => $massMail->started_at ?? now(),
            ]);
        }

        $recipients = $massMail->nextRecipients($chunkSize);

        if ($recipients->isEmpty()) {
            $this->markCompleted($massMail);

            return 0;
        }

        $sent = 0;

        foreach ($recipients as $user) {
            try {
                $this->deliverToUser($massMail, $user);
                $massMail->increment('sent_count');
                $sent++;
            } catch (Throwable $exception) {
                $massMail->increment('failed_count');
                $massMail->update(['last_error' => $exception->getMessage()]);
            }

            $massMail->update(['last_user_id' => $user->id]);
        }

        $massMail->refresh();

        if ($massMail->nextRecipients(1)->isEmpty()) {
            $this->markCompleted($massMail);
        }

        return $sent;
    }

    public function deliverToUser(MassMail $massMail, User $user): void
    {
        $variables = MassMail::placeholderVariables($user);
        $subject = EmailTemplate::replacePlaceholders($massMail->subject, $variables);
        $lines = EmailTemplate::bodyToLines(
            EmailTemplate::replacePlaceholders($massMail->body, $variables)
        );

        $payload = [
            'identifier' => 'admin.mass-email',
            'mailable_type' => MassMail::class,
            'mailable_id' => $massMail->id,
            'subject' => $subject,
            'lines' => $lines,
        ];

        if ($massMail->button_text && $massMail->button_url) {
            $payload['button'] = [
                'text' => EmailTemplate::replacePlaceholders($massMail->button_text, $variables),
                'url' => EmailTemplate::replacePlaceholders($massMail->button_url, $variables),
            ];
        }

        $user->email($payload);
    }

    private function markCompleted(MassMail $massMail): void
    {
        $massMail->update([
            'status' => $massMail->failed_count > 0 && $massMail->sent_count === 0
                ? MassMail::STATUS_FAILED
                : MassMail::STATUS_SENT,
            'completed_at' => now(),
        ]);
    }
}
