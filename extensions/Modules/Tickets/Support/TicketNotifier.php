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

            $lines = [
                'A new support ticket '.$ticket->displayNumber().' was opened in '.$ticket->department->name.'.',
                $preview,
            ];

            if ($member->isGuest()) {
                $lines[] = 'Use the button below to view and reply to your ticket. Keep this email — it contains your private access link.';
            }

            static::send($member, $ticket, 'Ticket opened: '.$ticket->title, $lines, 'View ticket');
        }

        static::notifyDepartment($ticket, 'New ticket '.$ticket->displayNumber().': '.$ticket->title, [
            $ticket->requesterName().' opened a ticket in '.$ticket->department->name.'.',
            $preview,
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

            static::send($member, $ticket, 'New reply on ticket '.$ticket->displayNumber(), [
                $message->authorDisplayName().' replied to '.$ticket->displayNumber().': '.$ticket->title,
                $preview,
            ], 'View reply');
        }

        if (! $message->isFromAdmin()) {
            static::notifyDepartment($ticket, 'New reply on '.$ticket->displayNumber().': '.$ticket->title, [
                $message->authorDisplayName().' replied to the ticket.',
                $preview,
            ]);
        }
    }

    public static function ticketClosed(Ticket $ticket, string $reason = 'closed'): void
    {
        $lines = $reason === 'inactivity'
            ? [
                'Ticket '.$ticket->displayNumber().' was automatically closed because there was no reply for a while.',
                'Reopen the ticket if you still need help.',
            ]
            : ['Ticket '.$ticket->displayNumber().' has been closed.'];

        foreach ($ticket->members as $member) {
            if (! $member->is_subscribed) {
                continue;
            }

            static::send($member, $ticket, 'Ticket closed: '.$ticket->title, $lines, 'View ticket');
        }
    }

    public static function memberInvited(Ticket $ticket, TicketMember $member, ?User $actor): void
    {
        $inviter = $actor ? ($actor->full_name ?: $actor->username) : 'Support';

        static::send($member, $ticket, 'You were added to ticket '.$ticket->displayNumber(), [
            $inviter.' invited you to the ticket "'.$ticket->title.'".',
            'Open the ticket to read the conversation and reply.',
        ], 'Open ticket');
    }

    protected static function notifyDepartment(Ticket $ticket, string $subject, array $lines): void
    {
        $email = $ticket->department?->notify_email;

        if (! $email) {
            return;
        }

        Email::actions()->sendEmailToAddress([
            'to' => $email,
            'subject' => $subject,
            'lines' => array_values(array_filter($lines)),
            'button_text' => 'Open in admin',
            'button_url' => $ticket->adminUrl(),
            'display' => false,
            'identifier' => 'tickets.department.'.$ticket->id.'.'.md5($subject),
            'cooldown' => 2,
        ]);
    }

    protected static function send(TicketMember $member, Ticket $ticket, string $subject, array $lines, string $button): void
    {
        $member->setRelation('ticket', $ticket);
        $url = $member->accessUrl();

        $payload = [
            'subject' => $subject,
            'lines' => array_values(array_filter($lines)),
            'identifier' => 'tickets.'.$ticket->id.'.'.md5($subject.$member->email),
            'mailable_type' => Ticket::class,
            'mailable_id' => $ticket->id,
        ];

        if ($member->user_id && $member->user) {
            $member->user->email([
                ...$payload,
                'button' => [
                    'text' => $button,
                    'url' => $url,
                ],
            ]);

            return;
        }

        Email::create([
            ...$payload,
            'to' => $member->email,
            'button_text' => $button,
            'button_url' => $url,
            'display' => true,
        ]);
    }

    protected static function preview(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : Str::limit($value, 240);
    }
}
