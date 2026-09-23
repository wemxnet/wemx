<?php

use App\Models\User;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $resourceId;

    public string $member_username = '';

    public string $member_role = 'developer';

    public function mount(): void
    {
        abort_unless($this->resource->userCan(auth()->user(), TeamRole::Support), 403);
    }

    #[Computed]
    public function resource(): MarketplaceResource
    {
        return MarketplaceResource::query()
            ->with('teamMembers.user')
            ->findOrFail($this->resourceId);
    }

    public function addMember(): void
    {
        $user = User::query()->where('username', $this->member_username)->orWhere('email', $this->member_username)->first();

        if (! $user) {
            $this->addError('member_username', 'No user matches that username or email.');

            return;
        }

        MarketplaceResource::actions()->addTeamMember([
            'actor_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'user_id' => $user->id,
            'role' => $this->member_role,
        ]);

        $this->member_username = '';
        unset($this->resource);
        session()->flash('success', 'Team member added.');
    }

    public function removeMember(int $userId): void
    {
        MarketplaceResource::actions()->removeTeamMember([
            'actor_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'user_id' => $userId,
        ]);

        unset($this->resource);
    }
}

?>

@php
    $resource = $this->resource;
    $canTeam = $resource->userCan(auth()->user(), TeamRole::Owner);
@endphp

<div>
    @if(session('success'))
        <x-theme::alert.success :text="session('success')" />
    @endif

    <x-theme::card class="mb-4">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Team</h2>

        <ul class="mb-4 space-y-2 text-sm">
            @foreach($resource->teamMembers as $member)
                <li wire:key="member-{{ $member->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2 dark:border-gray-700">
                    <span>{{ $member->user?->username }} <span class="text-gray-500">· {{ $member->role->label() }}</span></span>
                    @if($canTeam && $member->role !== TeamRole::Owner)
                        <button type="button" class="text-red-600 hover:underline" wire:click="removeMember({{ $member->user_id }})" wire:confirm="Remove this team member?">Remove</button>
                    @endif
                </li>
            @endforeach
        </ul>

        @if($canTeam)
            <form wire:submit="addMember" class="grid gap-3 sm:grid-cols-[1fr_10rem_auto]">
                <x-theme::form.input wire:model="member_username" placeholder="Username or email"/>
                <select wire:model="member_role" class="rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="manager">Manager</option>
                    <option value="developer">Developer</option>
                    <option value="support">Support</option>
                </select>
                <x-theme::button.primary type="submit">Add</x-theme::button.primary>
            </form>
            @error('member_username') <x-theme::form.error :text="$message"/> @enderror
            @error('user_id') <x-theme::form.error :text="$message"/> @enderror
        @endif
    </x-theme::card>
</div>
