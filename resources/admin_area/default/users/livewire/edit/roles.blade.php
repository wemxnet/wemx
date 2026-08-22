<?php

use Livewire\Volt\Component;
use App\Models\Role;

new class extends Component
{
    public $user;

    public $role_id;

    public function mount($user)
    {
        abort_if(!auth()->user()->hasPerm('admin.users.manage_roles'), 403);

        $this->user = $user;
    }

    public function assignRole()
    {
        abort_if(!auth()->user()->hasPerm('admin.users.manage_roles'), 403);

        $this->resetErrorBag();

        \App\Actions\RoleActions::assignRoleAsAdmin([
            'user_id' => $this->user->id,
            'role_id' => $this->role_id,
            'assigner_id' => auth()->id(),
        ]);

        $this->reset('role_id');
    }

    public function removeRole($roleId)
    {
        abort_if(!auth()->user()->hasPerm('admin.users.manage_roles'), 403);

        \App\Actions\RoleActions::removeRoleAsAdmin([
            'user_id' => $this->user->id,
            'role_id' => $roleId,
        ]);
    }
}

?>

<div>
    <p class="text-secondary">
        Assign roles to this user to grant specific permissions and access levels within the application.
    </p>

    <div class="mb-3 d-flex gap-2">
        <x-admin::form.select
            label="Assign Roles"
            wire:model="role_id"
            :options="Role::all()->pluck('name', 'id')->toArray()"
        />
        <x-admin::button label="Assign" wire:click="assignRole" wire:confirm="Assigning this role to the user will grant them all permissions part of the role, are you sure?" />
    </div>

    @error('role_id')
        <x-admin::form.error :message="$message"/>
    @enderror

    <div>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Permissions</th>
                    <th>Assigned By</th>
                    <th>Assigned At</th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($user->roles as $role)
                <tr>
                    <td>
                        @perm('admin.roles.update')
                            <a href="{{ route('admin.roles.edit', $role->role_id) }}" class="text-reset">{{ $role->role->name }}</a>
                        @else
                            {{ $role->role->name }}
                        @endperm
                    </td>
                    <td class="text-secondary">
                        {{ Str::limit($role->role->description ?? '-', 50) }}
                    </td>
                    <td class="text-secondary">
                        {{ $role->role->super_admin ? 'All' : count($role->role->getAllPermissions()) }} permissions
                    </td>
                    <td class="text-secondary">
                        <a href="{{ route('admin.users.edit', $role->assigner_id) }}" class="text-reset">
                            {{ $role->assigner ? $role->assigner->username : 'Deleted User' }} (ID: {{ $role->assigner_id }})
                        </a>
                    </td>
                    <td class="text-secondary">
                        {{ $role->created_at->format('Y-m-d H:i') }}
                    </td>
                    <td>
                        <a class="text-danger" href="#" wire:click="removeRole({{ $role->role_id }})" wire:confirm="">Remove</a>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
