<?php

namespace Extensions\Modules\Tickets\Support;

use App\Models\User;
use Extensions\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Str;

class TicketInboundMail
{
    public static function mailbox(): ?string
    {
        $configured = settings('tickets_inbound_mailbox');

        if (is_string($configured) && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return Str::lower($configured);
        }

        $from = config('mail.from.address');

        return is_string($from) && filter_var($from, FILTER_VALIDATE_EMAIL)
            ? Str::lower($from)
            : null;
    }

    public static function replyTo(Ticket $ticket): ?string
    {
        $mailbox = static::mailbox();

        if ($mailbox === null) {
            return null;
        }

        [$local, $domain] = explode('@', $mailbox, 2);

        return $local.'+t'.$ticket->id.'.'.static::tokenFor($ticket).'@'.$domain;
    }

    public static function tokenFor(Ticket $ticket): string
    {
        return substr(hash_hmac('sha256', (string) $ticket->id, static::signingKey()), 0, 16);
    }

    public static function messageIdFor(Ticket $ticket): string
    {
        $domain = Str::after((string) static::mailbox(), '@') ?: 'localhost';

        return 'ticket.'.$ticket->id.'.'.Str::lower(Str::random(12)).'@'.$domain;
    }

    /**
     * @return array<string, string>
     */
    public static function outboundData(Ticket $ticket): array
    {
        $data = [
            'message_id' => static::messageIdFor($ticket),
        ];

        $replyTo = static::replyTo($ticket);

        if ($replyTo) {
            $data['reply_to'] = $replyTo;
        }

        return $data;
    }

    public static function webhookToken(): string
    {
        $stored = settings('tickets_inbound_webhook_token');

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return hash_hmac('sha256', 'tickets-inbound-webhook', static::signingKey());
    }

    public static function tokenIsValid(string $token): bool
    {
        return hash_equals(static::webhookToken(), $token);
    }

    public static function locateTicket(InboundMailMessage $message): ?Ticket
    {
        foreach ($message->recipients as $address) {
            $ticket = static::ticketFromAddress($address);

            if ($ticket) {
                return $ticket;
            }
        }

        foreach (array_merge($message->inReplyTo, $message->references) as $messageId) {
            $ticket = static::ticketFromMessageId($messageId);

            if ($ticket) {
                return $ticket;
            }
        }

        return static::ticketFromSubject($message->subject, $message->fromEmail);
    }

    public static function ticketFromAddress(string $address): ?Ticket
    {
        if (! preg_match('/\+t(\d+)\.([a-f0-9]{16})@/i', $address, $matches)) {
            return null;
        }

        $ticket = Ticket::query()->with(['members', 'department'])->find((int) $matches[1]);

        if (! $ticket) {
            return null;
        }

        if (! hash_equals(static::tokenFor($ticket), strtolower($matches[2]))) {
            return null;
        }

        return $ticket;
    }

    public static function ticketFromMessageId(string $messageId): ?Ticket
    {
        if (! preg_match('/^ticket\.(\d+)\.[a-z0-9]+@/i', $messageId, $matches)) {
            return null;
        }

        return Ticket::query()->with(['members', 'department'])->find((int) $matches[1]);
    }

    public static function ticketFromSubject(string $subject, ?string $fromEmail): ?Ticket
    {
        if ($fromEmail === null || ! preg_match('/#(\d+)\b/', $subject, $matches)) {
            return null;
        }

        $ticket = Ticket::query()
            ->with(['members', 'department'])
            ->where('number', (int) $matches[1])
            ->first();

        if (! $ticket) {
            return null;
        }

        $fromEmail = Str::lower($fromEmail);

        if ($ticket->memberForUser(null, $fromEmail)) {
            return $ticket;
        }

        $user = User::query()->where('email', $fromEmail)->first();

        if ($user?->isStaff() && $user->hasPermission('admin.tickets.view')) {
            return $ticket;
        }

        $notifyEmail = $ticket->department?->notify_email;

        if ($notifyEmail && strcasecmp($notifyEmail, $fromEmail) === 0) {
            return $ticket;
        }

        return null;
    }

    protected static function signingKey(): string
    {
        return (string) config('app.key');
    }
}
