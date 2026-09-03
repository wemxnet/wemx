<?php

use App\Models\Order;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public string $title = '';

    public string $body = '';

    public string $priority = Ticket::PRIORITY_MEDIUM;

    public ?int $department_id = null;

    public ?int $order_id = null;

    public string $guest_name = '';

    public string $guest_email = '';

    public bool $showPreview = false;

    public ?string $appliedTemplate = null;

    public ?int $prefillOrderId = null;

    public function mount(?int $prefillOrderId = null): void
    {
        $this->prefillOrderId = $prefillOrderId;
        $first = $this->departments->first();
        $this->department_id = $first?->id;
        $this->appliedTemplate = $first?->prefill_template;
        $this->body = $first?->prefill_template ?? '';

        if ($prefillOrderId && $this->orders->contains('id', $prefillOrderId)) {
            $this->order_id = $prefillOrderId;
        }
    }

    #[Computed]
    public function isGuest(): bool
    {
        return ! auth()->check();
    }

    #[Computed]
    public function departments()
    {
        $query = TicketDepartment::query()->active()->ordered();

        if (! auth()->check()) {
            $query->acceptsGuests();
        }

        return $query->get();
    }

    #[Computed]
    public function orders()
    {
        if (! auth()->check()) {
            return collect();
        }

        return auth()->user()->orders()->with('package')->latest()->limit(50)->get();
    }

    public function updatedDepartmentId(): void
    {
        $department = $this->departments->firstWhere('id', (int) $this->department_id);
        $template = $department?->prefill_template ?? '';

        if ($this->body === '' || $this->body === $this->appliedTemplate) {
            $this->body = $template;
        }

        $this->appliedTemplate = $template;
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }

    public function submit(): mixed
    {
        if ($this->isGuest) {
            $ticket = Ticket::actions()->createAsGuest([
                'department_id' => $this->department_id,
                'title' => $this->title,
                'body' => $this->body,
                'priority' => $this->priority,
                'guest_name' => $this->guest_name,
                'guest_email' => $this->guest_email,
            ]);

            return $this->redirect(route('tickets.guest', $ticket->token), navigate: true);
        }

        $ticket = Ticket::actions()->createAsClient([
            'user_id' => auth()->id(),
            'department_id' => $this->department_id,
            'title' => $this->title,
            'body' => $this->body,
            'priority' => $this->priority,
            'order_id' => $this->order_id ?: null,
        ]);

        return $this->redirect(route('tickets.view', $ticket), navigate: true);
    }
}

?>

<div>
    <h1 class="mb-1 text-2xl font-bold text-gray-900 dark:text-white">New ticket</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Tell us what you need help with. Markdown is supported.</p>

    @if($this->departments->isEmpty())
        <x-theme::alert.warning text="No ticket departments are currently available."/>
    @else
        <form wire:submit="submit" class="space-y-4">
            @if($this->isGuest)
                <x-theme::alert.primary text="You are opening this ticket as a guest. We'll email you a private link to follow the conversation."/>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-theme::form.label for="guest_name" text="Username"/>
                        <x-theme::form.input id="guest_name" wire:model="guest_name" placeholder="Your name"/>
                        @error('guest_name') <x-theme::form.error :text="$message"/> @enderror
                    </div>
                    <div>
                        <x-theme::form.label for="guest_email" text="Email"/>
                        <x-theme::form.input id="guest_email" type="email" wire:model="guest_email" placeholder="you@example.com"/>
                        @error('guest_email') <x-theme::form.error :text="$message"/> @enderror
                    </div>
                </div>
            @endif

            <div>
                <x-theme::form.label for="title" text="Subject"/>
                <x-theme::form.input id="title" wire:model="title" placeholder="Briefly describe the issue"/>
                @error('title') <x-theme::form.error :text="$message"/> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-theme::form.label for="department_id" text="Department"/>
                    <select id="department_id" wire:model.live="department_id" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($this->departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id') <x-theme::form.error :text="$message"/> @enderror
                    @if($selected = $this->departments->firstWhere('id', (int) $this->department_id))
                        @if($selected->description)
                            <x-theme::form.description :text="$selected->description"/>
                        @endif
                    @endif
                </div>
                <div>
                    <x-theme::form.label for="priority" text="Priority"/>
                    <select id="priority" wire:model="priority" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    @error('priority') <x-theme::form.error :text="$message"/> @enderror
                </div>
            </div>

            @if(! $this->isGuest && $this->orders->isNotEmpty())
                <div>
                    <x-theme::form.label for="order_id" text="Related order (optional)"/>
                    <select id="order_id" wire:model="order_id" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">None</option>
                        @foreach($this->orders as $order)
                            <option value="{{ $order->id }}">#{{ $order->id }} — {{ $order->package->name ?? 'Order' }} ({{ ucfirst($order->status) }})</option>
                        @endforeach
                    </select>
                    @error('order_id') <x-theme::form.error :text="$message"/> @enderror
                </div>
            @endif

            <div>
                <x-theme::form.label for="body" text="Message"/>
                <x-tickets::markdown-composer
                    id="create-ticket-body"
                    wire:model="body"
                    placeholder="Describe the issue. You can use markdown."
                    :showPreview="$showPreview"
                    :previewHtml="\Extensions\Modules\Tickets\Models\Ticket::renderMarkdown($body)"
                    :rows="12"
                />
                @error('body') <x-theme::form.error :text="$message"/> @enderror
            </div>

            <div class="flex justify-end">
                <x-theme::button.primary type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Create ticket</span>
                    <span wire:loading>Creating…</span>
                </x-theme::button.primary>
            </div>
        </form>
    @endif
</div>
