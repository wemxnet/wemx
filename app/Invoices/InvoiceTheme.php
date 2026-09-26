<?php

namespace App\Invoices;

use App\Models\Payment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * An installable invoice layout discovered from resources/invoices/{slug}.
 *
 * A theme folder must contain invoice.blade.php. Optional theme.json may set
 * "name" and "description". The view receives $payment, with user.address,
 * gatewayConfig, and taxDetails loaded.
 */
class InvoiceTheme
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public string $path,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $directory = resource_path('invoices');

        if (! is_dir($directory)) {
            return [];
        }

        $themes = [];

        foreach (File::directories($directory) as $path) {
            if (! File::exists($path.'/invoice.blade.php')) {
                continue;
            }

            $slug = basename($path);
            $meta = self::metadata($path);
            $name = self::metaString($meta, 'name');

            $themes[$slug] = new self(
                slug: $slug,
                name: $name !== '' ? $name : Str::headline($slug),
                description: self::metaString($meta, 'description'),
                path: $path,
            );
        }

        ksort($themes);

        return $themes;
    }

    public static function find(?string $slug): ?self
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return self::all()[$slug] ?? null;
    }

    public static function default(): self
    {
        $themes = self::all();
        $configured = (string) settings('invoice_theme', 'default');

        if (isset($themes[$configured])) {
            return $themes[$configured];
        }

        if (isset($themes['default'])) {
            return $themes['default'];
        }

        if ($themes !== []) {
            return array_values($themes)[0];
        }

        throw new RuntimeException('No invoice themes are installed.');
    }

    public static function resolve(?string $slug): self
    {
        return self::find($slug) ?? self::default();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $theme) {
            $options[$theme->slug] = $theme->name;
        }

        return $options;
    }

    public function view(): string
    {
        $this->ensureRegistered();

        return $this->namespace().'::invoice';
    }

    /**
     * @return array{payment: Payment}
     */
    public function viewData(Payment $payment): array
    {
        $payment->loadMissing(['user.address', 'gatewayConfig', 'taxDetails']);

        return [
            'payment' => $payment,
        ];
    }

    public function ensureRegistered(): void
    {
        $namespace = $this->namespace();

        if (view()->exists($namespace.'::invoice')) {
            return;
        }

        View::addNamespace($namespace, $this->path);
    }

    public function namespace(): string
    {
        return 'invoice-theme-'.$this->slug;
    }

    /**
     * @return array<string, mixed>
     */
    private static function metadata(string $path): array
    {
        $file = $path.'/theme.json';

        if (! File::exists($file)) {
            return [];
        }

        $decoded = json_decode(File::get($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function metaString(array $meta, string $key): string
    {
        $value = $meta[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
