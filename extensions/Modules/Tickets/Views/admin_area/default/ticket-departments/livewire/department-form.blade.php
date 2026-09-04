<?php

use Extensions\Modules\Tickets\Models\TicketDepartment;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $departmentId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public bool $is_active = true;

    public bool $allow_guest_tickets = false;

    public bool $allow_guest_members = false;

    public bool $allow_invites = true;

    public string $prefill_template = '';

    public string $auto_response = '';

    public string $notify_email = '';

    public int $auto_close_days = 0;

    public int $sort_order = 0;

    public function mount(?int $departmentId = null): void
    {
        $this->departmentId = $departmentId;

        if ($departmentId) {
            $department = $this->department;
            $this->name = $department->name;
            $this->slug = $department->slug;
            $this->description = (string) $department->description;
            $this->is_active = $department->is_active;
            $this->allow_guest_tickets = $department->allow_guest_tickets;
            $this->allow_guest_members = $department->allow_guest_members;
            $this->allow_invites = $department->allow_invites;
            $this->prefill_template = (string) $department->prefill_template;
            $this->auto_response = (string) $department->auto_response;
            $this->notify_email = (string) $department->notify_email;
            $this->auto_close_days = $department->auto_close_days;
            $this->sort_order = $department->sort_order;
        }
    }

    #[Computed]
    public function department(): ?TicketDepartment
    {
        return $this->departmentId ? TicketDepartment::findOrFail($this->departmentId) : null;
    }

    public function updatedName(): void
    {
        if ($this->name && ($this->slug === '' || $this->slug === Str::slug($this->getOriginalNameHint()))) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): mixed
    {
        $payload = [
            'admin_user_id' => auth()->id(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN),
            'allow_guest_tickets' => $this->allow_guest_tickets,
            'allow_guest_members' => $this->allow_guest_members,
            'allow_invites' => $this->allow_invites,
            'prefill_template' => $this->prefill_template ?: null,
            'auto_response' => $this->auto_response ?: null,
            'notify_email' => $this->notify_email ?: null,
            'auto_close_days' => $this->auto_close_days,
            'sort_order' => $this->sort_order,
        ];

        if ($this->departmentId) {
            TicketDepartment::actions()->updateAsAdmin([
                ...$payload,
                'department_id' => $this->departmentId,
            ]);
        } else {
            TicketDepartment::actions()->createAsAdmin($payload);
        }

        return $this->redirect(route('admin.ticket-departments.index'), navigate: true);
    }

    public function delete(): mixed
    {
        TicketDepartment::actions()->deleteAsAdmin([
            'admin_user_id' => auth()->id(),
            'department_id' => $this->departmentId,
        ]);

        return $this->redirect(route('admin.ticket-departments.index'), navigate: true);
    }

    protected function getOriginalNameHint(): string
    {
        return $this->department?->name ?? '';
    }
}

?>

<form class="card" wire:submit="save">
    <div class="card-header">
        <h3 class="card-title">{{ $departmentId ? 'Edit department' : 'Create department' }}</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Name</label>
            <div class="col">
                <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model.blur="name">
                @error('name') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Slug</label>
            <div class="col">
                <input type="text" class="form-control" wire:model="slug">
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Description</label>
            <div class="col">
                <textarea class="form-control" rows="2" wire:model="description"></textarea>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Status</label>
            <div class="col">
                <select class="form-select" wire:model="is_active">
                    <option value="1">Active — customers can open new tickets</option>
                    <option value="0">Inactive — hidden from the create form</option>
                </select>
                <small class="form-hint">Existing tickets in this department stay open and can still be replied to.</small>
                @error('is_active') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Guests</label>
            <div class="col">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="allow_guest_tickets">
                    <span class="form-check-label">Allow guests to create tickets in this department</span>
                </label>
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="allow_guest_members">
                    <span class="form-check-label">Allow guests (people without an account) to be added to tickets</span>
                </label>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Invites</label>
            <div class="col">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="allow_invites">
                    <span class="form-check-label">Allow inviting people to tickets by email</span>
                </label>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Notify email</label>
            <div class="col">
                <input type="email" class="form-control" wire:model="notify_email" placeholder="support@example.com">
                <small class="form-hint">Optional inbox that receives new tickets and customer replies. Staff can reply to those emails to post on the ticket.</small>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Auto-close</label>
            <div class="col">
                <div class="input-group">
                    <input type="number" class="form-control" wire:model="auto_close_days" min="0" max="365">
                    <span class="input-group-text">days</span>
                </div>
                <small class="form-hint">Close tickets waiting on the customer after this many days with no reply. Set 0 to disable.</small>
                @error('auto_close_days') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Sort order</label>
            <div class="col">
                <input type="number" class="form-control" wire:model="sort_order" min="0">
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Pre-fill template</label>
            <div class="col">
                <textarea class="form-control font-monospace" rows="6" wire:model="prefill_template" placeholder="Shown in the message box when this department is selected"></textarea>
                <small class="form-hint">Markdown is supported. Used when a customer starts a new ticket.</small>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Auto-response</label>
            <div class="col">
                <textarea class="form-control" rows="4" wire:model="auto_response" placeholder="Posted as the first staff reply when a ticket is created"></textarea>
                <small class="form-hint">Leave empty to skip the automatic reply.</small>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <div>
            @if($departmentId)
                <button type="button" class="btn btn-danger" wire:click="delete" wire:confirm="Delete this department? Tickets must be empty.">Delete</button>
            @endif
        </div>
        <button type="submit" class="btn btn-primary">{{ $departmentId ? 'Save' : 'Create' }}</button>
    </div>
</form>
