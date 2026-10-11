<?php

use Livewire\Volt\Component;

new class extends Component
{

}
?>


<div class="grid grid-cols-1 gap-4 pt-2 dark:bg-gray-900 xl:grid-cols-3 xl:gap-4">

    <!-- Right Content -->
    <div class="min-w-0 xl:col-auto">
        <div class="mb-4 rounded-lg bg-white p-4 shadow dark:bg-gray-800 sm:p-6 xl:p-8">
            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-start">
                @if (auth()->user()->avatar !== null)
                    <img class="mb-4 h-20 w-20 rounded-lg sm:mb-0 xl:mb-4 2xl:mb-0" src="{{ auth()->user()->getAvatarUrl() }}" alt="user photo">
                @else
                    <div
                        class="relative mb-4 inline-flex h-28 w-28 items-center justify-center overflow-hidden rounded-full rounded-lg bg-gray-100 dark:bg-gray-600 sm:mb-0 xl:mb-4 2xl:mb-0">
                            <span class="font-medium text-gray-600 dark:text-gray-300">
                                {{ substr(auth()->user()->first_name, 0, 1) . substr(auth()->user()->last_name, 0, 1) }}
                            </span>
                    </div>
                @endif
                <div>
                    <h3 class="mb-1 text-2xl font-bold text-gray-900 dark:text-white">{{ auth()->user()->fullname }}</h3>
                    <div class="mb-4 text-base font-normal text-gray-500 dark:text-gray-400">
                        Member since {{ auth()->user()->created_at->format('M Y') }}
                    </div>
                </div>
            </div>

            @if(settings('allow_custom_avatars', false))
                <form action="#" method="POST" enctype="multipart/form-data">
                    @csrf
                    <label for="dropzone-file"
                           class="dark:hover:bg-bray-800 mb-4 mt-4 flex h-20 w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:hover:border-gray-500 dark:hover:bg-gray-600">
                        <div class="flex flex-col items-center justify-center pb-6 pt-5">
                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                Drag and drop your image here or
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG (MAX. 800x400px)</p>
                        </div>
                        <input id="dropzone-file" type="file" name="avatar" accept="image/*" required class="hidden">
                    </label>
                    <button type="submit"
                            class="bg-primary-700 hover:bg-primary-800 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 inline-flex items-center rounded-lg px-3 py-2 text-center text-sm font-medium text-white focus:ring-4">
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z">
                            </path>
                            <path d="M9 13h2v5a1 1 0 11-2 0v-5z"></path>
                        </svg>
                        Upload
                    </button>
                </form>
            @endif
        </div>
        <x-theme::action-card>
            <x-slot:title>
                Two Factor Authentication
            </x-slot:title>

            <x-slot:description>
                Enable two-factor authentication to add an extra layer of security to your account.
            </x-slot:description>

            <x-slot:action>
                @if (!auth()->user()->tfa_enabled)
                    <x-theme::button.primary href="{{ route('enable-2fa') }}" wire:navigate text="Enable"/>
                @else
                    <x-theme::button.danger href="{{ route('disable-2fa') }}" wire:navigate text="Disable"/>
                @endif
            </x-slot:action>
        </x-theme::action-card>

        @livewire(client_view_path(('account.livewire.view-sessions')))
    </div>
    <div class="min-w-0 xl:col-span-2">
        @livewire(client_view_path(('account.livewire.general-settings')))

        @livewire(client_view_path(('account.livewire.update-address')))

        @livewire(client_view_path(('account.livewire.update-email')))

        @livewire(client_view_path(('account.livewire.update-password')))
    </div>

</div>
