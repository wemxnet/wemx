<?php

namespace App\Mail;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * An installable email layout discovered from resources/email_templates/{slug}.
 *
 * A theme folder must contain email.blade.php. Optional text.blade.php replaces
 * the plain-text part. Optional theme.json may set "name" and "description".
 *
 * HTML themes receive name, body (HTML, including any table), text, subject, and button.
 * Markdown themes (theme.json "format": "markdown") receive name, body (plain markdown),
 * markdownTable, and button, and render through Laravel's mail layout.
 */
class EmailTheme
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public string $path,
        public string $format = 'html',
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $directory = resource_path('email_templates');

        if (! is_dir($directory)) {
            return [];
        }

        $themes = [];

        foreach (File::directories($directory) as $path) {
            if (! File::exists($path.'/email.blade.php')) {
                continue;
            }

            $slug = basename($path);
            $meta = self::metadata($path);
            $name = self::metaString($meta, 'name');
            $format = self::metaString($meta, 'format');

            $themes[$slug] = new self(
                slug: $slug,
                name: $name !== '' ? $name : Str::headline($slug),
                description: self::metaString($meta, 'description'),
                path: $path,
                format: $format === 'markdown' ? 'markdown' : 'html',
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
        $configured = (string) settings('email_theme', 'default');

        if (isset($themes[$configured])) {
            return $themes[$configured];
        }

        if (isset($themes['default'])) {
            return $themes['default'];
        }

        if ($themes !== []) {
            return array_values($themes)[0];
        }

        throw new RuntimeException('No email themes are installed.');
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

    public function usesMarkdown(): bool
    {
        return $this->format === 'markdown';
    }

    public function cssView(): ?string
    {
        if (! is_file($this->path.'/theme.css')) {
            return null;
        }

        $this->ensureRegistered();

        return $this->namespace().'::theme';
    }

    public function mailComponentsPath(): ?string
    {
        $path = $this->path.'/mail';

        return is_dir($path) ? $path : null;
    }

    public function htmlView(): string
    {
        $this->ensureRegistered();

        return $this->namespace().'::email';
    }

    public function textView(): string
    {
        $this->ensureRegistered();
        $view = $this->namespace().'::text';

        return view()->exists($view) ? $view : 'emails.email-text';
    }

    public function ensureRegistered(): void
    {
        $namespace = $this->namespace();

        if (view()->exists($namespace.'::email')) {
            return;
        }

        View::addNamespace($namespace, $this->path);
    }

    public function namespace(): string
    {
        return 'email-theme-'.$this->slug;
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
