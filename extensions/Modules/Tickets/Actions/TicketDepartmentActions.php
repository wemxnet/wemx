<?php

namespace Extensions\Modules\Tickets\Actions;

use App\Actions\Action;
use App\Models\User;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TicketDepartmentActions extends Action
{
    public function createAsAdmin(array $input): TicketDepartment
    {
        $validated = Validator::make($input, $this->rules())->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.ticket-departments.create');
        unset($validated['admin_user_id']);

        $validated['slug'] = TicketDepartment::generateSlug($validated['slug'] ?? $validated['name']);

        return TicketDepartment::create(self::omitNullValues($validated));
    }

    public function updateAsAdmin(array $input): TicketDepartment
    {
        $validated = Validator::make($input, array_merge($this->rules(updating: true), [
            'department_id' => ['required', 'integer', 'exists:ticket_departments,id'],
        ]))->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.ticket-departments.update');

        $department = TicketDepartment::findOrFail($validated['department_id']);

        unset($validated['admin_user_id'], $validated['department_id']);

        if (isset($validated['name']) || isset($validated['slug'])) {
            $validated['slug'] = TicketDepartment::generateSlug(
                $validated['slug'] ?? $validated['name'] ?? $department->name,
                $department->id,
            );
        }

        $department->update(self::omitNullValues($validated));

        return $department->fresh();
    }

    public function deleteAsAdmin(array $input): bool
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'department_id' => ['required', 'integer', 'exists:ticket_departments,id'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.ticket-departments.delete');

        $department = TicketDepartment::findOrFail($validated['department_id']);

        $openTickets = $department->tickets()->open()->count();

        if ($openTickets > 0) {
            throw ValidationException::withMessages([
                'department_id' => 'This department cannot be deleted while it still has open tickets.',
            ]);
        }

        if ($department->tickets()->exists()) {
            throw ValidationException::withMessages([
                'department_id' => 'This department cannot be deleted until its tickets are moved or deleted.',
            ]);
        }

        return (bool) $department->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'allow_guest_tickets' => ['sometimes', 'boolean'],
            'allow_guest_members' => ['sometimes', 'boolean'],
            'allow_invites' => ['sometimes', 'boolean'],
            'prefill_template' => ['nullable', 'string', 'max:20000'],
            'auto_response' => ['nullable', 'string', 'max:20000'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            'auto_close_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }

    protected function staffUser(int $userId, string $permission): User
    {
        $user = User::find($userId);

        if (! $user || ! $user->isStaff() || ! $user->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'You do not have permission to manage departments.',
            ]);
        }

        return $user;
    }
}
