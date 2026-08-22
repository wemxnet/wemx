<?php

use App\Actions\RoleActions;
use Livewire\Volt\Component;
use App\Models\Permission;

new class extends Component {
    public $name;

    public $description = '';

    public $parent_role_id = null;

    public bool $all_permissions = false;

    public array $permissions = [];

    public array $inheritedPermissions = [];

    public function updated()
    {
        if ($this->parentRole) {
            // if parent has super_admin, this role must also have super_admin
            if ($this->parentRole->super_admin) {
                $this->all_permissions = true;
            }

            // Get all permissions from parent role including inherited ones
            $this->inheritedPermissions = $this->parentRole->getAllPermissions();

            // Remove inherited permissions from selected permissions
            $this->permissions = array_merge($this->permissions, $this->inheritedPermissions);
        } else {
            $this->inheritedPermissions = [];
        }
    }

    #[\Livewire\Attributes\Computed]
    public function parentRole()
    {
        return \App\Models\Role::find($this->parent_role_id);
    }

    public function mount(): void
    {
        abort_if(!auth()->user()->hasPerm('admin.roles.create'), 403);
    }

    public function createRole()
    {
        abort_if(!auth()->user()->hasPerm('admin.roles.create'), 403);

        $this->resetErrorBag();

        RoleActions::createRoleAsAdmin([
            'name' => $this->name,
            'description' => $this->description,
            'super_admin' => $this->all_permissions,
            'permissions' => $this->all_permissions ? [] : $this->permissions,
            'parent_role_id' => $this->parent_role_id,
        ]);

        $this->redirect(route('admin.roles.index'), true);
    }
}

?>

<form class="card" wire:submit="createRole()">
    <div class="card-header">
        <h3 class="card-title">Create Role</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label required" for="name-input">{{ __('messages.name') }}</label>
            <div class="col">
                <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror"
                       aria-describedby="name-input" id="name-input" placeholder="Name">
                @error('name')
                <x-admin::form.error :message="$message"/>
                @else
                    <small class="form-hint">The name of the role</small>
                    @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="description-input">{{ __('messages.description') }}</label>
            <div class="col">
                <textarea class="form-control @error('description') is-invalid @enderror" wire:model="description"
                          id="description-input" rows="2" placeholder="Content.."></textarea>
                @error('description')
                    <x-admin::form.error :message="$message"/>
                @else
                    <small class="form-hint">The description of the role</small>
                @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required" for="parent-input">Parent Role</label>
            <div class="col">
                <x-admin::form.select
                    id="parent-input"
                    wire:model.change="parent_role_id"
                    :options="\App\Models\Role::all()->pluck('name', 'id')->toArray()"
                />
                @error('parent_role_id')
                    <x-admin::form.error :message="$message"/>
                @else
                    <small class="form-hint">Selecting a parent role will inherit permissions from the parent role to this role</small>
                @enderror
            </div>
        </div>
        @if(auth()->user()->isPrimaryAdmin())
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="all_permissions-input">Full Access</label>
                <div class="col">
                    <label class="form-check form-switch">
                        <input class="form-check-input" wire:model.change="all_permissions" id="all_permissions-input"
                               type="checkbox"/>
                        <span class="form-check-label">Does this role have all permissions</span>
                    </label>
                    @error('super_admin')
                        <x-admin::form.error :message="$message"/>
                    @enderror
                </div>
            </div>
        @endif
        @if(!$all_permissions)
            <div class="mb-3 row">
                <label class="col-3 col-form-label required" for="permissions-input">Permissions</label>
                <div class="col">
                    <div class="mb-3">
                        <div class="row" id="permissions-input">
                            @error('permissions')
                                <x-admin::form.error :message="$message"/>
                            @enderror
                            @foreach(Permission::getAllPermissionsByGroup() as $permissionGroupName => $permissionGroup)
                                <hr>
                                <div class="mb-2 col-12">
                                    <strong class="text-uppercase">{{ $permissionGroupName }}</strong>
                                </div>
                                @foreach($permissionGroup as $permission => $description)
                                    @continue(!auth()->user()->isPrimaryAdmin() && !auth()->user()->hasPermission($permission))
                                <div class="mb-2 col-4">
                                    <div class="form-label">{{ $permission }} @if(in_array($permission, $inheritedPermissions)) (Parent) @endif</div>
                                    <label class="form-check form-switch"
                                           for="permission-{{ $permission }}-input">
                                        <input class="form-check-input" type="checkbox"
                                               wire:model="permissions"
                                              @if(in_array($permission, $inheritedPermissions)) disabled checked @endif
                                                @if(in_array($permission, $permissions)) checked @endif
                                               id="permission-{{ $permission }}-input"
                                               value="{{ $permission }}"/>
                                        <span class="form-check-label">{{ $description }}</span>
                                    </label>
                                </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
    <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">{{ __('messages.create') }}</button>
    </div>
</form>
