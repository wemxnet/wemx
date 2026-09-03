<?php

use App\Models\Order;
use App\Models\User;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public string $customer_email = '';

    public string $title = '';

    public string $body = '';

    public string $priority = Ticket::PRIORITY_MEDIUM;

    public ?int $department_id = null;

    public ?int $order_id = null;

    public ?int $prefillOrderId = null;

    public ?int $prefillUserId = null;

    public function mount(?int $prefillOrderId = null, ?int $prefillUserId = null): void
    {
        $this->prefillOrderId = $prefillOrderId;
        $this->prefillUserId = $prefillUserId;
        $this->department_id = TicketDepartment::query()->active()->ordered()->value('id');

        if ($prefillOrderId) {
            $this->order_id = $prefillOrderId;
            $order = Order::query()->with('user')->find($prefillOrderId);

            if ($order?->user) {
                $this->customer_email = $order->user->email;
            }
        }

        if ($prefillUserId && $this->customer_email === '') {
            $customer = User::query()->find($prefillUserId);

            if ($customer) {
                $this->customer_email = $customer->email;
            }
        }
    }

    #[Computed]
    public function departments()
    {
        return TicketDepartment::query()->active()->ordered()->get();
    }

    public function submit(): mixed
    {
        $customer = User::query()
            ->where('email', $this->customer_email)
            ->orWhere('username', $this->customer_email)
            ->first();

        if (! $customer) {
            $this->addError('customer_email', 'No customer found with that email or username.');

            return null;
        }

        $ticket = Ticket::actions()->createAsAdmin([
            'admin_user_id' => auth()->id(),
            'user_id' => $customer->id,
            'department_id' => $this->department_id,
            'title' => $this->title,
            'body' => $this->body,
            'priority' => $this->priority,
            'order_id' => $this->order_id ?: null,
        ]);

        return $this->redirect(route('admin.tickets.view', $ticket), navigate: true);
    }
}

?>

<form class="card" wire:submit="submit">
    <div class="card-header">
        <h3 class="card-title">Create ticket for a customer</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Customer</label>
            <div class="col">
                <input type="text" class="form-control @error('customer_email') is-invalid @enderror" wire:model="customer_email" placeholder="Email or username">
                @error('customer_email')
                    <x-admin::form.error :message="$message"/>
                @else
                    <small class="form-hint">Look up an existing customer by email or username.</small>
                @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Subject</label>
            <div class="col">
                <input type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title">
                @error('title') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Department</label>
            <div class="col">
                <select class="form-select" wire:model="department_id">
                    @foreach($this->departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
                @error('department_id') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Priority</label>
            <div class="col">
                <select class="form-select" wire:model="priority">
                    @foreach(\Extensions\Modules\Tickets\Models\Ticket::priorities() as $priority)
                        <option value="{{ $priority }}">{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Related order ID</label>
            <div class="col">
                <input type="number" class="form-control @error('order_id') is-invalid @enderror" wire:model="order_id" placeholder="Optional">
                @error('order_id') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Message</label>
            <div class="col">
                <x-admin::form.markdown-editor id="admin-create-body" wire:model="body" :rows="10"/>
                @error('body') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">Create ticket</button>
    </div>
</form>
