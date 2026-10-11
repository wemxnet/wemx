@php
    $stepMeta = [
        'requirements' => [
            'label' => 'Requirements',
            'title' => 'Check your server',
            'intro' => 'Let’s make sure your server is ready to run WemX.',
        ],
        'eula' => [
            'label' => 'License agreement',
            'title' => 'License agreement',
            'intro' => 'Please read and accept the End User License Agreement to continue.',
        ],
        'activation' => [
            'label' => 'License key',
            'title' => 'Activate your license',
            'intro' => 'Enter the license key from your WemX account.',
        ],
        'database' => [
            'label' => 'Database',
            'title' => 'Connect a database',
            'intro' => 'WemX stores your data here. The tables are created for you.',
        ],
        'setup' => [
            'label' => 'Store settings',
            'title' => 'Set up your store',
            'intro' => 'The basics. You can change all of these later in the admin area.',
        ],
        'create_user' => [
            'label' => 'Admin account',
            'title' => 'Create your admin account',
            'intro' => 'This is the primary administrator. It always has full access to WemX.',
        ],
        'complete' => [
            'label' => 'Finish',
            'title' => 'WemX is installed',
            'intro' => 'Nearly done. A few last things to check on your server.',
        ],
    ];

    $stepKeys = array_keys($stepMeta);
    $currentIndex = array_search($step, $stepKeys, true) ?: 0;
    $current = $stepMeta[$step] ?? $stepMeta['requirements'];
    $stepCount = count($stepKeys);
@endphp

<div class="wx-shell">
    <div class="wx-card">

        {{-- Sidebar --}}
        <aside class="wx-aside">
            <div class="wx-brand">
                <img src="/assets/common/img/wemx.png" alt="">
                <div>
                    <div class="wx-brand-name">WemX</div>
                    <div class="wx-brand-sub">Installation</div>
                </div>
                <button type="button" class="wx-theme-toggle" onclick="wxToggleTheme()" aria-label="Toggle dark mode" title="Toggle dark mode">
                    <i class="ti ti-moon"></i>
                    <i class="ti ti-sun"></i>
                </button>
            </div>

            <ol class="wx-steps">
                @foreach($stepMeta as $key => $meta)
                    @php $index = $loop->index; @endphp
                    <li @class(['is-active' => $index === $currentIndex, 'is-done' => $index < $currentIndex]) @if($index === $currentIndex) aria-current="step" @endif>
                        <span class="wx-step-dot">
                            @if($index < $currentIndex)
                                <i class="ti ti-check"></i>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        {{ $meta['label'] }}
                    </li>
                @endforeach
            </ol>

            <div class="wx-aside-foot">
                <a href="https://v3.wemx.net/docs" target="_blank" rel="noopener noreferrer"><i class="ti ti-book"></i> Installation guide</a>
                <a href="https://wemx.net/discord" target="_blank" rel="noopener noreferrer"><i class="ti ti-brand-discord"></i> Get help on Discord</a>
                <div class="wx-version">Version {{ config('app.version') }}</div>
            </div>
        </aside>

        {{-- Main --}}
        <main class="wx-main">
            <div class="wx-progress">
                <div class="wx-progress-label">
                    <span>Step {{ $currentIndex + 1 }} of {{ $stepCount }}</span>
                    <span>{{ $current['label'] }}</span>
                </div>
                <div class="wx-progress-bar"><span style="width: {{ round(($currentIndex + 1) / $stepCount * 100) }}%"></span></div>
            </div>

            <header class="wx-head">
                <div class="wx-eyebrow">Step {{ $currentIndex + 1 }} of {{ $stepCount }}</div>
                <h1>{{ $current['title'] }}</h1>
                <p>{{ $current['intro'] }}</p>
            </header>

            {{-- ───────────── Requirements ───────────── --}}
            @if($step === 'requirements')
                @php
                    $summary = $this->requirementSummary;
                    $groups = $this->requirementGroups;
                    $failedCount = $summary['total'] - $summary['passed'];
                    $failedExtensions = collect($groups['extensions']['items'])->where('passed', false)->pluck('label');
                    $failedPaths = collect($groups['paths']['items'])->where('passed', false)->pluck('label');
                    $phpFailed = ! $groups['php']['items'][0]['passed'];
                    $extensionPackages = $failedExtensions
                        ->map(fn ($extension) => in_array($extension, ['openssl', 'pdo', 'tokenizer', 'fileinfo'], true) ? 'php8.3-common' : 'php8.3-'.$extension)
                        ->unique()
                        ->implode(' ');
                @endphp

                <div class="wx-body" x-data="{ open: {{ $areRequirementsMet ? 'false' : 'true' }} }">
                    @if($areRequirementsMet)
                        <div class="wx-banner is-success">
                            <i class="ti ti-circle-check"></i>
                            <div>
                                <strong>Your server is ready</strong>
                                <div class="wx-banner-detail">All {{ $summary['total'] }} checks passed.</div>
                            </div>
                            <button type="button" class="wx-link-btn ms-auto" @click="open = !open">
                                <span x-text="open ? 'Hide details' : 'Show details'">Show details</span>
                                <i class="ti" :class="open ? 'ti-chevron-up' : 'ti-chevron-down'"></i>
                            </button>
                        </div>
                    @else
                        <div class="wx-banner is-danger">
                            <i class="ti ti-alert-circle"></i>
                            <div>
                                <strong>{{ $failedCount }} {{ Str::plural('check', $failedCount) }} need{{ $failedCount === 1 ? 's' : '' }} attention</strong>
                                <div class="wx-banner-detail">Fix the items marked below, then check again.</div>
                            </div>
                        </div>
                    @endif

                    <div x-show="open" x-cloak class="mt-4">
                        @foreach($groups as $groupKey => $group)
                            <div class="mb-4">
                                <h2 class="wx-section-title">{{ $group['title'] }}</h2>
                                <div class="wx-checks">
                                    @foreach($group['items'] as $item)
                                        <div @class(['wx-check', 'is-wide' => $groupKey === 'php', 'is-pass' => $item['passed'], 'is-fail' => ! $item['passed']]) @if($groupKey === 'paths') title="{{ base_path($item['label']) }}" @endif>
                                            <i @class(['ti', 'ti-circle-check' => $item['passed'], 'ti-circle-x' => ! $item['passed']])></i>
                                            @if($groupKey === 'php')
                                                <span>{{ $item['label'] }}</span>
                                                <small>{{ $item['detail'] }}</small>
                                            @else
                                                <code>{{ $item['label'] }}</code>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @unless($areRequirementsMet)
                        <h2 class="wx-section-title mt-2">How to fix</h2>
                        <div class="d-flex flex-column gap-3">
                            @if($phpFailed)
                                <div class="text-secondary small">
                                    WemX needs PHP {{ $minPhpVersion }} or newer. The
                                    <a href="https://v3.wemx.net/docs" target="_blank" rel="noopener noreferrer">automatic install script</a>
                                    sets this up for you, or ask your host to upgrade PHP.
                                </div>
                            @endif

                            @if($failedExtensions->isNotEmpty())
                                <div>
                                    <div class="small">Install the missing PHP {{ Str::plural('extension', $failedExtensions->count()) }}, then restart PHP or your web server:</div>
                                    <div class="wx-code"><span>sudo apt install {{ $extensionPackages }}</span><button type="button" class="wx-copy" onclick="wxCopy(this)" aria-label="Copy"><i class="ti ti-copy"></i></button></div>
                                </div>
                            @endif

                            @if($failedPaths->isNotEmpty())
                                <div>
                                    <div class="small">Give the web server write access to these folders. Replace <code>www-data</code> if your web server runs as a different user.</div>
                                    <div class="wx-code"><span>cd {{ base_path() }}
chmod -R 755 storage/* bootstrap/cache/
chown -R www-data:www-data {{ base_path() }}</span><button type="button" class="wx-copy" onclick="wxCopy(this)" aria-label="Copy"><i class="ti ti-copy"></i></button></div>
                                </div>
                            @endif
                        </div>
                    @endunless
                </div>

                <footer class="wx-actions">
                    @if($areRequirementsMet)
                        <button type="button" class="btn btn-primary wx-primary" wire:click="changeStep('eula')" wire:loading.attr="disabled" wire:target="changeStep">
                            <span wire:loading wire:target="changeStep" class="spinner-border spinner-border-sm me-2"></span>
                            Continue
                        </button>
                    @else
                        <button type="button" class="btn btn-primary wx-primary" wire:click="recheckRequirements" wire:loading.attr="disabled" wire:target="recheckRequirements">
                            <span wire:loading wire:target="recheckRequirements" class="spinner-border spinner-border-sm me-2"></span>
                            <i class="ti ti-refresh me-1" wire:loading.remove wire:target="recheckRequirements"></i>
                            Check again
                        </button>
                    @endif
                </footer>

            {{-- ───────────── EULA ───────────── --}}
            @elseif($step === 'eula')
                <div x-data="{ accepted: false }" style="display: contents">
                    <div class="wx-body">
                        <div class="wx-eula markdown" tabindex="0">
                            {!! $eulaHtml !!}
                        </div>

                        <label class="wx-accept">
                            <input type="checkbox" class="form-check-input" x-model="accepted">
                            <span>I have read and agree to the End User License Agreement</span>
                        </label>
                    </div>

                    <footer class="wx-actions">
                        <button type="button" class="btn btn-ghost-secondary" wire:click="changeStep('requirements')">Back</button>
                        <button type="button" class="btn btn-primary wx-primary" wire:click="changeStep('activation')" :disabled="!accepted" disabled>
                            <span wire:loading wire:target="changeStep('activation')" class="spinner-border spinner-border-sm me-2"></span>
                            Accept &amp; continue
                        </button>
                    </footer>
                </div>

            {{-- ───────────── License ───────────── --}}
            @elseif($step === 'activation')
                <div class="wx-body wx-form">
                    <label class="form-label" for="license_key">License key</label>
                    <div class="input-group">
                        <input type="text"
                               id="license_key"
                               wire:model="license_key"
                               wire:keydown.enter="checkLicense"
                               @class(['form-control', 'wx-license-input', 'is-invalid' => $errors->has('license_key')])
                               placeholder="WMX-XXXX-XXXX-XXXX-XXXX"
                               autocomplete="off"
                               spellcheck="false"
                               autofocus>
                        <button type="button" class="btn" wire:click="checkLicense" wire:loading.attr="disabled" wire:target="checkLicense">
                            <span wire:loading wire:target="checkLicense" class="spinner-border spinner-border-sm me-2"></span>
                            Verify
                        </button>
                    </div>
                    @error('license_key')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @else
                        <div class="form-hint">
                            Don’t have a key yet? Get a free license at
                            <a href="https://app.wemx.net" target="_blank" rel="noopener noreferrer">app.wemx.net</a>.
                        </div>
                    @enderror

                    @if($licenseCheckedSuccessfully && $isLicenseActive && $licenseData)
                        <div class="wx-banner is-success mt-4">
                            <i class="ti ti-shield-check"></i>
                            <div>
                                <strong>License verified</strong>
                                <div class="wx-banner-detail">This license is active for {{ request()->getHost() }}.</div>
                            </div>
                        </div>

                        <dl class="wx-summary mb-0">
                            <div>
                                <dt>Plan</dt>
                                <dd>{{ $licenseData['plan_name'] ?? 'Not available' }}@if(! empty($licenseData['billing_cycle']) && $licenseData['billing_cycle'] !== 'Not Available') <span class="text-secondary fw-normal">· {{ $licenseData['billing_cycle'] }}</span>@endif</dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd>{{ ucfirst($licenseData['status'] ?? 'active') }}</dd>
                            </div>
                            <div>
                                <dt>Licensed to</dt>
                                <dd>{{ $licenseData['email'] ?? 'Not available' }}</dd>
                            </div>
                            <div>
                                <dt>Expires</dt>
                                <dd>{{ $licenseData['expires_at'] ?? 'Never' }}</dd>
                            </div>
                        </dl>
                    @endif
                </div>

                <footer class="wx-actions">
                    <button type="button" class="btn btn-ghost-secondary" wire:click="changeStep('eula')">Back</button>
                    <button type="button" class="btn btn-primary wx-primary" wire:click="checkLicenseAndContinue" wire:loading.attr="disabled" wire:target="checkLicenseAndContinue">
                        <span wire:loading wire:target="checkLicenseAndContinue" class="spinner-border spinner-border-sm me-2"></span>
                        Continue
                    </button>
                </footer>

            {{-- ───────────── Database ───────────── --}}
            @elseif($step === 'database')
                <div class="wx-body wx-form">
                    @if($isDatabaseDeployed)
                        <div class="wx-banner is-success">
                            <i class="ti ti-database-check"></i>
                            <div>
                                <strong>Database already connected</strong>
                                <div class="wx-banner-detail">Your database is set up and reachable, so there’s nothing to do here.</div>
                            </div>
                        </div>
                    @else
                        @php
                            $drivers = [];
                            if ($isSqliteAvailable) {
                                $drivers['sqlite'] = ['SQLite', 'Simplest, single file'];
                            }
                            $drivers['mysql'] = ['MySQL / MariaDB', 'MySQL 8+, MariaDB 10.3+'];
                            $drivers['pgsql'] = ['PostgreSQL', 'Host and credentials'];
                            $drivers['supabase'] = ['PostgreSQL URL', 'Supabase, Neon and others'];
                        @endphp

                        <div class="form-label">Database type</div>
                        <div class="wx-drivers" role="radiogroup">
                            @foreach($drivers as $value => [$name, $note])
                                <label @class(['wx-driver', 'is-selected' => $database_connection === $value])>
                                    <input type="radio" name="database_connection" value="{{ $value }}" wire:model.live="database_connection">
                                    <span class="wx-driver-name">{{ $name }}</span>
                                    <span class="wx-driver-note">{{ $note }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            @if($database_connection === 'sqlite')
                                <label class="form-label" for="database_path">Database file</label>
                                <input type="text" id="database_path" wire:model="database_path" @class(['form-control', 'is-invalid' => $errors->has('database_path')]) placeholder="database/database.sqlite">
                                @error('database_path')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-hint">The default is recommended. Relative paths start from the WemX folder.</div>
                                @enderror
                            @elseif($database_connection === 'supabase')
                                <label class="form-label" for="database_url">Connection URL</label>
                                <input type="text" id="database_url" wire:model="database_url" @class(['form-control', 'font-monospace', 'is-invalid' => $errors->has('database_url')]) placeholder="postgres://user:password@host:5432/database?sslmode=require" autocomplete="off" spellcheck="false">
                                @error('database_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-hint">Paste the full PostgreSQL connection string from your provider’s dashboard.</div>
                                @enderror
                            @else
                                <div class="row g-3">
                                    <div class="col-8 col-sm-9">
                                        <label class="form-label" for="database_host">Host</label>
                                        <input type="text" id="database_host" wire:model="database_host" @class(['form-control', 'is-invalid' => $errors->has('database_host')]) placeholder="127.0.0.1">
                                        @error('database_host') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-4 col-sm-3">
                                        <label class="form-label" for="database_port">Port</label>
                                        <input type="text" id="database_port" inputmode="numeric" wire:model="database_port" @class(['form-control', 'is-invalid' => $errors->has('database_port')]) placeholder="{{ $database_connection === 'pgsql' ? '5432' : '3306' }}">
                                        @error('database_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="database_name">Database name</label>
                                        <input type="text" id="database_name" wire:model="database_name" @class(['form-control', 'is-invalid' => $errors->has('database_name')]) placeholder="wemx">
                                        @error('database_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label" for="database_username">Username</label>
                                        <input type="text" id="database_username" wire:model="database_username" @class(['form-control', 'is-invalid' => $errors->has('database_username')]) placeholder="wemx_user" autocomplete="off">
                                        @error('database_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label" for="database_password">Password</label>
                                        <input type="password" id="database_password" wire:model="database_password" @class(['form-control', 'is-invalid' => $errors->has('database_password')]) autocomplete="new-password">
                                        @error('database_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="form-hint mt-2">
                                    Need to create a database first?
                                    <a href="https://v3.wemx.net/docs" target="_blank" rel="noopener noreferrer">See the database setup guide</a>.
                                </div>
                            @endif
                        </div>

                        @error('database_connection')
                            <div class="wx-banner is-danger mt-4">
                                <i class="ti ti-alert-circle"></i>
                                <div>
                                    <strong>{{ $message }}</strong>
                                    @if($database_error)
                                        <div class="wx-banner-detail">{{ $database_error }}</div>
                                    @endif
                                </div>
                            </div>
                        @else
                            @if($database_test_message)
                                <div @class(['wx-banner mt-4', 'is-success' => $database_test_success, 'is-danger' => ! $database_test_success])>
                                    <i @class(['ti', 'ti-plug-connected' => $database_test_success, 'ti-plug-connected-x' => ! $database_test_success])></i>
                                    <div>
                                        <strong>{{ $database_test_success ? 'Connected' : 'Couldn’t connect' }}</strong>
                                        <div class="wx-banner-detail">{{ $database_test_success ? 'The connection works.' : ($database_error ?: $database_test_message) }}</div>
                                    </div>
                                </div>
                            @endif
                        @enderror

                        @if($has_existing_database_tables)
                            <div class="wx-banner is-warning mt-3">
                                <i class="ti ti-alert-triangle"></i>
                                <div>
                                    <strong>This database isn’t empty</strong>
                                    <div class="wx-banner-detail">It already has {{ $existing_database_tables_count }} {{ Str::plural('table', $existing_database_tables_count) }}. We recommend an empty database for a new installation.</div>
                                    <label class="form-check mt-2 mb-0">
                                        <input class="form-check-input" type="checkbox" wire:model="confirm_migrate_existing_database">
                                        <span class="form-check-label">Use this database anyway</span>
                                    </label>
                                    @error('confirm_migrate_existing_database')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <div class="wx-busy" wire:loading.flex wire:target="checkDatabaseAndContinue">
                            <span class="spinner-border spinner-border-sm"></span>
                            Creating database tables. This can take a minute, please keep this page open.
                        </div>
                    @endif
                </div>

                <footer class="wx-actions">
                    <button type="button" class="btn btn-ghost-secondary" wire:click="changeStep('activation')">Back</button>
                    @unless($isDatabaseDeployed)
                        <button type="button" class="btn ms-auto" wire:click="testDatabaseConnection" wire:loading.attr="disabled" wire:target="testDatabaseConnection, checkDatabaseAndContinue">
                            <span wire:loading wire:target="testDatabaseConnection" class="spinner-border spinner-border-sm me-2"></span>
                            Test connection
                        </button>
                    @endunless
                    <button type="button" @class(['btn btn-primary', 'wx-primary' => $isDatabaseDeployed]) wire:click="checkDatabaseAndContinue" wire:loading.attr="disabled" wire:target="testDatabaseConnection, checkDatabaseAndContinue">
                        <span wire:loading wire:target="checkDatabaseAndContinue" class="spinner-border spinner-border-sm me-2"></span>
                        Continue
                    </button>
                </footer>

            {{-- ───────────── Store settings ───────────── --}}
            @elseif($step === 'setup')
                <form style="display: contents" wire:submit="saveAppSettingsAndContinue">
                    <div class="wx-body wx-form">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="app_name">Store name</label>
                                <input type="text" id="app_name" wire:model="app_name" @class(['form-control', 'is-invalid' => $errors->has('app_name')]) placeholder="My Hosting Company">
                                @error('app_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-hint">Shown to your customers across the site and in emails.</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="currency">Currency</label>
                                <select id="currency" wire:model="currency" @class(['form-select', 'is-invalid' => $errors->has('currency')])>
                                    @foreach(\App\Models\Currency::pluck('display_name', 'currency') as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="language">Language</label>
                                <select id="language" wire:model="language" @class(['form-select', 'is-invalid' => $errors->has('language')])>
                                    @foreach($languages as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('language') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="timezone">Timezone</label>
                                <select id="timezone"
                                        wire:model="timezone"
                                        @class(['form-select', 'is-invalid' => $errors->has('timezone')])
                                        x-data
                                        x-init="
                                            if ($wire.timezone === 'UTC') {
                                                const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
                                                if (detected && [...$el.options].some(o => o.value === detected)) { $wire.timezone = detected; }
                                            }
                                        ">
                                    @foreach(timezone_identifiers_list() as $zone)
                                        <option value="{{ $zone }}">{{ str_replace('_', ' ', $zone) }}</option>
                                    @endforeach
                                </select>
                                @error('timezone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-hint">Used for dates, invoices and scheduled tasks. We’ve picked your browser’s timezone.</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <footer class="wx-actions">
                        <button type="button" class="btn btn-ghost-secondary" wire:click="changeStep('database')">Back</button>
                        <button type="submit" class="btn btn-primary wx-primary" wire:loading.attr="disabled" wire:target="saveAppSettingsAndContinue">
                            <span wire:loading wire:target="saveAppSettingsAndContinue" class="spinner-border spinner-border-sm me-2"></span>
                            Continue
                        </button>
                    </footer>
                </form>

            {{-- ───────────── Admin account ───────────── --}}
            @elseif($step === 'create_user')
                <form style="display: contents" wire:submit="createAdminUserAndContinue">
                    <div class="wx-body wx-form">
                        @if($hasAdminUser)
                            <div class="wx-banner is-success">
                                <i class="ti ti-user-check"></i>
                                <div>
                                    <strong>An administrator account already exists</strong>
                                    <div class="wx-banner-detail">You can sign in with it once the installation is finished.</div>
                                </div>
                            </div>
                        @else
                            @php
                                $strength = (int) round($this->passwordStrengthScore / 25);
                                $strengthLabel = ['', 'Weak', 'Fair', 'Good', 'Strong'][$strength] ?? '';
                            @endphp
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="first_name">First name</label>
                                    <input type="text" id="first_name" wire:model="first_name" @class(['form-control', 'is-invalid' => $errors->has('first_name')]) autocomplete="given-name">
                                    @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="last_name">Last name</label>
                                    <input type="text" id="last_name" wire:model="last_name" @class(['form-control', 'is-invalid' => $errors->has('last_name')]) autocomplete="family-name">
                                    @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="username">Username</label>
                                    <input type="text" id="username" wire:model.blur="username" @class(['form-control', 'is-invalid' => $errors->has('username')]) autocomplete="username">
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="form-hint">At least 5 letters or numbers.</div>
                                    @enderror
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="email">Email</label>
                                    <input type="email" id="email" wire:model.blur="email" @class(['form-control', 'is-invalid' => $errors->has('email')]) autocomplete="email">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label" for="password">Password</label>
                                        <button type="button" class="wx-link-btn mb-2" wire:click="generateSecurePassword">
                                            <i class="ti ti-wand"></i> Generate a strong password
                                        </button>
                                    </div>
                                    <div class="input-group">
                                        <input type="{{ $show_password ? 'text' : 'password' }}" id="password" wire:model.live.debounce.400ms="password" @class(['form-control', 'is-invalid' => $errors->has('password')]) autocomplete="new-password">
                                        <button class="btn" type="button" wire:click="$toggle('show_password')" aria-label="{{ $show_password ? 'Hide password' : 'Show password' }}">
                                            <i @class(['ti', 'ti-eye' => ! $show_password, 'ti-eye-off' => $show_password])></i>
                                        </button>
                                    </div>
                                    @if($password !== '')
                                        <div class="wx-strength s-{{ $strength }}" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                                    @endif
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="form-hint">
                                            @if($password !== '')<strong>{{ $strengthLabel }}.</strong>@endif
                                            At least 8 characters with upper and lower case letters, a number and a symbol.
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="password_confirmation">Confirm password</label>
                                    <div class="input-group">
                                        <input type="{{ $show_password_confirmation ? 'text' : 'password' }}" id="password_confirmation" wire:model.live.debounce.400ms="password_confirmation" @class(['form-control', 'is-invalid' => $errors->has('password_confirmation')]) autocomplete="new-password">
                                        <button class="btn" type="button" wire:click="$toggle('show_password_confirmation')" aria-label="{{ $show_password_confirmation ? 'Hide password' : 'Show password' }}">
                                            <i @class(['ti', 'ti-eye' => ! $show_password_confirmation, 'ti-eye-off' => $show_password_confirmation])></i>
                                        </button>
                                    </div>
                                    @error('password_confirmation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        @endif
                    </div>

                    <footer class="wx-actions">
                        <button type="button" class="btn btn-ghost-secondary" wire:click="changeStep('setup')">Back</button>
                        <button type="submit" class="btn btn-primary wx-primary" wire:loading.attr="disabled" wire:target="createAdminUserAndContinue">
                            <span wire:loading wire:target="createAdminUserAndContinue" class="spinner-border spinner-border-sm me-2"></span>
                            {{ $hasAdminUser ? 'Continue' : 'Create account' }}
                        </button>
                    </footer>
                </form>

            {{-- ───────────── Complete ───────────── --}}
            @elseif($step === 'complete')
                <div class="wx-body">
                    <div class="wx-banner is-info mb-4">
                        <i class="ti ti-info-circle"></i>
                        <div>
                            <strong>Used the automatic install script?</strong>
                            <div class="wx-banner-detail">The scheduler and queue worker are already set up for you. Skip to step 3.</div>
                        </div>
                    </div>

                    <ol class="wx-next">
                        <li>
                            <span class="wx-next-num">1</span>
                            <div class="wx-next-body">
                                <strong>Run the scheduler every minute</strong>
                                <p>Handles renewals, invoices and suspensions. Run <code>sudo crontab -e</code> and add this line:</p>
                                <div class="wx-code"><span>* * * * * php {{ base_path('artisan') }} schedule:run >> /dev/null 2>&1</span><button type="button" class="wx-copy" onclick="wxCopy(this)" aria-label="Copy"><i class="ti ti-copy"></i></button></div>
                            </div>
                        </li>
                        <li>
                            <span class="wx-next-num">2</span>
                            <div class="wx-next-body">
                                <strong>Keep a queue worker running</strong>
                                <p>Sends emails and provisions services in the background. Run it as a service, as shown in the <a href="https://v3.wemx.net/docs" target="_blank" rel="noopener noreferrer">installation guide</a>:</p>
                                <div class="wx-code"><span>php {{ base_path('artisan') }} queue:work</span><button type="button" class="wx-copy" onclick="wxCopy(this)" aria-label="Copy"><i class="ti ti-copy"></i></button></div>
                            </div>
                        </li>
                        <li>
                            <span class="wx-next-num">3</span>
                            <div class="wx-next-body">
                                <strong>Set up your store</strong>
                                <p>Sign in and work through the <a href="https://v3.wemx.net/docs/first-steps" target="_blank" rel="noopener noreferrer">first steps checklist</a>: emails, payment gateways and your first package.</p>
                            </div>
                        </li>
                    </ol>
                </div>

                <footer class="wx-actions">
                    <button type="button" class="btn btn-ghost-secondary" wire:click="changeStep('create_user')">Back</button>
                    <button type="button" class="btn btn-primary wx-primary" wire:click="finish" wire:loading.attr="disabled" wire:target="finish">
                        <span wire:loading wire:target="finish" class="spinner-border spinner-border-sm me-2"></span>
                        Finish and open WemX
                        <i class="ti ti-arrow-right ms-1" wire:loading.remove wire:target="finish"></i>
                    </button>
                </footer>
            @endif
        </main>
    </div>
</div>
