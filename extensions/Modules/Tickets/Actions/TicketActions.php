<?php

namespace Extensions\Modules\Tickets\Actions;

use App\Actions\Action;
use App\Models\Order;
use App\Models\User;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Extensions\Modules\Tickets\Models\TicketMember;
use Extensions\Modules\Tickets\Models\TicketMessage;
use Extensions\Modules\Tickets\Support\TicketNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketActions extends Action
{
    public function createAsClient(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'department_id' => ['required', 'integer', 'exists:ticket_departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::in(Ticket::priorities())],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ])->validate();

        $user = User::findOrFail($validated['user_id']);
        $department = $this->activeDepartment($validated['department_id']);
        $order = $this->resolveOrderForUser($validated['order_id'] ?? null, $user);

        return $this->createTicket(
            department: $department,
            title: $validated['title'],
            body: $validated['body'],
            priority: $validated['priority'],
            user: $user,
            order: $order,
        );
    }

    public function createAsGuest(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'department_id' => ['required', 'integer', 'exists:ticket_departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::in(Ticket::priorities())],
            'guest_name' => ['required', 'string', 'max:100'],
            'guest_email' => ['required', 'email', 'max:255'],
        ])->validate();

        $department = $this->activeDepartment($validated['department_id']);

        if (! $department->allow_guest_tickets) {
            throw ValidationException::withMessages([
                'department_id' => 'This department does not accept tickets from guests.',
            ]);
        }

        $existingUser = User::query()
            ->where('email', Str::lower($validated['guest_email']))
            ->first();

        return $this->createTicket(
            department: $department,
            title: $validated['title'],
            body: $validated['body'],
            priority: $validated['priority'],
            user: $existingUser,
            guestName: $validated['guest_name'],
            guestEmail: Str::lower($validated['guest_email']),
        );
    }

    public function createAsAdmin(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'department_id' => ['required', 'integer', 'exists:ticket_departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::in(Ticket::priorities())],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.create');
        $customer = User::findOrFail($validated['user_id']);
        $department = $this->activeDepartment($validated['department_id']);
        $order = $this->resolveOrderForUser($validated['order_id'] ?? null, $customer);

        $ticket = $this->createTicket(
            department: $department,
            title: $validated['title'],
            body: $validated['body'],
            priority: $validated['priority'],
            user: $customer,
            order: $order,
            createdByStaff: $admin,
        );

        $this->ensureMember($ticket, $admin, TicketMember::ROLE_STAFF, subscribed: true);

        return $ticket;
    }

    public function replyAsParticipant(array $input): TicketMessage
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_email' => ['nullable', 'email'],
            'access_token' => ['nullable', 'string'],
            'body' => ['required', 'string', 'max:20000'],
            'close' => ['sometimes', 'boolean'],
        ])->validate();

        $ticket = Ticket::query()->with('members')->findOrFail($validated['ticket_id']);
        $user = isset($validated['user_id']) ? User::find($validated['user_id']) : null;

        $this->assertCanParticipate($ticket, $user, $validated['guest_email'] ?? null, $validated['access_token'] ?? null);
        $this->assertUnlocked($ticket);

        if ($ticket->isClosed()) {
            throw ValidationException::withMessages([
                'body' => 'This ticket is closed. Reopen it before sending a new message.',
            ]);
        }

        $isStaff = (bool) $user?->isStaff();
        $member = $this->resolveMember($ticket, $user, $validated['guest_email'] ?? null, $validated['access_token'] ?? null);

        $message = $this->addComment($ticket, $validated['body'], $user, $member, $isStaff, fromAdmin: false);

        if (! empty($validated['close'])) {
            $this->closeTicket($ticket, $user, $member, fromAdmin: false);
        }

        TicketNotifier::messagePosted($ticket->fresh(['members', 'department']), $message);

        return $message;
    }

    public function replyAsAdmin(array $input): TicketMessage
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:20000'],
            'close' => ['sometimes', 'boolean'],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.view');
        $ticket = Ticket::query()->with('members', 'department')->findOrFail($validated['ticket_id']);

        $this->ensureMember($ticket, $admin, TicketMember::ROLE_STAFF, subscribed: true);

        if ($ticket->isClosed()) {
            throw ValidationException::withMessages([
                'body' => 'This ticket is closed. Reopen it before sending a new message.',
            ]);
        }

        $member = $ticket->memberForUser($admin);
        $message = $this->addComment($ticket, $validated['body'], $admin, $member, isStaff: true, fromAdmin: true);

        if (! empty($validated['close'])) {
            $this->closeTicket($ticket, $admin, $member, fromAdmin: true);
        }

        TicketNotifier::messagePosted($ticket->fresh(['members', 'department']), $message);

        return $message;
    }

    public function addInternalNote(array $input): TicketMessage
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:20000'],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.view');
        $ticket = Ticket::findOrFail($validated['ticket_id']);

        return $ticket->messages()->create([
            'user_id' => $admin->id,
            'type' => TicketMessage::TYPE_NOTE,
            'is_staff' => true,
            'from_admin' => true,
            'body' => $validated['body'],
            'author_name' => $admin->full_name ?: $admin->username,
            'author_email' => $admin->email,
        ]);
    }

    public function close(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_email' => ['nullable', 'email'],
            'access_token' => ['nullable', 'string'],
            'as_admin' => ['sometimes', 'boolean'],
        ])->validate();

        $ticket = Ticket::query()->with('members')->findOrFail($validated['ticket_id']);
        $user = isset($validated['user_id']) ? User::find($validated['user_id']) : null;

        if (! empty($validated['as_admin'])) {
            $this->staffUser($user?->id, 'admin.tickets.update');
        } else {
            $this->assertCanParticipate($ticket, $user, $validated['guest_email'] ?? null, $validated['access_token'] ?? null);
            $this->assertUnlocked($ticket);
        }

        $member = $user ? $ticket->memberForUser($user) : $this->resolveMember($ticket, null, $validated['guest_email'] ?? null, $validated['access_token'] ?? null);

        return $this->closeTicket($ticket, $user, $member, fromAdmin: ! empty($validated['as_admin']));
    }

    public function reopen(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_email' => ['nullable', 'email'],
            'access_token' => ['nullable', 'string'],
            'as_admin' => ['sometimes', 'boolean'],
        ])->validate();

        $ticket = Ticket::query()->with('members')->findOrFail($validated['ticket_id']);
        $user = isset($validated['user_id']) ? User::find($validated['user_id']) : null;

        if (! empty($validated['as_admin'])) {
            $this->staffUser($user?->id, 'admin.tickets.update');
        } else {
            $this->assertCanParticipate($ticket, $user, $validated['guest_email'] ?? null, $validated['access_token'] ?? null);
            $this->assertUnlocked($ticket);
        }

        if ($ticket->isOpen()) {
            throw ValidationException::withMessages([
                'ticket_id' => 'This ticket is already open.',
            ]);
        }

        $fromAdmin = ! empty($validated['as_admin']);

        $ticket->update([
            'status' => Ticket::STATUS_OPEN,
            'closed_at' => null,
            'closed_by' => null,
            'last_reply_from' => $fromAdmin ? Ticket::REPLY_STAFF : Ticket::REPLY_CLIENT,
            'last_replied_at' => now(),
        ]);

        $this->recordEvent($ticket, $user, 'status_changed', [
            'action' => 'reopened',
            'from' => Ticket::STATUS_CLOSED,
            'to' => Ticket::STATUS_OPEN,
        ], fromAdmin: $fromAdmin);

        return $ticket->fresh();
    }

    public function lock(array $input): Ticket
    {
        return $this->setLocked($input, locked: true);
    }

    public function unlock(array $input): Ticket
    {
        return $this->setLocked($input, locked: false);
    }

    public function changePriority(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'priority' => ['required', Rule::in(Ticket::priorities())],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.update');
        $ticket = Ticket::findOrFail($validated['ticket_id']);

        if ($ticket->priority === $validated['priority']) {
            return $ticket;
        }

        $from = $ticket->priority;
        $ticket->update(['priority' => $validated['priority']]);

        $this->recordEvent($ticket, $admin, 'priority_changed', [
            'from' => $from,
            'to' => $validated['priority'],
        ], fromAdmin: true);

        return $ticket->fresh();
    }

    public function changeDepartment(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'department_id' => ['required', 'integer', 'exists:ticket_departments,id'],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.update');
        $ticket = Ticket::query()->with('department')->findOrFail($validated['ticket_id']);
        $department = $this->findDepartment($validated['department_id']);

        if ($ticket->department_id === $department->id) {
            return $ticket;
        }

        $from = $ticket->department->name;
        $ticket->update(['department_id' => $department->id]);

        $this->recordEvent($ticket, $admin, 'department_changed', [
            'from' => $from,
            'to' => $department->name,
        ], fromAdmin: true);

        return $ticket->fresh('department');
    }

    public function assign(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.update');
        $ticket = Ticket::findOrFail($validated['ticket_id']);

        $assignee = null;
        if (! empty($validated['assigned_to'])) {
            $assignee = User::findOrFail($validated['assigned_to']);

            if (! $assignee->isStaff()) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Tickets can only be assigned to staff members.',
                ]);
            }

            $this->ensureMember($ticket, $assignee, TicketMember::ROLE_STAFF, subscribed: true);
        }

        $ticket->update(['assigned_to' => $assignee?->id]);

        $this->recordEvent($ticket, $admin, 'assigned', [
            'to' => $assignee ? ($assignee->full_name ?: $assignee->username) : null,
        ], fromAdmin: true);

        return $ticket->fresh('assignee');
    }

    public function invite(array $input): TicketMember
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_email' => ['nullable', 'email'],
            'access_token' => ['nullable', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'as_admin' => ['sometimes', 'boolean'],
        ])->validate();

        $ticket = Ticket::query()->with(['members', 'department'])->findOrFail($validated['ticket_id']);
        $actor = isset($validated['user_id']) ? User::find($validated['user_id']) : null;

        if (! empty($validated['as_admin'])) {
            $this->staffUser($actor?->id, 'admin.tickets.update');
        } else {
            $this->assertCanParticipate($ticket, $actor, $validated['guest_email'] ?? null, $validated['access_token'] ?? null);
            $this->assertUnlocked($ticket);
        }

        if (! $ticket->department->allow_invites) {
            throw ValidationException::withMessages([
                'email' => 'Invites are disabled for this department.',
            ]);
        }

        $email = Str::lower($validated['email']);
        $invitee = User::query()->where('email', $email)->first();

        if (! $invitee && ! $ticket->department->allow_guest_members) {
            throw ValidationException::withMessages([
                'email' => 'Guests cannot be added to tickets in this department. Invite someone with an existing account.',
            ]);
        }

        if ($ticket->memberForUser($invitee, $email)) {
            throw ValidationException::withMessages([
                'email' => 'That person is already on this ticket.',
            ]);
        }

        $member = $this->addMember(
            ticket: $ticket,
            user: $invitee,
            email: $email,
            name: $invitee ? ($invitee->full_name ?: $invitee->username) : null,
            role: $invitee?->isStaff() ? TicketMember::ROLE_STAFF : TicketMember::ROLE_MEMBER,
            invitedBy: $actor,
        );

        $this->recordEvent($ticket, $actor, 'member_added', [
            'name' => $member->displayName(),
            'email' => $member->email,
        ], fromAdmin: ! empty($validated['as_admin']));

        TicketNotifier::memberInvited($ticket->fresh('department'), $member, $actor);

        return $member;
    }

    public function autoCloseInactive(): int
    {
        $closed = 0;

        TicketDepartment::query()
            ->where('auto_close_days', '>', 0)
            ->each(function (TicketDepartment $department) use (&$closed) {
                $ticketIds = Ticket::query()
                    ->open()
                    ->where('department_id', $department->id)
                    ->whereNull('locked_at')
                    ->where('last_reply_from', Ticket::REPLY_STAFF)
                    ->where('last_replied_at', '<=', now()->subDays($department->auto_close_days))
                    ->pluck('id');

                foreach ($ticketIds as $ticketId) {
                    $ticket = Ticket::query()->with(['members', 'department'])->find($ticketId);

                    if (! $ticket || $ticket->isClosed() || $ticket->isLocked()) {
                        continue;
                    }

                    $this->closeForInactivity($ticket, $department->auto_close_days);
                    $closed++;
                }
            });

        return $closed;
    }

    public function linkOrder(array $input): Ticket
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.update');
        $ticket = Ticket::findOrFail($validated['ticket_id']);
        $orderId = $validated['order_id'] ?? null;

        if ($ticket->order_id === $orderId) {
            return $ticket;
        }

        $order = $orderId ? $this->resolveOrderForUser($orderId, $ticket->user ?? $admin, allowAny: true) : null;

        if ($order && $ticket->user_id && $order->user_id !== $ticket->user_id) {
            throw ValidationException::withMessages([
                'order_id' => 'The order must belong to the ticket customer.',
            ]);
        }

        $ticket->update(['order_id' => $order?->id]);

        $this->recordEvent($ticket, $admin, 'order_linked', [
            'order_id' => $order?->id,
        ], fromAdmin: true);

        return $ticket->fresh('order');
    }

    public function removeMember(array $input): void
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'member_id' => ['required', 'integer', 'exists:ticket_members,id'],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.update');
        $ticket = Ticket::findOrFail($validated['ticket_id']);
        $member = $ticket->members()->where('id', $validated['member_id'])->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => 'Member not found on this ticket.',
            ]);
        }

        if ($member->role === TicketMember::ROLE_OWNER) {
            throw ValidationException::withMessages([
                'member_id' => 'The ticket owner cannot be removed.',
            ]);
        }

        $name = $member->displayName();
        $member->delete();

        $this->recordEvent($ticket, $admin, 'member_removed', [
            'name' => $name,
        ], fromAdmin: true);
    }

    public function subscribe(array $input): TicketMember
    {
        return $this->setSubscription($input, true);
    }

    public function unsubscribe(array $input): TicketMember
    {
        return $this->setSubscription($input, false);
    }

    public function deleteAsAdmin(array $input): bool
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.tickets.delete');
        $ticket = Ticket::findOrFail($validated['ticket_id']);

        return (bool) $ticket->delete();
    }

    protected function createTicket(
        TicketDepartment $department,
        string $title,
        string $body,
        string $priority,
        ?User $user = null,
        ?Order $order = null,
        ?string $guestName = null,
        ?string $guestEmail = null,
        ?User $createdByStaff = null,
    ): Ticket {
        $ticket = DB::transaction(function () use ($department, $title, $body, $priority, $user, $order, $guestName, $guestEmail, $createdByStaff) {
            $ownerEmail = Str::lower($user?->email ?? $guestEmail);
            $ownerName = $user ? ($user->full_name ?: $user->username) : $guestName;

            $ticket = Ticket::create([
                'number' => Ticket::nextNumber(),
                'department_id' => $department->id,
                'user_id' => $user?->id,
                'order_id' => $order?->id,
                'title' => $title,
                'priority' => $priority,
                'status' => Ticket::STATUS_OPEN,
                'last_reply_from' => Ticket::REPLY_CLIENT,
                'last_replied_at' => now(),
                'guest_name' => $user ? null : $guestName,
                'guest_email' => $user ? null : $guestEmail,
                'token' => Ticket::newToken(),
            ]);

            $this->addMember(
                ticket: $ticket,
                user: $user,
                email: $ownerEmail,
                name: $ownerName,
                role: TicketMember::ROLE_OWNER,
            );

            $this->addComment(
                ticket: $ticket,
                body: $body,
                user: $user,
                member: $ticket->memberForUser($user, $ownerEmail),
                isStaff: false,
                authorName: $ownerName,
                authorEmail: $ownerEmail,
            );

            if ($department->auto_response) {
                $ticket->messages()->create([
                    'type' => TicketMessage::TYPE_COMMENT,
                    'is_staff' => true,
                    'from_admin' => true,
                    'body' => $department->auto_response,
                    'author_name' => $department->name,
                ]);
            }

            if ($createdByStaff) {
                $this->recordEvent($ticket, $createdByStaff, 'member_added', [
                    'name' => $ownerName,
                    'email' => $ownerEmail,
                ], fromAdmin: true);
            }

            return $ticket;
        });

        TicketNotifier::ticketCreated($ticket->fresh(['members.user', 'department', 'user']));

        return $ticket->fresh(['department', 'user', 'members']);
    }

    protected function addComment(
        Ticket $ticket,
        string $body,
        ?User $user,
        ?TicketMember $member,
        bool $isStaff,
        ?string $authorName = null,
        ?string $authorEmail = null,
        bool $fromAdmin = false,
    ): TicketMessage {
        $message = $ticket->messages()->create([
            'user_id' => $user?->id,
            'type' => TicketMessage::TYPE_COMMENT,
            'is_staff' => $isStaff,
            'from_admin' => $fromAdmin,
            'body' => $body,
            'author_name' => $authorName ?: ($user ? ($user->full_name ?: $user->username) : $member?->displayName()),
            'author_email' => $authorEmail ?: ($user?->email ?? $member?->email),
        ]);

        $ticket->update([
            'last_reply_from' => $fromAdmin ? Ticket::REPLY_STAFF : Ticket::REPLY_CLIENT,
            'last_replied_at' => now(),
        ]);

        $member?->update(['last_read_at' => now()]);

        return $message;
    }

    protected function closeTicket(Ticket $ticket, ?User $user, ?TicketMember $member, bool $fromAdmin = false): Ticket
    {
        if ($ticket->isClosed()) {
            throw ValidationException::withMessages([
                'ticket_id' => 'This ticket is already closed.',
            ]);
        }

        $ticket->update([
            'status' => Ticket::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $user?->id,
            'last_replied_at' => now(),
        ]);

        $this->recordEvent($ticket, $user, 'status_changed', [
            'action' => 'closed',
            'from' => Ticket::STATUS_OPEN,
            'to' => Ticket::STATUS_CLOSED,
        ], $member, $fromAdmin);

        return $ticket->fresh();
    }

    protected function closeForInactivity(Ticket $ticket, int $days): Ticket
    {
        $ticket->update([
            'status' => Ticket::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => null,
        ]);

        $this->recordEvent($ticket, null, 'status_changed', [
            'action' => 'closed',
            'reason' => 'inactivity',
            'days' => $days,
            'from' => Ticket::STATUS_OPEN,
            'to' => Ticket::STATUS_CLOSED,
        ], fromAdmin: true);

        TicketNotifier::ticketClosed($ticket->fresh(['members.user', 'department']), 'inactivity');

        return $ticket->fresh();
    }

    protected function recordEvent(
        Ticket $ticket,
        ?User $user,
        string $eventType,
        array $meta,
        ?TicketMember $member = null,
        bool $fromAdmin = false,
    ): TicketMessage {
        return $ticket->messages()->create([
            'user_id' => $user?->id,
            'type' => TicketMessage::TYPE_EVENT,
            'event_type' => $eventType,
            'is_staff' => $fromAdmin || (bool) $user?->isStaff(),
            'from_admin' => $fromAdmin,
            'meta' => $meta,
            'author_name' => $user ? ($user->full_name ?: $user->username) : $member?->displayName(),
            'author_email' => $user?->email ?? $member?->email,
        ]);
    }

    protected function addMember(
        Ticket $ticket,
        ?User $user,
        string $email,
        ?string $name,
        string $role,
        ?User $invitedBy = null,
        bool $subscribed = true,
    ): TicketMember {
        return $ticket->members()->create([
            'user_id' => $user?->id,
            'invited_by' => $invitedBy?->id,
            'email' => Str::lower($email),
            'name' => $name,
            'role' => $role,
            'is_subscribed' => $subscribed,
            'access_token' => Ticket::newToken(),
            'last_read_at' => now(),
        ]);
    }

    protected function ensureMember(Ticket $ticket, User $user, string $role, bool $subscribed = true): TicketMember
    {
        $existing = $ticket->memberForUser($user);

        if ($existing) {
            return $existing;
        }

        $ticket->load('members');

        if ($existing = $ticket->memberForUser($user)) {
            return $existing;
        }

        return $this->addMember(
            ticket: $ticket,
            user: $user,
            email: $user->email,
            name: $user->full_name ?: $user->username,
            role: $role,
            subscribed: $subscribed,
        );
    }

    protected function setSubscription(array $input, bool $subscribed): TicketMember
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_email' => ['nullable', 'email'],
            'access_token' => ['nullable', 'string'],
        ])->validate();

        $ticket = Ticket::query()->with('members')->findOrFail($validated['ticket_id']);
        $user = isset($validated['user_id']) ? User::find($validated['user_id']) : null;

        $this->assertCanParticipate($ticket, $user, $validated['guest_email'] ?? null, $validated['access_token'] ?? null);

        $member = $this->resolveMember($ticket, $user, $validated['guest_email'] ?? null, $validated['access_token'] ?? null);

        if (! $member) {
            throw ValidationException::withMessages([
                'ticket_id' => 'You are not a member of this ticket.',
            ]);
        }

        if ($member->is_subscribed === $subscribed) {
            return $member;
        }

        $member->update(['is_subscribed' => $subscribed]);

        $this->recordEvent($ticket, $user, $subscribed ? 'subscribed' : 'unsubscribed', [
            'email' => $member->email,
        ], $member);

        return $member->fresh();
    }

    protected function activeDepartment(int $departmentId): TicketDepartment
    {
        return $this->findDepartment($departmentId, activeOnly: true);
    }

    protected function findDepartment(int $departmentId, bool $activeOnly = false): TicketDepartment
    {
        $department = TicketDepartment::find($departmentId);

        if (! $department) {
            throw ValidationException::withMessages([
                'department_id' => 'The selected department is not available.',
            ]);
        }

        if ($activeOnly && ! $department->is_active) {
            throw ValidationException::withMessages([
                'department_id' => 'This department is inactive and is not accepting new tickets.',
            ]);
        }

        return $department;
    }

    protected function resolveOrderForUser(?int $orderId, User $user, bool $allowAny = false): ?Order
    {
        if (! $orderId) {
            return null;
        }

        $order = Order::find($orderId);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'The selected order could not be found.',
            ]);
        }

        if (! $allowAny && $order->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'order_id' => 'You can only attach tickets to your own orders.',
            ]);
        }

        return $order;
    }

    protected function setLocked(array $input, bool $locked): Ticket
    {
        $validated = Validator::make($input, [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
        ])->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.tickets.update');
        $ticket = Ticket::findOrFail($validated['ticket_id']);

        if ($ticket->isLocked() === $locked) {
            return $ticket;
        }

        $ticket->update([
            'locked_at' => $locked ? now() : null,
            'locked_by' => $locked ? $admin->id : null,
        ]);

        $this->recordEvent($ticket, $admin, 'lock_changed', [
            'action' => $locked ? 'locked' : 'unlocked',
        ], fromAdmin: true);

        return $ticket->fresh();
    }

    protected function staffUser(?int $userId, string $permission): User
    {
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->isStaff() || ! $user->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'You do not have permission to perform this action.',
            ]);
        }

        return $user;
    }

    protected function assertCanParticipate(Ticket $ticket, ?User $user, ?string $guestEmail, ?string $accessToken): void
    {
        if ($user?->isStaff() && $user->hasPermission('admin.tickets.view')) {
            return;
        }

        if ($ticket->canBeViewedBy($user, $accessToken)) {
            return;
        }

        if ($guestEmail && $ticket->memberForUser(null, $guestEmail)) {
            return;
        }

        throw ValidationException::withMessages([
            'ticket_id' => 'You do not have access to this ticket.',
        ]);
    }

    protected function assertUnlocked(Ticket $ticket): void
    {
        if ($ticket->isLocked()) {
            throw ValidationException::withMessages([
                'ticket_id' => 'This ticket is locked. Only staff can make changes.',
            ]);
        }
    }

    protected function resolveMember(Ticket $ticket, ?User $user, ?string $guestEmail, ?string $accessToken): ?TicketMember
    {
        if ($user) {
            return $ticket->memberForUser($user);
        }

        if ($accessToken) {
            $byToken = $ticket->members->first(fn (TicketMember $member) => hash_equals($member->access_token, $accessToken));

            if ($byToken) {
                return $byToken;
            }
        }

        if ($guestEmail) {
            return $ticket->memberForUser(null, $guestEmail);
        }

        return null;
    }
}
