<?php

namespace Extensions\Modules\Tickets\Support;

use App\Models\Email;
use App\Models\User;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketMember;
use Extensions\Modules\Tickets\Models\TicketMessage;
use Illuminate\Support\Str;

class TicketNotifier
{
    public static function ticketCreated(Ticket $ticket): void
    {
        $preview = static::preview(
            $ticket->messages()->where('type', TicketMessage::TYPE_COMMENT)->orderBy('id')->value('body')
        );

        foreach ($ticket->members as $member) {
            if (! $member->is_subscribed) {
                continue;
            }

            static::send($member, $ticket, 'tickets.created', [
                'ticket_title' => $ticket->title,
                'ticket_number' => $ticket->displayNumber(),
                'department' => $ticket->department->name,
                'preview' => $preview,
                'guest_note' => $member->isGuest()
                    ? 'Use the button below to view and reply to your ticket. Keep this email — it contains your private access link.'
                    : '',
            ], 'tickets.'.$ticket->id.'.created.'.$member->email);
        }

        static::notifyDepartment($ticket, 'tickets.department.created', [
            'ticket_title' => $ticket->title,
            'ticket_number' => $ticket->displayNumber(),
            'department' => $ticket->department->name,
            'requester_name' => $ticket->requesterName(),
            'preview' => $preview,
        ]);
    }

    public static function messagePosted(Ticket $ticket, TicketMessage $message): void
    {
        if (! $message->isComment()) {
            return;
        }

        $preview = static::preview($message->body);
        $authorEmail = Str::lower((string) $message->author_email);

        foreach ($ticket->members as $member) {
            if (! $member->is_subscribed) {
                continue;
            }

            if ($authorEmail !== '' && strcasecmp($member->email, $authorEmail) === 0) {
                continue;
            }

            static::send($member, $ticket, 'tickets.replied', [
                'ticket_title' => $ticket->title,
                'ticket_number' => $ticket->displayNumber(),
                'author_name' => $message->authorDisplayName(),
                'preview' => $preview,
            ], 'tickets.'.$ticket->id.'.replied.'.$member->email.md5((string) $message->id));
        }

        if (! $message->isFromAdmin()) {
            static::notifyDepartment($ticket, 'tickets.department.replied', [
                'ticket_title' => $ticket->title,
                'ticket_number' => $ticket->displayNumber(),
                'author_name' => $message->authorDisplayName(),
                'preview' => $preview,
            ]);
        }
    }

    public static function ticketClosed(Ticket $ticket, string $reason = 'closed'): void
    {
        $closeMessage = $reason === 'inactivity'
            ? 'Ticket '.$ticket->displayNumber().' was automatically closed because there was no reply for a while.'."\n".'Reopen the ticket if you still need help.'
            : 'Ticket '.$ticket->displayNumber().' has been closed.';

        foreach ($ticket->members as $member) {
            if (! $member->is_subscribed) {
                continue;
            }

            static::send($member, $ticket, 'tickets.closed', [
                'ticket_title' => $ticket->title,
                'ticket_number' => $ticket->displayNumber(),
                'close_message' => $closeMessage,
            ], 'tickets.'.$ticket->id.'.closed.'.$member->email);
        }
    }

    public static function memberInvited(Ticket $ticket, TicketMember $member, ?User $actor): void
    {
        $inviter = $actor ? ($actor->full_name ?: $actor->username) : 'Support';

        static::send($member, $ticket, 'tickets.member_invited', [
            'ticket_title' => $ticket->title,
            'ticket_number' => $ticket->displayNumber(),
            'inviter_name' => $inviter,
        ], 'tickets.'.$ticket->id.'.invited.'.$member->email);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    protected static function notifyDepartment(Ticket $ticket, string $template, array $variables): void
    {
        $email = $ticket->department?->notify_email;

        if (! $email) {
            return;
        }

        Email::actions()->sendEmailToAddress([
            'to' => $email,
            'template' => $template,
            'identifier' => $template.'.'.$ticket->id,
            'variables' => $variables,
            'button_url' => $ticket->adminUrl(),
            'display' => false,
            'cooldown' => 2,
        ]);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    protected static function send(TicketMember $member, Ticket $ticket, string $template, array $variables, string $identifier): void
    {
        $member->setRelation('ticket', $ticket);
        $url = $member->accessUrl();

        $payload = [
            'template' => $template,
            'identifier' => $identifier,
            'variables' => $variables,
            'mailable_type' => Ticket::class,
            'mailable_id' => $ticket->id,
            'button_url' => $url,
        ];

        if ($member->user_id && $member->user) {
            $member->user->email([
                ...$payload,
                'button' => [
                    'url' => $url,
                ],
            ]);

            return;
        }

        Email::actions()->sendEmailToAddress([
            ...$payload,
            'to' => $member->email,
            'display' => true,
        ]);
    }

    protected static function preview(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : Str::limit($value, 240);
    }
}
