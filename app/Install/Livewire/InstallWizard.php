<?php

namespace App\Install\Livewire;

use App\Helpers\EnvironmentWriter;
use App\Models\Setting;
use App\Models\User;
use App\Rules\NotReservedUsername;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class InstallWizard extends Component
{
    #[Url]
    public ?string $step = 'requirements';

    public $license_key = '';

    public $licenseData = [];

    public array $steps = [
        'requirements',
        'eula',
        'activation',
        'database',
        'setup',
        'create_user',
        'complete',
    ];

    public bool $isDatabaseDeployed = false;

    public bool $isLicenseActive = false;

    public bool $licenseCheckedSuccessfully = false;

    public bool $isSqliteAvailable = false;

    public bool $hasAdminUser = false;

    public $minPhpVersion = '8.3';

    public array $requiredExtensions = [
        'openssl',
        'pdo',
        'mbstring',
        'tokenizer',
        'fileinfo',
        'curl',
    ];

    public array $writablePaths = [
        'storage/app/',
        'storage/framework/',
        'storage/logs/',
        'bootstrap/cache/',
    ];

    public $languages = [
        'en' => 'English',
    ];

    public bool $areRequirementsMet = true;

    public string $app_name = 'Application';

    public string $language = 'en';

    public string $currency = 'USD';

    public string $timezone = 'UTC';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $username = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $database_connection = 'mysql';

    public string $database_host = '127.0.0.1';

    public string $database_port = '3306';

    public string $database_name = '';

    public string $database_username = '';

    public string $database_password = '';

    public string $database_path = '';

    public string $database_url = '';

    public ?string $database_error = null;

    public ?string $database_test_message = null;

    public bool $database_test_success = false;

    public int $existing_database_tables_count = 0;

    public bool $has_existing_database_tables = false;

    public bool $confirm_migrate_existing_database = false;

    public bool $show_password = false;

    public bool $show_password_confirmation = false;

    public string $eulaHtml = '';

    public function mount()
    {
        $eulaPath = base_path('EULA.md');
        $this->eulaHtml = File::isFile($eulaPath)
            ? Str::markdown(File::get($eulaPath))
            : '<p class="text-secondary">The End User License Agreement file (EULA.md) could not be found in the project root.</p>';

        // check if all requirements are met
        $this->areRequirementsMet();

        $this->license_key = config('app.license_key');
        $this->validateLicenseKey();
        $this->isLicenseActive = $this->isLicenseActive || session('installer.license_active', false);

        $this->isSqliteAvailable = extension_loaded('pdo_sqlite');
        $this->database_connection = $this->isSqliteAvailable ? 'sqlite' : 'mysql';
        $this->database_host = (string) env('DB_HOST', '127.0.0.1');
        $this->database_port = (string) env('DB_PORT', '3306');
        $this->database_name = (string) env('DB_DATABASE', 'laravel');
        $this->database_username = (string) env('DB_USERNAME', 'root');
        $this->database_password = (string) env('DB_PASSWORD', '');
        $this->database_path = 'database/database.sqlite';
        $this->database_url = (string) env('DB_URL', '');

        try {
            DB::connection()->getPdo();
            $this->isDatabaseDeployed = true;
            $this->database_test_success = true;
            $this->database_test_message = 'Database is already configured and reachable. You can skip this step.';
        } catch (\Exception $e) {
            $this->isDatabaseDeployed = false;
        }

        if ($this->isDatabaseDeployed) {
            // check if users table exists and if there is any user in the database
            if (DB::getSchemaBuilder()->hasTable('users')) {
                $this->hasAdminUser = User::query()->exists(); // check if there is any user in the database
            } else {
                $this->hasAdminUser = false; // users table does not exist
            }

            $this->language = settings('language', 'en');
            $this->app_name = settings('app_name', 'Application');
            $this->currency = settings('currency', 'USD');
            $this->timezone = settings('timezone', 'UTC');
        }
    }

    public function changeStep(string $newStep)
    {
        if (! in_array($newStep, $this->steps, true)) {
            return;
        }

        if ($newStep !== 'requirements' && ! $this->areRequirementsMet()) {
            $this->step = 'requirements';

            return;
        }

        $this->step = $newStep;
    }

    public function recheckRequirements(): void
    {
        $this->areRequirementsMet();
    }

    /**
     * Requirement checks grouped for display in the installer.
     */
    public function getRequirementGroupsProperty(): array
    {
        $phpPassed = version_compare(PHP_VERSION, $this->minPhpVersion, '>=');

        $extensions = array_map(fn (string $extension) => [
            'label' => $extension,
            'passed' => extension_loaded($extension),
        ], $this->requiredExtensions);

        $paths = array_map(fn (string $path) => [
            'label' => $path,
            'passed' => is_writable(base_path($path)),
        ], $this->writablePaths);

        return [
            'php' => [
                'title' => 'PHP version',
                'items' => [[
                    'label' => 'PHP '.$this->minPhpVersion.' or newer',
                    'detail' => 'Running '.PHP_VERSION,
                    'passed' => $phpPassed,
                ]],
            ],
            'extensions' => [
                'title' => 'PHP extensions',
                'items' => $extensions,
            ],
            'paths' => [
                'title' => 'Writable folders',
                'items' => $paths,
            ],
        ];
    }

    public function getRequirementSummaryProperty(): array
    {
        $items = collect($this->requirementGroups)->pluck('items')->flatten(1);

        return [
            'passed' => $items->where('passed', true)->count(),
            'total' => $items->count(),
        ];
    }

    public function updatedDatabaseConnection(string $value): void
    {
        if ($value === 'supabase') {
            $this->database_connection = 'supabase';
        }

        if ($value === 'pgsql') {
            $this->database_port = '5432';
        }

        if ($value === 'mysql') {
            $this->database_port = '3306';
        }

        $this->database_test_message = null;
        $this->database_test_success = false;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['first_name', 'last_name'], true)) {
            $this->resetErrorBag($property);
        }

        if (str_starts_with($property, 'database_') && $property !== 'database_test_message' && $property !== 'database_test_success') {
            $this->database_test_message = null;
            $this->database_test_success = false;
            $this->existing_database_tables_count = 0;
            $this->has_existing_database_tables = false;
            $this->confirm_migrate_existing_database = false;
        }
    }

    protected function adminUserRules(): array
    {
        return [
            // Kept in line with AuthActions::registerAsClient(), which creates the account.
            'first_name' => ['required', 'alpha', 'min:3', 'max:100'],
            'last_name' => ['required', 'alpha', 'min:3', 'max:100'],
            'username' => ['required', 'alpha_num', 'min:5', 'max:30', 'unique:users,username', new NotReservedUsername],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ];
    }

    public function updatedUsername(): void
    {
        if ($this->hasAdminUser) {
            return;
        }

        $this->validateOnly('username', $this->adminUserRules());
    }

    public function updatedEmail(): void
    {
        if ($this->hasAdminUser) {
            return;
        }

        $this->validateOnly('email', $this->adminUserRules());
    }

    public function updatedPassword(): void
    {
        if ($this->hasAdminUser) {
            return;
        }

        $this->validateOnly('password', $this->adminUserRules());
    }

    public function updatedPasswordConfirmation(): void
    {
        if ($this->hasAdminUser) {
            return;
        }

        $this->validateOnly('password', $this->adminUserRules());
    }

    public function generateSecurePassword(): void
    {
        $this->password = Str::password(20, true, true, true, false);
        $this->password_confirmation = $this->password;
        $this->show_password = true;
        $this->show_password_confirmation = true;

        if (! $this->hasAdminUser) {
            $this->validateOnly('password', $this->adminUserRules());
        }
    }

    public function getPasswordStrengthScoreProperty(): int
    {
        $password = $this->password;

        if ($password === '') {
            return 0;
        }

        $score = 0;

        if (strlen($password) >= 8) {
            $score += 25;
        }

        if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) {
            $score += 25;
        }

        if (preg_match('/\d/', $password)) {
            $score += 25;
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 25;
        }

        return min($score, 100);
    }

    protected function areRequirementsMet()
    {
        $this->areRequirementsMet = true;

        // check PHP version
        if (version_compare(PHP_VERSION, $this->minPhpVersion, '<')) {
            $this->areRequirementsMet = false;

            return false;
        }

        // check required extensions
        foreach ($this->requiredExtensions as $extension) {
            if (! extension_loaded($extension)) {
                $this->areRequirementsMet = false;

                return false;
            }
        }

        // check writable paths
        foreach ($this->writablePaths as $path) {
            if (! is_writable(base_path($path))) {
                $this->areRequirementsMet = false;

                return false;
            }
        }

        return true;
    }

    protected function licenseValidationEndpoint(): string
    {
        return 'https://api-v3.wemx.org/api/v1/licenses/validate';
    }

    protected function licenseReportEndpoint(): string
    {
        return 'https://api-v3.wemx.org/api/v1/licenses/report-check';
    }

    protected function buildLicenseReportPayload(string $eventType): array
    {
        return [
            'license_key' => trim((string) $this->license_key),
            'event_type' => $eventType,
            'domain' => request()->getHost(),
            'install_path' => base_path(),
            'php_version' => PHP_VERSION,
            'app_version' => (string) config('app.version', ''),
            'os_name' => PHP_OS_FAMILY,
            'os_version' => php_uname('r') ?: php_uname('v'),
            'server_software' => request()->server('SERVER_SOFTWARE'),
            'environment' => app()->environment(),
            'timezone' => (string) config('app.timezone', 'UTC'),
            'machine_ip_address' => gethostbyname(gethostname()),
        ];
    }

    protected function reportLicenseCheck(string $eventType): void
    {
        $payload = $this->buildLicenseReportPayload($eventType);
        if ($payload['license_key'] === '') {
            return;
        }

        try {
            Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->post($this->licenseReportEndpoint(), $payload);
        } catch (\Throwable) {
            // Reporting must never interrupt installation completion.
        }
    }

    public function updatedLicenseKey(): void
    {
        $this->licenseData = [];
        $this->isLicenseActive = false;
        $this->licenseCheckedSuccessfully = false;
        session()->forget('installer.license_active');
    }

    public function checkLicense(): void
    {
        $this->validate([
            'license_key' => 'required|string|starts_with:WMX-',
        ]);

        $this->validateLicenseKey();
    }

    public function validateLicenseKey(): void
    {
        $this->licenseData = [];
        $this->isLicenseActive = false;
        $this->licenseCheckedSuccessfully = false;
        session()->forget('installer.license_active');

        $licenseKey = trim((string) $this->license_key);
        if ($licenseKey === '') {
            return;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->post($this->licenseValidationEndpoint(), [
                    'license_key' => $licenseKey,
                    'domain' => request()->getHost(),
                ]);
        } catch (\Throwable) {
            $this->addError('license_key', 'Unable to contact the license server. Please try again.');

            return;
        }

        if (! $response->successful() || ! $response->json('success')) {
            $this->addError(
                'license_key',
                (string) ($response->json('message') ?: 'License key is not valid or has expired.')
            );

            return;
        }

        $validatedLicense = $response->json('data.0');
        if (! is_array($validatedLicense)) {
            $this->addError('license_key', 'License server returned an unexpected response.');

            return;
        }

        $dueDate = (string) data_get($validatedLicense, 'order.due_date', '');
        $expiresAt = 'Never';
        if ($dueDate !== '') {
            try {
                $expiresAt = Carbon::parse($dueDate)->format('d M Y H:i');
            } catch (\Throwable) {
                $expiresAt = $dueDate;
            }
        }

        $this->licenseData = [
            'license_key' => (string) ($validatedLicense['license_key'] ?? $licenseKey),
            'plan_name' => (string) data_get($validatedLicense, 'order.package', 'Not Available'),
            'email' => (string) data_get($validatedLicense, 'order.user.email', 'Not Available'),
            'status' => (string) data_get($validatedLicense, 'order.status', 'active'),
            'expires_at' => $expiresAt,
            'billing_cycle' => (string) data_get($validatedLicense, 'order.billing_cycle', 'Not Available'),
        ];

        EnvironmentWriter::write([
            'LICENSE_KEY' => (string) ($validatedLicense['license_key'] ?? $licenseKey),
        ]);

        // clear config cache
        Artisan::call('config:clear');

        $this->isLicenseActive = true;
        $this->licenseCheckedSuccessfully = true;
        session(['installer.license_active' => true]);
    }

    public function checkLicenseAndContinue()
    {
        $this->validate([
            'license_key' => 'required|string|not_in:test',
        ]);

        if (! $this->licenseCheckedSuccessfully || ! $this->isLicenseActive) {
            $this->checkLicense();
        }

        if ($this->getErrorBag()->has('license_key')) {
            return;
        }

        if (! $this->licenseCheckedSuccessfully || ! $this->isLicenseActive) {
            $this->addError('license_key', 'Please check your license key successfully before continuing.');

            return;
        }

        $this->step = 'database';
    }

    protected function databaseRules(): array
    {
        return [
            'database_connection' => 'required|in:mysql,pgsql,sqlite,supabase',
            'database_url' => 'nullable|string|max:2048',
            'database_host' => 'required_if:database_connection,mysql,pgsql|string|max:255',
            'database_port' => 'required_if:database_connection,mysql,pgsql|integer|min:1|max:65535',
            'database_name' => 'required_if:database_connection,mysql,pgsql|string|max:255',
            'database_username' => 'required_if:database_connection,mysql,pgsql|string|max:255',
            'database_password' => 'nullable|string|max:255',
            'database_path' => 'required_if:database_connection,sqlite|string|max:255',
        ];
    }

    protected function configureTemporaryDatabaseConnection(array $validated): void
    {
        if ($validated['database_connection'] === 'sqlite') {
            $databasePath = $this->resolveSqliteDatabasePath($validated['database_path']);

            $databaseDirectory = dirname($databasePath);
            if (! is_dir($databaseDirectory)) {
                throw new \RuntimeException('The SQLite directory does not exist: '.$databaseDirectory);
            }

            if (! file_exists($databasePath)) {
                touch($databasePath);
            }

            if (! is_writable($databasePath)) {
                throw new \RuntimeException('The SQLite database file is not writable: '.$databasePath);
            }

            Config::set('database.default', 'sqlite');
            Config::set('database.connections.sqlite.database', $databasePath);

            return;
        }

        $runtimeConnection = $validated['database_connection'] === 'supabase' ? 'pgsql' : $validated['database_connection'];

        Config::set('database.default', $runtimeConnection);
        Config::set('database.connections.'.$runtimeConnection.'.url', $validated['database_url'] ?? null);
        Config::set('database.connections.'.$runtimeConnection.'.host', $validated['database_host']);
        Config::set('database.connections.'.$runtimeConnection.'.port', (string) $validated['database_port']);
        Config::set('database.connections.'.$runtimeConnection.'.database', $validated['database_name']);
        Config::set('database.connections.'.$runtimeConnection.'.username', $validated['database_username']);
        Config::set('database.connections.'.$runtimeConnection.'.password', $validated['database_password']);
    }

    protected function resolveSqliteDatabasePath(string $databasePath): string
    {
        $trimmedPath = trim($databasePath);

        if ($trimmedPath === '') {
            return base_path('database/database.sqlite');
        }

        $isUnixAbsolutePath = str_starts_with($trimmedPath, '/');
        $isWindowsAbsolutePath = preg_match('/^[A-Za-z]:[\/\\\\]/', $trimmedPath) === 1;

        if ($isUnixAbsolutePath || $isWindowsAbsolutePath) {
            return $trimmedPath;
        }

        return base_path($trimmedPath);
    }

    protected function writeDatabaseEnvironment(array $validated): void
    {
        $selectedConnection = $validated['database_connection'] === 'supabase' ? 'pgsql' : $validated['database_connection'];
        $env = [
            'DB_CONNECTION' => $selectedConnection,
        ];

        if ($validated['database_connection'] === 'sqlite') {
            // Store a dotenv-safe relative path to avoid Windows path escaping issues.
            $env['DB_DATABASE'] = trim($validated['database_path']) ?: 'database/database.sqlite';
            $env['DB_HOST'] = '';
            $env['DB_PORT'] = '';
            $env['DB_USERNAME'] = '';
            $env['DB_PASSWORD'] = '';
            $env['DB_URL'] = '';
        } else {
            $env['DB_URL'] = $validated['database_url'] ?? '';
            $env['DB_HOST'] = $validated['database_host'];
            $env['DB_PORT'] = (string) $validated['database_port'];
            $env['DB_DATABASE'] = $validated['database_name'];
            $env['DB_USERNAME'] = $validated['database_username'];
            $env['DB_PASSWORD'] = $validated['database_password'] ?? '';
        }

        EnvironmentWriter::write($env);
    }

    protected function validatedDatabaseConfiguration(): array
    {
        $validated = Validator::make([
            'database_connection' => $this->database_connection,
            'database_url' => trim($this->database_url),
            'database_host' => trim($this->database_host),
            'database_port' => $this->database_port,
            'database_name' => trim($this->database_name),
            'database_username' => trim($this->database_username),
            'database_password' => $this->database_password,
            'database_path' => trim($this->database_path),
        ], $this->databaseRules())->validate();

        if ($validated['database_connection'] === 'supabase' && blank($validated['database_url'])) {
            throw ValidationException::withMessages([
                'database_url' => 'Database URL is required when using Supabase (by URL).',
            ]);
        }

        return $validated;
    }

    protected function testDatabaseConfiguration(array $validated): void
    {
        $this->configureTemporaryDatabaseConnection($validated);
        DB::purge(config('database.default'));
        DB::connection()->getPdo();
    }

    protected function detectExistingDatabaseTables(): void
    {
        $tables = DB::connection()->getSchemaBuilder()->getTableListing();

        $ignoredTables = [
            'migrations',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'sessions',
            'password_reset_tokens',
        ];

        $applicationTables = array_values(array_diff($tables, $ignoredTables));

        $this->existing_database_tables_count = count($applicationTables);
        $this->has_existing_database_tables = $this->existing_database_tables_count > 0;
    }

    public function testDatabaseConnection(): void
    {
        $this->resetErrorBag();
        $this->database_error = null;
        $this->database_test_message = null;
        $this->database_test_success = false;

        if ($this->isDatabaseDeployed) {
            $this->database_test_success = true;
            $this->database_test_message = 'Database is already configured and reachable.';

            return;
        }

        if ($this->database_connection === 'sqlite' && ! $this->isSqliteAvailable) {
            $this->addError('database_connection', 'SQLite driver is not available on this server.');

            return;
        }

        try {
            $validated = $this->validatedDatabaseConfiguration();
            $this->testDatabaseConfiguration($validated);
            $this->detectExistingDatabaseTables();
            $this->database_test_success = true;
            $this->database_test_message = 'Database connection successful.';
        } catch (\Throwable $exception) {
            $this->database_test_success = false;
            $this->database_error = $exception->getMessage();
            $this->database_test_message = 'Database connection failed. Please review your settings.';
            $this->existing_database_tables_count = 0;
            $this->has_existing_database_tables = false;
            $this->confirm_migrate_existing_database = false;
        }
    }

    public function checkDatabaseAndContinue()
    {
        $this->resetErrorBag();
        $this->database_error = null;

        if ($this->isDatabaseDeployed) {
            $this->database_test_success = true;
            $this->database_test_message = 'Database is already configured and reachable. Continuing to next step.';
            $this->step = 'setup';

            return;
        }

        if (! $this->database_test_success) {
            $this->testDatabaseConnection();

            if (! $this->database_test_success) {
                return;
            }
        }

        if ($this->has_existing_database_tables && ! $this->confirm_migrate_existing_database) {
            $this->addError('confirm_migrate_existing_database', 'Please confirm before migrating a database that already contains tables.');

            return;
        }

        if ($this->database_connection === 'sqlite' && ! $this->isSqliteAvailable) {
            $this->addError('database_connection', 'SQLite driver is not available on this server.');

            return;
        }

        $validated = $this->validatedDatabaseConfiguration();

        try {
            $this->testDatabaseConfiguration($validated);
            $this->detectExistingDatabaseTables();
            $this->database_test_success = true;
            $this->database_test_message = 'Database connection successful.';

            $this->writeDatabaseEnvironment($validated);
            Artisan::call('config:clear');

            DB::purge(config('database.default'));
            Artisan::call('migrate', [
                '--force' => true,
            ]);
        } catch (\Throwable $exception) {
            $this->isDatabaseDeployed = false;
            $this->database_test_success = false;
            $this->database_error = $exception->getMessage();
            $this->addError('database_connection', 'Unable to connect or migrate with these settings. Please review your database details.');

            return;
        }

        $this->isDatabaseDeployed = true;
        $this->database_error = null;

        if (DB::getSchemaBuilder()->hasTable('users')) {
            $this->hasAdminUser = User::query()->exists();
        }

        $this->step = 'setup';
    }

    public function saveAppSettingsAndContinue()
    {
        Setting::actions()->updateApplicationSettingsAsAdmin([
            'app_name' => $this->app_name,
            'language' => $this->language,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
        ]);

        // clear config cache
        Artisan::call('config:clear');

        $this->step = 'create_user';
    }

    public function createAdminUserAndContinue()
    {
        if ($this->hasAdminUser) {
            $this->step = 'complete';

            return;
        }

        $this->validate($this->adminUserRules());

        User::authActions()->registerAsClient([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->step = 'complete';
    }

    public function finish()
    {
        EnvironmentWriter::write([
            'APP_INSTALLED' => 'true',
        ]);

        // clear config cache
        Artisan::call('config:clear');
        session()->forget('installer.license_active');
        $this->reportLicenseCheck('new_install');

        $this->redirect('/');
    }

    public function render()
    {
        if ($this->step !== 'complete' and config('app.installed')) {
            $this->redirect('/');
        }

        if ($this->step !== 'requirements' && $this->step !== 'complete' && ! $this->areRequirementsMet()) {
            $this->step = 'requirements';
        }

        if (in_array($this->step, ['database', 'setup', 'create_user', 'complete'], true) and ! $this->isLicenseActive) {
            $this->step = 'activation';
        }

        if (in_array($this->step, ['setup', 'create_user', 'complete'], true) and ! $this->isDatabaseDeployed) {
            $this->step = 'database';
        }

        return view('install::livewire.install-wizard');
    }
}
