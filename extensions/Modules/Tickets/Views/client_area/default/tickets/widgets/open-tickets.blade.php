@if(auth()->check())
    @php
        $openCount = \Extensions\Modules\Tickets\Models\Ticket::query()
            ->forUser(auth()->user())
            ->open()
            ->count();
    @endphp
    @if($openCount > 0)
        <div class="mb-4">
            <x-theme::alert.primary class="flex items-center justify-between gap-3">
                <span>You have {{ $openCount }} open support {{ \Illuminate\Support\Str::plural('ticket', $openCount) }}.</span>
                <x-theme::button.primary href="{{ route('tickets.index') }}" wire:navigate text="View tickets"/>
            </x-theme::alert.primary>
        </div>
    @endif
@endif
