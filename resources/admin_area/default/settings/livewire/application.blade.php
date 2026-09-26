<?php

use App\Mail\EmailTheme;
use App\Models\Setting;
use Livewire\Volt\Component;

new class extends Component
{
    public $languages = [
        'en' => 'English',
    ];

    public $app_name;

    public $company_address;

    public $language = 'en';

    public $timezone = 'UTC';

    public array $timezones = [];

    public $currency = 'USD';

    public string $email_theme = 'default';

    public array $emailThemes = [];

    public $lastModifiedTimestamps;

    public function mount()
    {
        $this->app_name = settings('app_name', 'Application');
        $this->company_address = settings('company_address');
        $this->language = settings('language', 'en');
        $this->timezone = settings('timezone', 'UTC');
        $this->currency = settings('currency', 'USD');
        $this->email_theme = EmailTheme::default()->slug;
        $this->emailThemes = EmailTheme::options();
        $this->lastModifiedTimestamps = Setting::whereIn('key', ['language', 'currency', 'email_theme'])->pluck('updated_at', 'key');

        $timezones = \DateTimeZone::listIdentifiers();
        $this->timezones = array_combine($timezones, $timezones);
    }

    public function saveChanges()
    {
        Setting::actions()->updateApplicationSettingsAsAdmin([
            'app_name' => $this->app_name,
            'company_address' => $this->company_address,
            'language' => $this->language,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'email_theme' => $this->email_theme,
        ]);

        $this->dispatch('alert', 'success', 'Settings saved successfully.');
    }
}

?>

<div>
    <x-admin::settings.page-form title="Application">
            <div class="mb-4">
                <h3 class="card-title">Application Name</h3>
                <p class="card-subtitle">
                    Your application's name displayed to users.
                </p>
                <div class="row g-2">
                    <div class="col">
                        <x-admin::form.input wire:model="app_name" label="Application Name" name="application_name" placeholder="Application Name" />
                    </div>
                    @error('app_name')
                        <x-admin::form.error :message="$message" />
                    @enderror
                </div>
            </div>
            <div class="mb-4">
                <h3 class="card-title">Company Address</h3>
                <p class="card-subtitle">
                    Your company's address that's displayed on invoices.
                </p>
                <div class="row g-2">
                    <div class="col">
                        <x-admin::form.textarea wire:model="company_address" name="company_address" placeholder="Company Address" />
                    </div>
                    @error('company_address')
                        <x-admin::form.error :message="$message" />
                    @enderror
                </div>
            </div>
            <div class="mb-4">
                <h3 class="card-title">Default Language</h3>
                <p class="card-subtitle">
                    The default language of the application.
                </p>
                <div class="row g-2">
                    <div class="col">
                        <x-admin::form.select wire:model="language" id="language" value="{{ settings('language', 'en') }}" :options="$languages" searchable />
                    </div>
                    @error('language')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">
                            Last modified {{ isset($lastModifiedTimestamps['language']) ? $lastModifiedTimestamps['language']->diffForHumans() : 'Never' }}
                        </small>
                    @enderror
                </div>
            </div>
            <div class="mb-4">
                <h3 class="card-title">Application Timezone</h3>
                <p class="card-subtitle">
                    The default timezone for the application. You can view timezones here <a href="https://php.net/manual/en/timezones.php" target="_blank" rel="noopener noreferrer">https://php.net/manual/en/timezones.php</a>.
                </p>
                <div class="row g-2">
                    <div class="col">
                        <x-admin::form.select wire:model="timezone" id="timezone" value="{{ settings('timezone', 'UTC') }}" :options="$timezones" />
                    </div>
                    @error('timezone')
                        <x-admin::form.error :message="$message" />
                    @enderror
                </div>
            </div>
            <div class="mb-4">
                <h3 class="card-title">Default Currency</h3>
                <p class="card-subtitle">
                    The default currency for the application. You can manage currencies in the <a href="{{ route('admin.currencies.index') }}">currencies</a> section.
                </p>
                <div class="row g-2">
                    <div class="col">
                        <x-admin::form.select wire:model="currency" value="{{ settings('currency', 'USD') }}" id="currency" :options="\App\Models\Currency::pluck('display_name', 'currency')" searchable />
                    </div>
                    @error('currency')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">
                            Last modified {{ isset($lastModifiedTimestamps['currency']) ? $lastModifiedTimestamps['currency']->diffForHumans() : 'Never' }}
                        </small>
                    @enderror
                </div>
            </div>
            <div class="mb-4">
                <h3 class="card-title">Default Email Theme</h3>
                <p class="card-subtitle">
                    The layout used for customer emails when a message does not choose its own theme. Installed email themes are listed here.
                </p>
                <div class="row g-2">
                    <div class="col">
                        <x-admin::form.select wire:model="email_theme" id="email_theme" value="{{ settings('email_theme', 'default') }}" :options="$emailThemes" />
                    </div>
                    @error('email_theme')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">
                            {{ $emailThemes[$email_theme] ?? 'Default' }}
                            · Last modified {{ isset($lastModifiedTimestamps['email_theme']) ? $lastModifiedTimestamps['email_theme']->diffForHumans() : 'Never' }}
                        </small>
                    @enderror
                </div>
            </div>
    </x-admin::settings.page-form>
</div>
